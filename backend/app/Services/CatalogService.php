<?php

namespace App\Services;

use App\Models\Product;
use App\Models\ProductBarcode;
use App\Models\ProductImage;
use App\Models\ProductVariant;
use App\Models\ProductVariantOption;
use App\Models\ProductVariantOptionValue;
use App\Models\ProductVariantValue;
use App\Services\AuditService;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;

class CatalogService
{
    public function __construct(
        private readonly AuditService $auditService = new AuditService(),
    ) {}

    public function createProduct(array $data, int $tenantId): Product
    {
        return DB::transaction(function () use ($data, $tenantId) {
            $hasVariants = $data['has_variants'] ?? false;

            $this->validateSkuUnique($data['sku'] ?? null, $tenantId);
            $this->validateBarcodeUnique($data['barcode'] ?? null, $tenantId);

            $product = Product::create([
                'tenant_id' => $tenantId,
                'category_id' => $data['category_id'],
                'name' => $data['name'],
                'sku' => $data['sku'] ?? null,
                'barcode' => $data['barcode'] ?? null,
                'description' => $data['description'] ?? null,
                'cost_price' => $data['cost_price'],
                'selling_price' => $data['selling_price'],
                'unit' => $data['unit'],
                'image' => $data['image'] ?? null,
                'is_active' => $data['is_active'] ?? true,
                'has_variants' => $hasVariants,
                'is_trackable' => $data['is_trackable'] ?? true,
                'min_stock' => $data['min_stock'] ?? null,
                'base_unit_id' => $data['base_unit_id'] ?? null,
                'purchase_unit_id' => $data['purchase_unit_id'] ?? null,
            ]);

            if (!empty($data['barcodes'])) {
                foreach ($data['barcodes'] as $barcode) {
                    $this->validateBarcodeUnique($barcode, $tenantId, null, $product->id);
                    ProductBarcode::create([
                        'tenant_id' => $tenantId,
                        'product_id' => $product->id,
                        'variant_id' => null,
                        'barcode' => $barcode,
                    ]);
                }
            }

            if (!empty($data['images'])) {
                foreach ($data['images'] as $image) {
                    ProductImage::create([
                        'product_id' => $product->id,
                        'url' => $image['url'],
                        'sort_order' => $image['sort_order'] ?? 0,
                    ]);
                }
            }

            if ($hasVariants && !empty($data['variant_options'])) {
                $optionValueMap = [];

                foreach ($data['variant_options'] as $optionData) {
                    $option = ProductVariantOption::create([
                        'product_id' => $product->id,
                        'name' => $optionData['name'],
                        'sort_order' => $optionData['sort_order'] ?? 0,
                    ]);

                    foreach ($optionData['values'] as $valueData) {
                        $value = ProductVariantOptionValue::create([
                            'option_id' => $option->id,
                            'value' => $valueData['value'],
                            'sort_order' => $valueData['sort_order'] ?? 0,
                        ]);
                        $optionValueMap[$valueData['value']] = $value->id;
                    }
                }

                if (!empty($data['variants'])) {
                    foreach ($data['variants'] as $variantData) {
                        $this->validateSkuUnique($variantData['sku'] ?? null, $tenantId, null, $product->id);

                        $variant = ProductVariant::create([
                            'product_id' => $product->id,
                            'sku' => $variantData['sku'] ?? null,
                            'barcode' => $variantData['barcode'] ?? null,
                            'price_override' => $variantData['price_override'] ?? null,
                            'cost_price_override' => $variantData['cost_price_override'] ?? null,
                            'is_active' => $variantData['is_active'] ?? true,
                        ]);

                        if (!empty($variantData['barcode'])) {
                            $this->validateBarcodeUnique($variantData['barcode'], $tenantId, $variant->id, $product->id);
                            ProductBarcode::create([
                                'tenant_id' => $tenantId,
                                'product_id' => $product->id,
                                'variant_id' => $variant->id,
                                'barcode' => $variantData['barcode'],
                            ]);
                        }

                        foreach ($variantData['option_value_ids'] as $ovId) {
                            $resolvedId = $optionValueMap[$ovId] ?? $ovId;
                            ProductVariantValue::create([
                                'variant_id' => $variant->id,
                                'option_value_id' => $resolvedId,
                            ]);
                        }
                    }
                }
            }

            $this->auditService->log('product.created', 'product', $product->id, null, $product->fresh()->load(['category', 'variants', 'images', 'barcodes'])->toArray());

            return $product->fresh(['category', 'variants.optionValues.option', 'images', 'barcodes', 'variantOptions.values']);
        });
    }

    public function updateProduct(int $id, array $data, int $tenantId): Product
    {
        return DB::transaction(function () use ($id, $data, $tenantId) {
            $product = Product::where('tenant_id', $tenantId)->findOrFail($id);
            $oldValues = $product->toArray();

            if (isset($data['sku'])) {
                $this->validateSkuUnique($data['sku'], $tenantId, null, $product->id);
            }
            if (isset($data['barcode'])) {
                $this->validateBarcodeUnique($data['barcode'], $tenantId, null, $product->id);
            }

            $product->update($data);
            $product->refresh();

            $this->auditService->log('product.updated', 'product', $product->id, $oldValues, $product->toArray());

            return $product->fresh(['category', 'variants.optionValues.option', 'images', 'barcodes', 'variantOptions.values']);
        });
    }

    public function deleteProduct(int $id, int $tenantId): void
    {
        DB::transaction(function () use ($id, $tenantId) {
            $product = Product::where('tenant_id', $tenantId)->findOrFail($id);
            $oldValues = $product->toArray();
            $product->delete();
            $this->auditService->log('product.deleted', 'product', $product->id, $oldValues, null);
        });
    }

    public function generateVariantCombinations(int $productId, array $optionValueIdGroups): array
    {
        $product = Product::findOrFail($productId);
        $combinations = $this->cartesianProduct($optionValueIdGroups);

        $result = [];
        foreach ($combinations as $combination) {
            $labels = [];
            foreach ($combination as $ovId) {
                $ov = ProductVariantOptionValue::with('option')->find($ovId);
                if ($ov) {
                    $labels[] = $ov->value;
                }
            }
            $result[] = [
                'option_value_ids' => $combination,
                'label' => implode(' / ', $labels),
            ];
        }

        return $result;
    }

    public function importProducts(UploadedFile $file, int $tenantId): array
    {
        $path = $file->getRealPath();
        $handle = fopen($path, 'r');
        if ($handle === false) {
            throw new \RuntimeException('Failed to open CSV file');
        }

        $headers = fgetcsv($handle);
        if ($headers === false) {
            fclose($handle);
            throw new \RuntimeException('Empty CSV file');
        }

        $headers = array_map('trim', $headers);

        $required = ['name', 'category_name', 'cost_price', 'selling_price', 'unit'];
        foreach ($required as $req) {
            if (!in_array($req, $headers)) {
                fclose($handle);
                throw new \InvalidArgumentException("Missing required column: {$req}", 422);
            }
        }

        $rows = [];
        $rowCount = 0;
        while (($row = fgetcsv($handle)) !== false) {
            $rowCount++;
            if ($rowCount > 1000) {
                fclose($handle);
                throw new \InvalidArgumentException('CSV import exceeds 1000 row limit', 422);
            }

            $data = array_combine($headers, array_map('trim', $row));
            $data = $this->sanitizeCsvCell($data);
            $rows[] = $data;
        }
        fclose($handle);

        $created = 0;
        $updated = 0;
        $errors = [];

        DB::transaction(function () use ($rows, $tenantId, &$created, &$updated, &$errors) {
            foreach ($rows as $index => $row) {
                $rowNumber = $index + 2;

                $validator = Validator::make($row, [
                    'name' => 'required|string|max:255',
                    'category_name' => 'required|string',
                    'sku' => 'nullable|string|max:100',
                    'barcode' => 'nullable|string|max:100',
                    'cost_price' => 'required|numeric|min:0',
                    'selling_price' => 'required|numeric|min:0',
                    'unit' => 'required|string|max:50',
                    'is_active' => 'nullable|in:0,1,true,false',
                    'description' => 'nullable|string',
                ]);

                if ($validator->fails()) {
                    $errors[] = [
                        'row' => $rowNumber,
                        'sku' => $row['sku'] ?? null,
                        'error' => implode(', ', $validator->errors()->all()),
                    ];
                    continue;
                }

                $category = \App\Models\Category::where('tenant_id', $tenantId)
                    ->where('name', $row['category_name'])
                    ->first();

                if (!$category) {
                    $errors[] = [
                        'row' => $rowNumber,
                        'sku' => $row['sku'] ?? null,
                        'error' => "Category '{$row['category_name']}' not found",
                    ];
                    continue;
                }

                $isActive = isset($row['is_active']) ? in_array($row['is_active'], ['1', 'true'], true) : true;

                if (!empty($row['sku'])) {
                    $existing = Product::where('tenant_id', $tenantId)->where('sku', $row['sku'])->first();

                    if ($existing) {
                        $existing->update([
                            'name' => $row['name'],
                            'category_id' => $category->id,
                            'barcode' => $row['barcode'] ?? $existing->barcode,
                            'description' => $row['description'] ?? $existing->description,
                            'cost_price' => $row['cost_price'],
                            'selling_price' => $row['selling_price'],
                            'unit' => $row['unit'],
                            'is_active' => $isActive,
                        ]);
                        $updated++;
                    } else {
                        Product::create([
                            'tenant_id' => $tenantId,
                            'category_id' => $category->id,
                            'name' => $row['name'],
                            'sku' => $row['sku'],
                            'barcode' => $row['barcode'] ?? null,
                            'description' => $row['description'] ?? null,
                            'cost_price' => $row['cost_price'],
                            'selling_price' => $row['selling_price'],
                            'unit' => $row['unit'],
                            'is_active' => $isActive,
                        ]);
                        $created++;
                    }
                } else {
                    Product::create([
                        'tenant_id' => $tenantId,
                        'category_id' => $category->id,
                        'name' => $row['name'],
                        'sku' => null,
                        'barcode' => $row['barcode'] ?? null,
                        'description' => $row['description'] ?? null,
                        'cost_price' => $row['cost_price'],
                        'selling_price' => $row['selling_price'],
                        'unit' => $row['unit'],
                        'is_active' => $isActive,
                    ]);
                    $created++;
                }
            }
        });

        $this->auditService->log('product.imported', 'product', null, null, [
            'created' => $created,
            'updated' => $updated,
            'errors' => count($errors),
        ]);

        return [
            'created' => $created,
            'updated' => $updated,
            'errors' => $errors,
        ];
    }

    public function exportProducts(int $tenantId): string
    {
        $products = Product::where('tenant_id', $tenantId)
            ->with('category')
            ->orderBy('name')
            ->get();

        $output = fopen('php://temp', 'r+');
        fputcsv($output, ['name', 'category_name', 'sku', 'barcode', 'cost_price', 'selling_price', 'unit', 'is_active', 'description']);

        foreach ($products as $product) {
            fputcsv($output, [
                $product->name,
                $product->category?->name ?? '',
                $product->sku ?? '',
                $product->barcode ?? '',
                $product->cost_price,
                $product->selling_price,
                $product->unit,
                $product->is_active ? '1' : '0',
                $product->description ?? '',
            ]);
        }

        rewind($output);
        $csv = stream_get_contents($output);
        fclose($output);

        return $csv;
    }

    public function lookupByBarcode(string $barcode, int $tenantId): ?array
    {
        $productBarcode = ProductBarcode::where('tenant_id', $tenantId)
            ->where('barcode', $barcode)
            ->with(['product', 'variant'])
            ->first();

        if ($productBarcode) {
            return [
                'product' => $productBarcode->product,
                'variant' => $productBarcode->variant,
            ];
        }

        $product = Product::where('tenant_id', $tenantId)
            ->where('barcode', $barcode)
            ->first();

        if ($product) {
            return [
                'product' => $product,
                'variant' => null,
            ];
        }

        return null;
    }

    private function validateSkuUnique(?string $sku, int $tenantId, ?int $variantId = null, ?int $productId = null): void
    {
        if (!$sku) {
            return;
        }

        $productQuery = Product::where('tenant_id', $tenantId)->where('sku', $sku);
        if ($productId) {
            $productQuery->where('id', '!=', $productId);
        }
        if ($productQuery->exists()) {
            throw new \InvalidArgumentException("SKU '{$sku}' already exists for another product in this tenant", 422);
        }

        $variantQuery = ProductVariant::whereHas('product', function ($q) use ($tenantId) {
            $q->where('tenant_id', $tenantId);
        })->where('sku', $sku);
        if ($variantId) {
            $variantQuery->where('id', '!=', $variantId);
        }
        if ($variantQuery->exists()) {
            throw new \InvalidArgumentException("SKU '{$sku}' already exists for another variant in this tenant", 422);
        }
    }

    private function validateBarcodeUnique(?string $barcode, int $tenantId, ?int $variantId = null, ?int $productId = null): void
    {
        if (!$barcode) {
            return;
        }

        $barcodeQuery = ProductBarcode::where('tenant_id', $tenantId)->where('barcode', $barcode);
        if ($productId) {
            $barcodeQuery->where('product_id', '!=', $productId);
        }
        if ($barcodeQuery->exists()) {
            throw new \InvalidArgumentException("Barcode '{$barcode}' already exists in this tenant", 422);
        }

        $productQuery = Product::where('tenant_id', $tenantId)->where('barcode', $barcode);
        if ($productId) {
            $productQuery->where('id', '!=', $productId);
        }
        if ($productQuery->exists()) {
            throw new \InvalidArgumentException("Barcode '{$barcode}' already exists in this tenant", 422);
        }
    }

    private function cartesianProduct(array $groups): array
    {
        $result = [[]];
        foreach ($groups as $group) {
            $newResult = [];
            foreach ($result as $existing) {
                foreach ($group as $item) {
                    $newResult[] = array_merge($existing, [$item]);
                }
            }
            $result = $newResult;
        }
        return $result;
    }

    private function sanitizeCsvCell(array $data): array
    {
        foreach ($data as $key => $value) {
            if (is_string($value) && strlen($value) > 0) {
                $firstChar = $value[0];
                if (in_array($firstChar, ['=', '+', '-', '@'])) {
                    $data[$key] = "'" . $value;
                }
            }
        }
        return $data;
    }
}
