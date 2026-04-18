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
            $table->foreignId('github_connection_id')->constrained()->cascadeOnDelete();
            $table->string('status')->default('pending');
            $table->date('source_window_start')->nullable();
            $table->date('source_window_end')->nullable();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->decimal('overall_score', 5, 2)->nullable();
            $table->decimal('confidence', 5, 2)->nullable();
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
