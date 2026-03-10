<?php

namespace App\Http\Controllers\Api;

use App\Http\Resources\ReviewConfigResource;
use App\Models\ReviewChannel;
use App\Models\ReviewConfig;
use App\Models\ReviewQuestion;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class ReviewConfigController extends BaseApiController
{
    public function show(): JsonResponse
    {
        $query = ReviewConfig::with(['questions', 'channels']);
        $this->scopeByCompany($query);
        $config = $query->first();

        if (!$config) {
            return $this->successResponse(null);
        }

        return $this->resourceResponse(new ReviewConfigResource($config));
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'questions' => 'sometimes|array|max:10',
            'questions.*.text' => 'required_with:questions|string|max:255',
            'questions.*.orderIndex' => 'sometimes|integer|min:0',
            'channels' => 'sometimes|array|max:20',
            'channels.*.name' => 'required_with:channels|string|max:100',
            'isActive' => 'sometimes|boolean',
        ]);

        $companyId = auth()->user()->company_id;

        $config = ReviewConfig::firstOrCreate(
            ['company_id' => $companyId],
            ['token' => Str::random(32)]
        );

        if (isset($validated['isActive'])) {
            $config->update(['is_active' => $validated['isActive']]);
        }

        if (isset($validated['questions'])) {
            $config->questions()->delete();
            foreach ($validated['questions'] as $index => $q) {
                ReviewQuestion::create([
                    'review_config_id' => $config->id,
                    'text' => $q['text'],
                    'order_index' => $q['orderIndex'] ?? $index,
                ]);
            }
        }

        if (isset($validated['channels'])) {
            $config->channels()->delete();
            foreach ($validated['channels'] as $c) {
                ReviewChannel::create([
                    'review_config_id' => $config->id,
                    'name' => $c['name'],
                ]);
            }
        }

        $config->load(['questions', 'channels']);

        return $this->resourceResponse(new ReviewConfigResource($config));
    }

    public function regenerateToken(): JsonResponse
    {
        $companyId = auth()->user()->company_id;

        $config = ReviewConfig::firstOrCreate(
            ['company_id' => $companyId],
            ['token' => Str::random(32)]
        );

        $config->update(['token' => Str::random(32)]);
        $config->load(['questions', 'channels']);

        return $this->resourceResponse(new ReviewConfigResource($config));
    }
}
