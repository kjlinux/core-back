<?php

namespace App\Http\Controllers\Api;

use App\Http\Resources\FeelbackEntryResource;
use App\Models\FeelbackEntry;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class FeelbackEntryController extends BaseApiController
{
    public function index(Request $request): JsonResponse
    {
        $query = FeelbackEntry::with(['device', 'site']);

        if ($request->filled('site_id')) {
            $query->where('site_id', $request->site_id);
        }

        if ($request->filled('level')) {
            $query->where('level', $request->level);
        }

        if ($request->filled('start_date')) {
            $query->whereDate('created_at', '>=', $request->start_date);
        }

        if ($request->filled('end_date')) {
            $query->whereDate('created_at', '<=', $request->end_date);
        }

        $entries = $query->orderBy('created_at', 'desc')
            ->paginate($request->input('per_page', 15));

        return $this->paginatedResponse(FeelbackEntryResource::collection($entries));
    }
}
