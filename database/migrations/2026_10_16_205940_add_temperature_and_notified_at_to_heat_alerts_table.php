<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('heat_alerts', function (Blueprint $table) {
            $table->decimal('temperature', 4, 1)->nullable()->after('severity_level');
            $table->timestamp('notified_at')->nullable()->after('end_date');
        });
    }

    public function down(): void
    {
        Schema::table('heat_alerts', function (Blueprint $table) {
            $table->dropColumn(['temperature', 'notified_at']);
        });
    }
};
