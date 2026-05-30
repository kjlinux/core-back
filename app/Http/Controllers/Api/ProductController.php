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
            $query->where('name', 'LIKE', '%'.$request->input('search').'%');
        }

        if ($request->filled('stock_status')) {
            $query->where(function ($q) use ($request) {
                match ($request->input('stock_status')) {
                    'out_of_stock' => $q->where('stock_quantity', '<=', 0),
                    'critical' => $q->whereBetween('stock_quantity', [1, 10]),
                    'low' => $q->whereBetween('stock_quantity', [11, 50]),
                    'normal' => $q->where('stock_quantity', '>', 50),
                    default => $q,
                };
            });
        }

        $products = $query->paginate((int) $request->input('per_page', 15));

        return $this->paginatedResponse(ProductResource::collection($products));
    }

    public function show(string $id): JsonResponse
    {
        $product = Product::findOrFail($id);

        return $this->resourceResponse(new ProductResource($product));
    }

    public function store(StoreProductRequest $request): JsonResponse
    {
        $data = $request->validated();
        $data['category'] = $data['category'] ?? 'standard_card';
        $data['customizable'] = $data['customizable'] ?? false;
        $data['min_quantity'] = $data['min_quantity'] ?? 1;
        $data['is_active'] = $data['is_active'] ?? true;
        $product = Product::create($data);

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
