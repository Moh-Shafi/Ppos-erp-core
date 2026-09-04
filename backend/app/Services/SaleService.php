<?php

namespace App\Services;

use App\Models\Customer;
use App\Models\Inventory;
use App\Models\Payment;
use App\Models\PriceListItem;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\Sale;
use App\Models\SaleItem;
use App\Models\Store;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Collection;

class SaleService
{
    public function __construct(
        private InventoryService $inventoryService,
        private PaymentService $paymentService,
        private ModuleService $moduleService,
        private CustomerCreditService $creditService,
        private LoyaltyService $loyaltyService,
        private AuditService $auditService,
    ) {}

    /**
     * Atomic checkout: Sale + SaleItems + Inventory decrease + Movements + Payment.
     * All in a single DB::transaction — if any step fails, everything rolls back.
     *
     * @param  array  $data  store_id, customer_id?, items[], payments[], notes?, discount?, tax?
     *   items[]: product_id, quantity (unit_price from Product.selling_price, NOT from request)
     *   payments[]: payment_method, amount (change auto-calculated for cash)
     * @return Sale
     * @throws \DomainException  For validation failures (cross-tenant, insufficient stock, etc.)
     * @throws \InvalidArgumentException  For invalid quantities or amounts
     */
    public function checkout(array $data): Sale
    {
        return DB::transaction(function () use ($data) {
            $user = Auth::user();
            $tenantId = $user->tenant_id;

            // --- Validate store belongs to tenant ---
            $store = Store::withoutTenantScope()->find($data['store_id']);
            if (!$store || $store->tenant_id !== $tenantId) {
                throw new \DomainException('Store does not belong to your tenant');
            }

            // --- Validate customer (if provided) belongs to tenant ---
            $customerId = null;
            if (!empty($data['customer_id'])) {
                $customer = Customer::withoutTenantScope()->find($data['customer_id']);
                if (!$customer || $customer->tenant_id !== $tenantId) {
                    throw new \DomainException('Customer does not belong to your tenant');
                }
                $customerId = $customer->id;
            }

            // --- Validate items ---
            if (empty($data['items'])) {
                throw new \DomainException('Sale must have at least one item');
            }

            $productIds = array_column($data['items'], 'product_id');
            $products = Product::withoutTenantScope()
                ->whereIn('id', $productIds)
                ->get()
                ->keyBy('id');

            // Collect variant IDs for batch loading
            $variantIds = array_filter(array_column($data['items'], 'variant_id'));
            $variants = collect();
            if (!empty($variantIds)) {
                $variants = ProductVariant::whereIn('id', $variantIds)->get()->keyBy('id');
            }

            // Check all products exist and belong to tenant
            foreach ($data['items'] as $item) {
                $product = $products->get($item['product_id']);
                if (!$product) {
                    throw new \DomainException("Product {$item['product_id']} not found");
                }
                if ($product->tenant_id !== $tenantId) {
                    throw new \DomainException("Product {$product->id} does not belong to your tenant");
                }
                if (!$product->is_active) {
                    throw new \DomainException("Product {$product->name} is not active");
                }
                if ($item['quantity'] <= 0) {
                    throw new \DomainException('Quantity must be greater than 0');
                }

                // Validate variant if provided
                if (!empty($item['variant_id'])) {
                    $variant = $variants->get($item['variant_id']);
                    if (!$variant) {
                        throw new \DomainException("Variant {$item['variant_id']} not found");
                    }
                    if ($variant->product_id !== $product->id) {
                        throw new \DomainException("Variant {$variant->id} does not belong to product {$product->name}");
                    }
                    if (!$variant->is_active) {
                        throw new \DomainException("Variant for {$product->name} is not active");
                    }
                } elseif ($product->has_variants) {
                    throw new \DomainException("Product {$product->name} requires a variant selection");
                }
            }

            // Check for duplicate products in cart
            $uniqueProductIds = array_unique($productIds);
            if (count($uniqueProductIds) !== count($productIds)) {
                throw new \DomainException('Duplicate products in cart are not allowed');
            }

            // --- Load price list items if customer has a price list ---
            $priceListId = null;
            $priceListItems = collect();
            if ($customerId) {
                $customer = Customer::withoutTenantScope()->find($customerId);
                if ($customer && $customer->price_list_id) {
                    $priceListId = $customer->price_list_id;
                    $priceListItems = PriceListItem::where('price_list_id', $priceListId)
                        ->whereIn('product_id', $productIds)
                        ->get();
                }
            }

            // --- Lock inventory rows for all products in this store ---
            $inventories = Inventory::withoutTenantScope()
                ->where('tenant_id', $tenantId)
                ->where('store_id', $store->id)
                ->whereIn('product_id', $productIds)
                ->lockForUpdate()
                ->get()
                ->keyBy('product_id');

            // Check sufficient stock for all items BEFORE creating anything
            foreach ($data['items'] as $item) {
                $inv = $inventories->get($item['product_id']);
                $currentQty = $inv ? $inv->quantity : 0;
                if ($currentQty < $item['quantity']) {
                    $product = $products->get($item['product_id']);
                    throw new \InvalidArgumentException(
                        "Insufficient stock for {$product->name}. Available: {$currentQty}, Requested: {$item['quantity']}"
                    );
                }
            }

            // --- Calculate totals (backend, never from request) ---
            $subtotal = 0;
            $totalItemDiscount = 0;
            $totalItemTax = 0;

            $itemData = [];
            $modifierService = null;
            $kitchenEnabled = $this->moduleService->isModuleEnabled($tenantId, 'kitchen');
            if ($kitchenEnabled) {
                $modifierService = app(ModifierService::class);
            }

            foreach ($data['items'] as $item) {
                $product = $products->get($item['product_id']);
                $variant = !empty($item['variant_id']) ? $variants->get($item['variant_id']) : null;

                // --- Price resolution: Price List → Variant price_override → Product selling_price ---
                $originalPrice = (float) $product->selling_price;
                $unitPrice = $originalPrice;

                // Step 1: Variant price_override
                if ($variant && $variant->price_override !== null) {
                    $unitPrice = (float) $variant->price_override;
                }

                // Step 2: Price list item (highest priority — overrides variant)
                if ($priceListId) {
                    $pliQuery = $priceListItems->where('product_id', $product->id);
                    if ($variant) {
                        $pli = $pliQuery->where('variant_id', $variant->id)->first()
                            ?? $pliQuery->whereNull('variant_id')->first();
                    } else {
                        $pli = $pliQuery->whereNull('variant_id')->first();
                    }
                    if ($pli) {
                        $unitPrice = (float) $pli->price;
                    }
                }

                // --- Phase 8: Modifier price deltas (8A) ---
                $modifierDelta = 0;
                $modifierData = null;
                if ($kitchenEnabled && !empty($item['modifiers'])) {
                    [$modifierDelta, $modifierData] = $modifierService->resolveModifiers($item['modifiers'], $product);
                }

                $quantity = $item['quantity'];
                $lineSubtotal = ($unitPrice + $modifierDelta) * $quantity;
                $lineDiscount = 0;
                $lineTax = 0;
                $lineTotal = $lineSubtotal - $lineDiscount + $lineTax;

                $subtotal += $lineSubtotal;
                $totalItemDiscount += $lineDiscount;
                $totalItemTax += $lineTax;

                $itemData[] = [
                    'product_id' => $product->id,
                    'variant_id' => $variant?->id,
                    'product_name' => $product->name,
                    'sku' => $variant?->sku ?? $product->sku,
                    'quantity' => $quantity,
                    'unit_price' => $unitPrice + $modifierDelta,
                    'original_price' => ($unitPrice !== $originalPrice) ? $originalPrice : null,
                    'discount' => $lineDiscount,
                    'tax' => $lineTax,
                    'subtotal' => $lineSubtotal,
                    'total' => $lineTotal,
                    'metadata' => $modifierData ? ['modifiers' => $modifierData] : null,
                ];
            }

            // Sale-level discount and tax (from request but validated)
            $saleDiscount = (float) ($data['discount'] ?? 0);
            $saleTax = (float) ($data['tax'] ?? 0);

            if ($saleDiscount < 0) {
                throw new \DomainException('Discount cannot be negative');
            }
            if ($saleDiscount > $subtotal) {
                throw new \DomainException('Discount cannot exceed subtotal');
            }
            if ($saleTax < 0) {
                throw new \DomainException('Tax cannot be negative');
            }

            $total = $subtotal - $saleDiscount + $saleTax;

            // --- Credit limit check (only if sales.customer_credit feature enabled) ---
            if ($customerId && $this->moduleService->isFeatureEnabled($tenantId, 'sales.customer_credit')) {
                $customerForCredit = Customer::withoutTenantScope()->find($customerId);
                $creditCheck = $this->creditService->checkLimit($customerForCredit, $total);
                if (!$creditCheck['allowed']) {
                    $this->auditService->log(
                        'pos.credit_limit_blocked',
                        'sale',
                        null,
                        null,
                        ['customer_id' => $customerId, 'sale_total' => $total, 'credit_limit' => $creditCheck['credit_limit'], 'outstanding' => $creditCheck['outstanding_balance']],
                        tenantId: $tenantId,
                    );
                    throw new \DomainException(
                        "Credit limit exceeded. Outstanding: {$creditCheck['outstanding_balance']}, Limit: {$creditCheck['credit_limit']}, Sale total: {$total}"
                    );
                }
            }

            // --- Validate payments ---
            if (empty($data['payments'])) {
                throw new \DomainException('At least one payment is required');
            }

            $totalPaid = 0;
            foreach ($data['payments'] as $pay) {
                $amount = (float) $pay['amount'];
                if ($amount <= 0) {
                    throw new \DomainException('Payment amount must be greater than 0');
                }
                $validMethods = ['cash', 'qris', 'card', 'bank_transfer'];
                if (!in_array($pay['payment_method'], $validMethods)) {
                    throw new \DomainException("Invalid payment method: {$pay['payment_method']}");
                }
                $totalPaid += $amount;
            }

            // Determine payment status
            $changeAmount = 0;
            if ($totalPaid >= $total) {
                $paymentStatus = 'paid';
                $changeAmount = $totalPaid - $total;
            } elseif ($totalPaid > 0) {
                $paymentStatus = 'partial';
            } else {
                $paymentStatus = 'unpaid';
            }

            // --- Create Sale ---
            $sale = new Sale;
            $sale->tenant_id = $tenantId;
            $sale->store_id = $store->id;
            $sale->cashier_id = $user->id;
            $sale->customer_id = $customerId;
            $sale->sale_number = $this->generateSaleNumber($tenantId);
            $sale->status = 'completed';
            $sale->payment_status = $paymentStatus;
            $sale->sale_date = now();
            $sale->subtotal = $subtotal;
            $sale->discount = $saleDiscount;
            $sale->tax = $saleTax;
            $sale->total = $total;
            $sale->paid_amount = $totalPaid;
            $sale->change_amount = $changeAmount;
            $sale->price_list_id = $priceListId;
            $sale->hold_status = 'none';
            $sale->refund_status = 'none';
            $sale->refunded_amount = 0;
            $sale->notes = $data['notes'] ?? null;
            $sale->table_id = $data['table_id'] ?? null;
            $sale->appointment_id = $data['appointment_id'] ?? null;
            $sale->save();

            // --- Create SaleItems (with snapshot) ---
            foreach ($itemData as $item) {
                $saleItem = new SaleItem;
                $saleItem->sale_id = $sale->id;
                $saleItem->product_id = $item['product_id'];
                $saleItem->variant_id = $item['variant_id'];
                $saleItem->product_name = $item['product_name'];
                $saleItem->sku = $item['sku'];
                $saleItem->quantity = $item['quantity'];
                $saleItem->unit_price = $item['unit_price'];
                $saleItem->original_price = $item['original_price'];
                $saleItem->discount = $item['discount'];
                $saleItem->tax = $item['tax'];
                $saleItem->subtotal = $item['subtotal'];
                $saleItem->total = $item['total'];
                $saleItem->metadata = $item['metadata'] ?? null;
                $saleItem->save();
            }

            // --- Decrease inventory + create movements ---
            // Phase 8: Skip standard deduction for recipe-linked products (8A)
            $recipeService = null;
            $recipeAutoDeduct = $this->moduleService->isFeatureEnabled($tenantId, 'recipes.auto_deduct');
            if ($recipeAutoDeduct) {
                $recipeService = app(RecipeService::class);
            }

            foreach ($data['items'] as $item) {
                $product = $products->get($item['product_id']);

                // Skip standard deduction if product has a recipe and auto-deduct is enabled
                if ($recipeService && $recipeService->hasRecipe($product->id)) {
                    continue;
                }

                $this->inventoryService->decrease(
                    $store,
                    $product,
                    $item['quantity'],
                    'sale',
                    $sale,
                    "Sale {$sale->sale_number}",
                );
            }

            // Phase 8: Recipe ingredient deduction (8A)
            if ($recipeAutoDeduct) {
                $recipeService->deductIngredientsForSale($sale->fresh(['items']), $store);
            }

            // --- Create Payments (via PaymentService for idempotency) ---
            $createdPayments = $this->paymentService->createForCheckout($sale, $data['payments'], $tenantId);

            // --- Recalculate paid amount and payment status based on actual payment statuses ---
            $actualPaid = 0;
            foreach ($createdPayments as $payment) {
                if ($payment->status === 'success') {
                    $actualPaid += (float) $payment->amount;
                }
            }

            $sale->paid_amount = $actualPaid;
            if ($actualPaid >= $total) {
                $sale->payment_status = 'paid';
                $sale->change_amount = $actualPaid - $total;
            } elseif ($actualPaid > 0) {
                $sale->payment_status = 'partial';
                $sale->change_amount = 0;
            } else {
                $sale->payment_status = 'unpaid';
                $sale->change_amount = 0;
            }
            $sale->save();

            // --- Customer credit debit (only for unpaid portion) ---
            if ($customerId && $this->moduleService->isFeatureEnabled($tenantId, 'sales.customer_credit')) {
                $outstanding = $total - $actualPaid;
                if ($outstanding > 0) {
                    $customerForDebit = Customer::withoutTenantScope()->find($customerId);
                    $this->creditService->addDebit($customerForDebit, $outstanding, 'sale', $sale->id);
                }
            }

            // --- Loyalty points earning ---
            if ($customerId && $this->moduleService->isFeatureEnabled($tenantId, 'customers.loyalty_points')) {
                $customerForLoyalty = Customer::withoutTenantScope()->find($customerId);
                $this->loyaltyService->earnPoints($customerForLoyalty, $actualPaid, $sale->id);
            }

            // --- Phase 8: Post-checkout hooks ---
            $this->runPostCheckoutHooks($sale, $data, $tenantId);

            return $sale->fresh(['items.product', 'items.variant', 'payments', 'store', 'cashier', 'customer', 'refunds', 'table', 'appointment']);
        });
    }

    /**
     * Phase 8: Post-checkout hooks for business-specific modules.
     * Called within the checkout transaction after all core logic completes.
     */
    private function runPostCheckoutHooks(Sale $sale, array $data, int $tenantId): void
    {
        // Table linking (8A)
        if (!empty($data['table_id']) && $this->moduleService->isModuleEnabled($tenantId, 'tables')) {
            app(TableService::class)->linkSaleToTable($sale, $data['table_id']);
        }

        // KOT generation (8A)
        if ($this->moduleService->isModuleEnabled($tenantId, 'kitchen')) {
            app(KotService::class)->generateFromSale($sale);
        }

        // Appointment linking (8C)
        if (!empty($data['appointment_id']) && $this->moduleService->isModuleEnabled($tenantId, 'appointments')) {
            app(AppointmentService::class)->linkSaleToAppointment($sale, $data['appointment_id']);
        }

        // Promotion usage recording (8B)
        if (!empty($data['promotion_ids']) && $this->moduleService->isModuleEnabled($tenantId, 'promotions')) {
            app(PromotionService::class)->recordUsage($sale, $data['promotion_ids']);
        }

        // Loyalty redemption (8B) — separate from existing loyalty earning
        if (!empty($data['loyalty_redeem_points']) && $customerId = $sale->customer_id) {
            app(LoyaltyProgramService::class)->redeemPoints(
                $customerId,
                $data['loyalty_redeem_points'],
                $sale->id
            );
        }
    }

    /**
     * Cancel a completed sale — restores inventory via InventoryService::increase().
     * Creates sale_return movements. Sale status → cancelled.
     */
    public function cancel(Sale $sale): Sale
    {
        if ($sale->status !== 'completed') {
            throw new \DomainException('Only completed sales can be cancelled');
        }

        return DB::transaction(function () use ($sale) {
            $sale->load(['items.product', 'store']);

            // Refund all successful payments
            $this->paymentService->refundPayments($sale, $sale->tenant_id);

            // Restore inventory for each item
            foreach ($sale->items as $item) {
                $this->inventoryService->increase(
                    $sale->store,
                    $item->product,
                    $item->quantity,
                    'sale_return',
                    $sale,
                    "Cancel sale {$sale->sale_number}",
                );
            }

            $sale->status = 'cancelled';
            $sale->save();

            return $sale->fresh(['items.product', 'items.variant', 'payments', 'store', 'cashier', 'customer', 'refunds']);
        });
    }

    /**
     * Generate a unique sale number: INV-YYYYMMDD-XXXX
     */
    private function generateSaleNumber(int $tenantId): string
    {
        $date = now()->format('Ymd');
        $prefix = "INV-{$date}-";

        $last = Sale::withoutTenantScope()
            ->where('tenant_id', $tenantId)
            ->where('sale_number', 'like', "{$prefix}%")
            ->orderBy('id', 'desc')
            ->first();

        $seq = 1;
        if ($last) {
            $parts = explode('-', $last->sale_number);
            $seq = (int) end($parts) + 1;
        }

        return $prefix . str_pad((string) $seq, 4, '0', STR_PAD_LEFT);
    }
}
