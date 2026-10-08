<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('power_outages', function (Blueprint $table) {
            $table->id();
            $table->string('title');
            $table->string('area');
            $table->string('status')->default('planned');
            $table->string('severity')->default('moderate');
            $table->timestamp('started_at')->nullable();
            $table->timestamp('estimated_end_at')->nullable();
            $table->text('description')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('power_outages');
    }
};
