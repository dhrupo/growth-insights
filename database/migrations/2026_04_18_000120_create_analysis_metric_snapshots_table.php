<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('analysis_metric_snapshots', function (Blueprint $table) {
            $table->id();
            $table->foreignId('analysis_run_id')->unique()->constrained()->cascadeOnDelete();
            $table->decimal('consistency_score', 5, 2)->nullable();
            $table->decimal('diversity_score', 5, 2)->nullable();
            $table->decimal('contribution_score', 5, 2)->nullable();
            $table->decimal('collaboration_score', 5, 2)->nullable();
            $table->decimal('testing_score', 5, 2)->nullable();
            $table->decimal('documentation_score', 5, 2)->nullable();
            $table->decimal('momentum_score', 5, 2)->nullable();
            $table->json('score_breakdown')->nullable();
            $table->json('evidence')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('analysis_metric_snapshots');
    }
};
