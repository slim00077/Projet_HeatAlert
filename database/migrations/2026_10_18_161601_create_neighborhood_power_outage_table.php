<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('neighborhood_power_outage', function (Blueprint $table) {
            $table->foreignId('power_outage_id')->constrained()->cascadeOnDelete();
            $table->foreignId('neighborhood_id')->constrained()->cascadeOnDelete();
            $table->primary(['power_outage_id', 'neighborhood_id']);
            $table->index(['neighborhood_id', 'power_outage_id']);
        });

        Schema::table('power_outages', function (Blueprint $table) {
            $table->string('area')->nullable()->change();
        });

        DB::table('neighborhood_power_outage')->insertUsing(
            ['power_outage_id', 'neighborhood_id'],
            DB::table('power_outages as outages')
                ->join('neighborhoods', 'neighborhoods.name', '=', 'outages.area')
                ->select('outages.id', 'neighborhoods.id'),
        );
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        DB::table('power_outages')
            ->whereNull('area')
            ->orderBy('id')
            ->chunkById(100, function ($outages): void {
                foreach ($outages as $outage) {
                    $area = DB::table('neighborhood_power_outage')
                        ->join('neighborhoods', 'neighborhoods.id', '=', 'neighborhood_power_outage.neighborhood_id')
                        ->where('neighborhood_power_outage.power_outage_id', $outage->id)
                        ->pluck('neighborhoods.name')
                        ->implode(', ');

                    DB::table('power_outages')
                        ->where('id', $outage->id)
                        ->update(['area' => $area !== '' ? $area : 'Unspecified']);
                }
            });

        Schema::dropIfExists('neighborhood_power_outage');

        Schema::table('power_outages', function (Blueprint $table) {
            $table->string('area')->nullable(false)->change();
        });
    }
};
