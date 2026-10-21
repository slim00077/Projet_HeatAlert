<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Links a response case to the resident reports it handles; the reports themselves are never modified.
     */
    public function up(): void
    {
        Schema::create('response_case_report', function (Blueprint $table) {
            $table->id();
            $table->foreignId('response_case_id')->constrained()->cascadeOnDelete();
            $table->foreignId('resident_report_id')->constrained()->cascadeOnDelete();
            $table->foreignId('linked_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('source', 20)->default('manual');
            $table->timestamps();

            $table->unique(['response_case_id', 'resident_report_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('response_case_report');
    }
};
