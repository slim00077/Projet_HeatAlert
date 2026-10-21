<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Additive only: existing reports keep their data and get null category/resolution fields.
     */
    public function up(): void
    {
        Schema::table('resident_reports', function (Blueprint $table) {
            $table->string('category', 40)->nullable()->after('title');
            $table->text('resolution_notes')->nullable()->after('status');
            $table->timestamp('resolved_at')->nullable()->after('resolution_notes');
        });

        // Best available resolution date for reports resolved before this column existed.
        DB::table('resident_reports')
            ->where('status', 'Resolved')
            ->whereNull('resolved_at')
            ->update(['resolved_at' => DB::raw('updated_at')]);
    }

    public function down(): void
    {
        Schema::table('resident_reports', function (Blueprint $table) {
            $table->dropColumn(['category', 'resolution_notes', 'resolved_at']);
        });
    }
};
