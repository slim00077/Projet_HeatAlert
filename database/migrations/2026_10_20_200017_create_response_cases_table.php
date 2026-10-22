<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Additive only: response cases group existing resident reports without changing them.
     */
    public function up(): void
    {
        Schema::create('response_cases', function (Blueprint $table) {
            $table->id();
            $table->foreignId('residence_id')->constrained()->cascadeOnDelete();
            $table->string('title');
            $table->text('summary')->nullable();
            $table->string('stage', 20)->default('reported');
            $table->string('priority', 20)->default('medium');
            $table->date('target_date')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('stage_changed_at')->nullable();
            $table->timestamp('resolved_at')->nullable();
            $table->timestamps();

            $table->index(['residence_id', 'stage']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('response_cases');
    }
};
