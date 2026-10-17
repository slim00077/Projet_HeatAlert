<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('neighborhoods', function (Blueprint $table) {
            $table->decimal('latitude', 10, 7)->nullable()->after('description');
            $table->decimal('longitude', 10, 7)->nullable()->after('latitude');
            $table->unsignedInteger('population')->nullable()->after('longitude');
            $table->unsignedInteger('vulnerable_population')->nullable()->after('population');
        });
    }

    public function down(): void
    {
        Schema::table('neighborhoods', function (Blueprint $table) {
            $table->dropColumn(['latitude', 'longitude', 'population', 'vulnerable_population']);
        });
    }
};
