<?php

namespace App\Http\Controllers;

use App\Services\FatherStockService;
use Illuminate\Http\JsonResponse;

class CategoryController extends Controller
{
    public function __construct(private FatherStockService $shop) {}

    public function index(): JsonResponse
    {
        $categories = $this->shop->getCategories();

        if (empty($categories)) {
            return response()->json(['message' => 'Categories not found'], 404);
        }

        return response()->json(['data' => $categories]);
    }

    public function products(int $id): JsonResponse
    {
        $products = $this->shop->getCategoryProducts($id);

        if ($products === null) {
            return response()->json(['message' => 'Category not found'], 404);
        }

        return response()->json(['data' => $products]);
    }
}
