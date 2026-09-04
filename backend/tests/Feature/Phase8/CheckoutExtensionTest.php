<?php

namespace Tests\Feature\Phase8;

use App\Models\Inventory;
use App\Models\Modifier;
use App\Models\ModifierOption;
use App\Models\ProductModifier;
use App\Models\Recipe;
use App\Models\RecipeIngredient;
use App\Models\RestaurantTable;
use App\Models\TableArea;
use App\Services\SaleService;
use Illuminate\Support\Facades\Auth;
use Tests\Feature\Phase4TestHelper;

class CheckoutExtensionTest extends Phase4TestHelper
{
    public function test_checkout_links_sale_to_table(): void
    {
        $this->setupPhase4();
        $this->setInventory($this->store, $this->product, 100);
        Auth::login($this->cashier);

        $area = TableArea::create([
            'store_id' => $this->store->id,
            'name' => 'Main Area',
        ]);
        $table = RestaurantTable::create([
            'store_id' => $this->store->id,
            'table_area_id' => $area->id,
            'name' => 'T1',
            'code' => 'T1-' . uniqid(),
            'capacity' => 4,
            'status' => 'available',
        ]);

        $service = app(SaleService::class);
        $sale = $service->checkout($this->checkoutData([
            ['product_id' => $this->product->id, 'quantity' => 1],
        ], [], ['table_id' => $table->id]));

        $this->assertEquals($table->id, $sale->table_id);
        $table->refresh();
        $this->assertEquals('occupied', $table->status);
    }

    public function test_checkout_applies_modifier_price_deltas(): void
    {
        $this->setupPhase4();
        $this->setInventory($this->store, $this->product, 100);
        Auth::login($this->cashier);

        $modifier = Modifier::create([
            'name' => 'Extra Shot',
            'type' => 'single',
            'is_required' => false,
        ]);
        $option = ModifierOption::create([
            'modifier_id' => $modifier->id,
            'name' => 'Double',
            'price_delta' => 3000,
        ]);
        ProductModifier::create([
            'product_id' => $this->product->id,
            'modifier_id' => $modifier->id,
        ]);

        $service = app(SaleService::class);
        $sale = $service->checkout($this->checkoutData([
            [
                'product_id' => $this->product->id,
                'quantity' => 1,
                'modifiers' => [
                    ['modifier_id' => $modifier->id, 'option_ids' => [$option->id]],
                ],
            ],
        ]));

        $item = $sale->items->first();
        $this->assertEquals(13000, (float) $item->unit_price);
        $this->assertNotNull($item->metadata);
        $this->assertArrayHasKey('modifiers', $item->metadata);
    }

    public function test_checkout_deduction_uses_recipe(): void
    {
        $this->setupPhase4();
        $this->setInventory($this->store, $this->product, 100);
        Auth::login($this->cashier);

        $ingredient = new \App\Models\Product;
        $ingredient->tenant_id = $this->tenant->id;
        $ingredient->category_id = $this->cat->id;
        $ingredient->name = 'Coffee Beans';
        $ingredient->sku = 'BEANS-' . uniqid();
        $ingredient->barcode = (string) uniqid();
        $ingredient->cost_price = 2000;
        $ingredient->selling_price = 0;
        $ingredient->unit = 'gr';
        $ingredient->has_variants = false;
        $ingredient->save();

        $this->setInventory($this->store, $ingredient, 1000);

        $recipe = Recipe::create([
            'product_id' => $this->product->id,
        ]);
        $ri = new RecipeIngredient;
        $ri->recipe_id = $recipe->id;
        $ri->ingredient_product_id = $ingredient->id;
        $ri->quantity = 20;
        $ri->save();

        $service = app(SaleService::class);
        $sale = $service->checkout($this->checkoutData([
            ['product_id' => $this->product->id, 'quantity' => 2],
        ]));

        $inv = Inventory::withoutTenantScope()
            ->where('tenant_id', $this->tenant->id)
            ->where('store_id', $this->store->id)
            ->where('product_id', $ingredient->id)
            ->first();
        $this->assertEquals(960, (float) $inv->quantity);
    }

    public function test_checkout_insufficient_recipe_ingredient_fails(): void
    {
        $this->setupPhase4();
        $this->setInventory($this->store, $this->product, 100);
        Auth::login($this->cashier);

        $ingredient = new \App\Models\Product;
        $ingredient->tenant_id = $this->tenant->id;
        $ingredient->category_id = $this->cat->id;
        $ingredient->name = 'Milk';
        $ingredient->sku = 'MILK-' . uniqid();
        $ingredient->barcode = (string) uniqid();
        $ingredient->cost_price = 1000;
        $ingredient->selling_price = 0;
        $ingredient->unit = 'ml';
        $ingredient->has_variants = false;
        $ingredient->save();

        $this->setInventory($this->store, $ingredient, 10);

        $recipe = Recipe::create([
            'product_id' => $this->product->id,
        ]);
        $ri = new RecipeIngredient;
        $ri->recipe_id = $recipe->id;
        $ri->ingredient_product_id = $ingredient->id;
        $ri->quantity = 50;
        $ri->save();

        $this->expectException(\DomainException::class);

        $service = app(SaleService::class);
        $service->checkout($this->checkoutData([
            ['product_id' => $this->product->id, 'quantity' => 1],
        ]));
    }
}
