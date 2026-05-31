<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('subscription_reminders_sent', function (Blueprint $table) {
            $table->id();
            $table->uuid('company_id');
            $table->unsignedTinyInteger('days_left');
            $table->date('sent_on');
            $table->timestamps();

            $table->foreign('company_id')->references('id')->on('companies')->cascadeOnDelete();
            $table->unique(['company_id', 'days_left', 'sent_on']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('subscription_reminders_sent');
    }
};
