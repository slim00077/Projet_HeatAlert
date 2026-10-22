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
        Schema::table('advice', function (Blueprint $table) {
            $table->string('risk_level')->default('medium');
            $table->boolean('is_approved')->default(false);
        });

        $seededAdviceRiskLevels = [
            'Drink water regularly' => 'low',
            'Choose water during hot weather' => 'low',
            'Reduce electricity use during peak heat' => 'medium',
            'Use electrical equipment safely' => 'medium',
            'Prepare a heatwave kit' => 'medium',
            'Keep your phone and power bank charged' => 'medium',
            'Keep indoor spaces cooler' => 'low',
            'Limit direct sun exposure' => 'low',
            'Check on people at higher risk' => 'medium',
            'Know when to seek urgent help' => 'high',
        ];

        foreach ($seededAdviceRiskLevels as $title => $riskLevel) {
            DB::table('advice')
                ->where('title', $title)
                ->update(['risk_level' => $riskLevel, 'is_approved' => true]);
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('advice', function (Blueprint $table) {
            $table->dropColumn(['risk_level', 'is_approved']);
        });
    }
};
