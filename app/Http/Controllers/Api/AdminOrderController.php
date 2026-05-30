<?php

namespace App\Http\Controllers\Api;

use App\Http\Resources\OrderResource;
use App\Models\Order;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AdminOrderController extends BaseApiController
{
    public function index(Request $request): JsonResponse
    {
        $query = Order::with(['items', 'company']);

        if ($request->filled('status')) {
            $query->where('status', $request->input('status'));
        }

        if ($request->filled('payment_status')) {
            $query->where('payment_status', $request->input('payment_status'));
        }

        if ($request->filled('company_id')) {
            $query->where('company_id', $request->input('company_id'));
        }

        if ($request->filled('search')) {
            $search = $request->input('search');
            $query->where(function ($q) use ($search) {
                $q->where('order_number', 'LIKE', '%'.$search.'%')
                    ->orWhereHas('company', function ($companyQuery) use ($search) {
                        $companyQuery->where('name', 'LIKE', '%'.$search.'%');
                    });
            });
        }

        $orders = $query->orderBy('created_at', 'desc')
            ->paginate((int) $request->input('per_page', 15));

        return $this->paginatedResponse(OrderResource::collection($orders));
    }
}
