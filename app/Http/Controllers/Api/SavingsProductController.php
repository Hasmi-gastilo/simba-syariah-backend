<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\SavingsProduct;
use Illuminate\Http\Request;

class SavingsProductController extends Controller
{
    public function index()
    {
        $products = SavingsProduct::where('status', 'active')->get();

        return response()->json([
            'success' => true,
            'message' => 'Daftar produk simpanan berhasil diambil.',
            'data' => $products,
            'meta' => null,
        ]);
    }
}

