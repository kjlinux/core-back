<?php

namespace App\Http\Controllers\Api;

use App\Http\Resources\ReviewSubmissionResource;
use App\Models\ReviewAnswer;
use App\Models\ReviewConfig;
use App\Models\ReviewSubmission;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ReviewStatsController extends BaseApiController
{
    public function index(): JsonResponse
    {
        $query = ReviewConfig::with('questions');
        $this->scopeByCompany($query);
        $config = $query->first();

        if (! $config) {
            return $this->successResponse([
                'totalSubmissions' => 0,
                'averagePerQuestion' => [],
                'channelDistribution' => [],
            ]);
        }

        $totalSubmissions = ReviewSubmission::where('review_config_id', $config->id)->count();

        // Une seule requête GROUP BY pour tous les questions à la fois (évite N+1).
        $avgByQuestion = ReviewAnswer::query()
            ->whereIn('review_question_id', $config->questions->pluck('id'))
            ->whereIn('review_submission_id', function ($q) use ($config) {
                $q->select('id')->from('review_submissions')->where('review_config_id', $config->id);
            })
            ->selectRaw('review_question_id, AVG(stars) as avg_stars')
            ->groupBy('review_question_id')
            ->pluck('avg_stars', 'review_question_id');

        $averagePerQuestion = $config->questions->map(function ($question) use ($avgByQuestion) {
            $avg = $avgByQuestion[$question->id] ?? null;

            return [
                'questionId' => (string) $question->id,
                'text' => $question->text,
                'average' => $avg !== null ? round((float) $avg, 2) : 0,
            ];
        })->values();

        $channelDistribution = ReviewSubmission::where('review_config_id', $config->id)
            ->whereNotNull('channel')
            ->selectRaw('channel, count(*) as count')
            ->groupBy('channel')
            ->orderByDesc('count')
            ->get()
            ->map(fn ($row) => [
                'channel' => $row->channel,
                'count' => $row->count,
            ]);

        return $this->successResponse([
            'totalSubmissions' => $totalSubmissions,
            'averagePerQuestion' => $averagePerQuestion,
            'channelDistribution' => $channelDistribution,
        ]);
    }

    public function submissions(Request $request): JsonResponse
    {
        $query = ReviewConfig::query();
        $this->scopeByCompany($query);
        $config = $query->first();

        if (! $config) {
            return $this->paginatedResponse(ReviewSubmissionResource::collection(
                (new \Illuminate\Pagination\LengthAwarePaginator([], 0, 15))
            ));
        }

        $submissions = ReviewSubmission::with('answers')
            ->where('review_config_id', $config->id)
            ->latest()
            ->paginate($request->input('per_page', 15));

        return $this->paginatedResponse(ReviewSubmissionResource::collection($submissions));
    }
}
