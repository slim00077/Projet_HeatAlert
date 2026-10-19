<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('outage_reports', function (Blueprint $table) {
            $table->id();
            $table->foreignId('power_outage_id')->constrained()->cascadeOnDelete();
            $table->string('reporter_name');
            $table->string('email');
            $table->string('status')->default('pending');
            $table->text('description');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('outage_reports');
    }
};
