<?php

namespace App\Http\Controllers\Api;

use App\Models\ReviewAnswer;
use App\Models\ReviewConfig;
use App\Models\ReviewSubmission;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PublicReviewController extends BaseApiController
{
    public function show(string $token): JsonResponse
    {
        $config = ReviewConfig::with(['questions', 'channels', 'company'])
            ->where('token', $token)
            ->where('is_active', true)
            ->firstOrFail();

        return $this->successResponse([
            'companyName' => $config->company->name,
            'questions' => $config->questions->map(fn ($q) => [
                'id' => (string) $q->id,
                'text' => $q->text,
                'orderIndex' => $q->order_index,
            ]),
            'channels' => $config->channels->map(fn ($c) => [
                'id' => (string) $c->id,
                'name' => $c->name,
            ]),
        ]);
    }

    public function submit(Request $request, string $token): JsonResponse
    {
        $config = ReviewConfig::where('token', $token)
            ->where('is_active', true)
            ->firstOrFail();

        $validated = $request->validate([
            'recommendations' => 'nullable|string|max:2000',
            'channel' => 'nullable|string|max:100',
            'answers' => 'required|array',
            'answers.*.questionId' => 'required|uuid|exists:review_questions,id',
            'answers.*.stars' => 'required|integer|min:1|max:5',
        ]);

        $submission = ReviewSubmission::create([
            'review_config_id' => $config->id,
            'recommendations' => $validated['recommendations'] ?? null,
            'channel' => $validated['channel'] ?? null,
        ]);

        foreach ($validated['answers'] as $answer) {
            ReviewAnswer::create([
                'review_submission_id' => $submission->id,
                'review_question_id' => $answer['questionId'],
                'stars' => $answer['stars'],
            ]);
        }

        return $this->successResponse(null, 'Avis enregistré avec succès', 201);
    }
}
