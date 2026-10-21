<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * A resident's answer to "does the issue appear resolved?" for their own report in a case.
     */
    public function up(): void
    {
        Schema::create('response_confirmations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('response_case_id')->constrained()->cascadeOnDelete();
            $table->foreignId('resident_report_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->boolean('is_resolved');
            $table->text('comment')->nullable();
            $table->timestamps();

            $table->unique(['response_case_id', 'resident_report_id'], 'response_confirmations_case_report_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('response_confirmations');
    }
};
