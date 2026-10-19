<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('heat_alerts', function (Blueprint $table) {
            $table->decimal('latitude', 10, 7)->nullable()->after('temperature');
            $table->decimal('longitude', 10, 7)->nullable()->after('latitude');
            $table->foreignId('created_by')->nullable()->after('notified_at')->constrained('users')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('heat_alerts', function (Blueprint $table) {
            $table->dropConstrainedForeignId('created_by');
            $table->dropColumn(['latitude', 'longitude']);
        });
    }
};
