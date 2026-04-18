<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('analysis_runs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('github_connection_id')->nullable()->constrained()->nullOnDelete();
            $table->string('github_username')->index();
            $table->string('analysis_mode')->default('public_only');
            $table->string('status')->default('pending');
            $table->date('source_window_start')->nullable();
            $table->date('source_window_end')->nullable();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->decimal('overall_score', 5, 2)->nullable();
            $table->decimal('confidence', 5, 2)->nullable();
            $table->string('momentum_label')->nullable();
            $table->json('strengths')->nullable();
            $table->json('weaknesses')->nullable();
            $table->json('weekly_plan')->nullable();
            $table->json('context')->nullable();
            $table->string('ai_status')->default('not_requested');
            $table->string('ai_model')->nullable();
            $table->string('ai_snapshot_hash', 64)->nullable()->index();
            $table->timestamp('ai_enhanced_at')->nullable();
            $table->text('ai_error')->nullable();
            $table->json('ai_enhancement')->nullable();
            $table->text('summary')->nullable();
            $table->text('evidence_summary')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('analysis_runs');
    }
};
