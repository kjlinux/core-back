<?php

namespace App\Http\Controllers\Api;

use App\Http\Requests\Marketplace\StoreProductRequest;
use App\Http\Requests\Marketplace\UpdateProductRequest;
use App\Http\Requests\Marketplace\UpdateStockRequest;
use App\Http\Resources\ProductResource;
use App\Models\Product;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ProductController extends BaseApiController
{
    public function index(Request $request): JsonResponse
    {
        $query = Product::query();

        if ($request->filled('category')) {
            $query->where('category', $request->category);
        }

        if ($request->has('is_active')) {
            $query->where('is_active', filter_var($request->is_active, FILTER_VALIDATE_BOOLEAN));
        }

        if ($request->filled('search')) {
            $query->where('name', 'LIKE', '%' . $request->search . '%');
        }

        $products = $query->paginate($request->input('per_page', 15));

        return $this->paginatedResponse(ProductResource::collection($products));
    }

    public function show(string $id): JsonResponse
    {
        $product = Product::findOrFail($id);

        return $this->resourceResponse(new ProductResource($product));
    }

    public function store(StoreProductRequest $request): JsonResponse
    {
        $product = Product::create($request->validated());

        return $this->resourceResponse(new ProductResource($product), '', 201);
    }

    public function update(UpdateProductRequest $request, string $id): JsonResponse
    {
        $product = Product::findOrFail($id);
        $product->update($request->validated());

        return $this->resourceResponse(new ProductResource($product));
    }

    public function updateStock(UpdateStockRequest $request, string $id): JsonResponse
    {
        $product = Product::findOrFail($id);
        $product->update(['stock_quantity' => $request->validated()['stock_quantity']]);

        return $this->resourceResponse(new ProductResource($product));
    }

    public function destroy(string $id): JsonResponse
    {
        $product = Product::findOrFail($id);
        $product->delete();

        return $this->noContentResponse();
    }
}
