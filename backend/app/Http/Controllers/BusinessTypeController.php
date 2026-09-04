<?php

namespace App\Http\Controllers;

use App\Models\BusinessType;
use Illuminate\Http\JsonResponse;

class BusinessTypeController extends Controller
{
    public function index(): JsonResponse
    {
        $types = BusinessType::where('is_active', true)
            ->orderBy('sort_order')
            ->get(['id', 'slug', 'name', 'description', 'icon']);

        return response()->json([
            'data' => $types,
        ]);
    }
}
