<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('skill_signals', function (Blueprint $table) {
            $table->id();
            $table->foreignId('analysis_run_id')->constrained()->cascadeOnDelete();
            $table->string('skill_key')->index();
            $table->decimal('score', 5, 2)->nullable();
            $table->decimal('confidence', 5, 2)->nullable();
            $table->json('evidence')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->unique(['analysis_run_id', 'skill_key']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('skill_signals');
    }
};
