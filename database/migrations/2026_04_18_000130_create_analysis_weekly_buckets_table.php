<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('analysis_weekly_buckets', function (Blueprint $table) {
            $table->id();
            $table->foreignId('analysis_run_id')->constrained()->cascadeOnDelete();
            $table->date('week_start');
            $table->date('week_end');
            $table->unsignedTinyInteger('active_days')->default(0);
            $table->unsignedInteger('commits_count')->default(0);
            $table->unsignedInteger('pull_requests_count')->default(0);
            $table->unsignedInteger('issues_count')->default(0);
            $table->unsignedInteger('reviews_count')->default(0);
            $table->string('momentum_label')->nullable();
            $table->string('trend_direction')->nullable();
            $table->json('evidence')->nullable();
            $table->timestamps();

            $table->unique(['analysis_run_id', 'week_start']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('analysis_weekly_buckets');
    }
};
