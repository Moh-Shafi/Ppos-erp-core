<?php

namespace App\Http\Controllers;

use App\Models\Customer;
use App\Models\Inventory;
use App\Models\Payment;
use App\Models\Product;
use App\Models\Sale;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;

class IntegrationApiController extends Controller
{
    protected function getTenantId(Request $request): int
    {
        return $request->attributes->get('tenant_id');
    }

    public function listSales(Request $request)
    {
        $query = Sale::withoutTenantScope()
            ->where('tenant_id', $this->getTenantId($request))
            ->with(['items', 'payments']);

        if ($request->has('store_id')) {
            $query->where('store_id', $request->get('store_id'));
        }

        if ($request->has('status')) {
            $query->where('status', $request->get('status'));
        }

        if ($request->has('date_from')) {
            $query->where('created_at', '>=', $request->get('date_from'));
        }

        if ($request->has('date_to')) {
            $query->where('created_at', '<=', $request->get('date_to'));
        }

        $sales = $query->orderByDesc('created_at')->paginate($request->get('per_page', 20));

        return response()->json($sales);
    }

    public function showSale(Request $request, int $id)
    {
        $sale = Sale::withoutTenantScope()
            ->where('tenant_id', $this->getTenantId($request))
            ->with(['items', 'payments'])
            ->find($id);

        if (!$sale) {
            return response()->json(['message' => 'Sale not found'], 404);
        }

        return response()->json(['data' => $sale]);
    }

    public function listProducts(Request $request)
    {
        $query = Product::withoutTenantScope()->where('tenant_id', $this->getTenantId($request));

        if ($request->has('category_id')) {
            $query->where('category_id', $request->get('category_id'));
        }

        $products = $query->orderByDesc('created_at')->paginate($request->get('per_page', 20));

        return response()->json($products);
    }

    public function showProduct(Request $request, int $id)
    {
        $product = Product::withoutTenantScope()
            ->where('tenant_id', $this->getTenantId($request))
            ->find($id);

        if (!$product) {
            return response()->json(['message' => 'Product not found'], 404);
        }

        return response()->json(['data' => $product]);
    }

    public function listInventory(Request $request)
    {
        $request->validate(['store_id' => 'required|integer']);

        $inventory = Inventory::withoutTenantScope()
            ->where('tenant_id', $this->getTenantId($request))
            ->where('store_id', $request->get('store_id'))
            ->with('product:id,name,sku')
            ->orderByDesc('created_at')
            ->paginate($request->get('per_page', 20));

        return response()->json($inventory);
    }

    public function listCustomers(Request $request)
    {
        $customers = Customer::withoutTenantScope()
            ->where('tenant_id', $this->getTenantId($request))
            ->orderByDesc('created_at')
            ->paginate($request->get('per_page', 20));

        return response()->json($customers);
    }

    public function showCustomer(Request $request, int $id)
    {
        $customer = Customer::withoutTenantScope()
            ->where('tenant_id', $this->getTenantId($request))
            ->find($id);

        if (!$customer) {
            return response()->json(['message' => 'Customer not found'], 404);
        }

        return response()->json(['data' => $customer]);
    }

    public function listPayments(Request $request)
    {
        $payments = Payment::withoutTenantScope()
            ->where('tenant_id', $this->getTenantId($request))
            ->with('sale:id,sale_number,total')
            ->orderByDesc('created_at')
            ->paginate($request->get('per_page', 20));

        return response()->json($payments);
    }
}
