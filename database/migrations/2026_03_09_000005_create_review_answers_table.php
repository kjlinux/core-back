<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('review_answers', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('review_submission_id')->constrained('review_submissions')->cascadeOnDelete();
            $table->foreignUuid('review_question_id')->constrained('review_questions')->cascadeOnDelete();
            $table->unsignedTinyInteger('stars');
            $table->timestamps();

            $table->index('review_submission_id');
            $table->index('review_question_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('review_answers');
    }
};
