<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\FinancingProduct;
use Illuminate\Http\JsonResponse;

class FinancingProductController extends Controller
{
    public function index(): JsonResponse
    {
        $products = FinancingProduct::where('status', 'active')->get();

        return response()->json([
            'success' => true,
            'data' => $products,
        ]);
    }
}

