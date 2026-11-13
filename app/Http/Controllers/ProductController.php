<?php

namespace App\Http\Controllers;

use App\Services\FatherStockService;
use Illuminate\Http\JsonResponse;

class ProductController extends Controller
{
    public function __construct(private FatherStockService $shop)
    {
    }

    public function show(int $id): JsonResponse
    {
        $product = $this->shop->getProduct($id);

        if ($product === null) {
            return response()->json(['message' => 'Product not found'], 404);
        }

        return response()->json(['data' => $product]);
    }
}