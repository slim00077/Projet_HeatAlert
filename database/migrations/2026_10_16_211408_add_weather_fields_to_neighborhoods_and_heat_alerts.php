<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('neighborhoods', function (Blueprint $table) {
            $table->decimal('current_temperature', 4, 1)->nullable()->after('vulnerable_population');
            $table->decimal('forecast_max_temperature', 4, 1)->nullable()->after('current_temperature');
            $table->timestamp('weather_updated_at')->nullable()->after('forecast_max_temperature');
        });

        Schema::table('heat_alerts', function (Blueprint $table) {
            $table->string('source', 10)->default('manual')->after('severity_level');
        });
    }

    public function down(): void
    {
        Schema::table('neighborhoods', function (Blueprint $table) {
            $table->dropColumn(['current_temperature', 'forecast_max_temperature', 'weather_updated_at']);
        });

        Schema::table('heat_alerts', function (Blueprint $table) {
            $table->dropColumn('source');
        });
    }
};
