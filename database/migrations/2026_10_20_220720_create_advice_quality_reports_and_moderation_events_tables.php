<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('advice_quality_reports', function (Blueprint $table) {
            $table->id();
            $table->foreignId('advice_id')->constrained()->cascadeOnDelete();
            $table->foreignId('analyzed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('analysis_status', 24);
            $table->string('analyzed_model', 100)->nullable();
            $table->unsignedTinyInteger('score')->nullable();
            $table->text('summary')->nullable();
            $table->json('findings');
            $table->string('source_status', 32);
            $table->json('advice_snapshot');
            $table->timestamps();
            $table->index(['advice_id', 'created_at']);
        });

        Schema::create('advice_moderation_events', function (Blueprint $table) {
            $table->id();
            $table->foreignId('advice_id')->constrained()->cascadeOnDelete();
            $table->foreignId('quality_report_id')->nullable()->constrained('advice_quality_reports')->nullOnDelete();
            $table->foreignId('actor_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('action', 40);
            $table->text('note')->nullable();
            $table->json('details')->nullable();
            $table->timestamps();
            $table->index(['advice_id', 'created_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('advice_moderation_events');
        Schema::dropIfExists('advice_quality_reports');
    }
};
