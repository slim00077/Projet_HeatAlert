<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('outage_reports', function (Blueprint $table) {
            $table->foreignId('user_id')->nullable()->after('power_outage_id')->constrained()->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('outage_reports', function (Blueprint $table) {
            $table->dropConstrainedForeignId('user_id');
        });
    }
};
