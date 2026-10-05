<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('resident_reports', function (Blueprint $table) {
            $table->id();
            $table->foreignId('residence_id')->constrained('residences')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('title');
            $table->text('description');
            $table->string('status');
            $table->date('reported_at');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('resident_reports');
    }
};
