<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('advice_conversations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('title', 120);
            $table->timestamps();
            $table->index(['user_id', 'updated_at']);
        });

        Schema::create('advice_messages', function (Blueprint $table) {
            $table->id();
            $table->foreignId('advice_conversation_id')->constrained()->cascadeOnDelete();
            $table->string('role', 16);
            $table->longText('content');
            $table->json('advice_ids')->nullable();
            $table->timestamps();
            $table->index(['advice_conversation_id', 'created_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('advice_messages');
        Schema::dropIfExists('advice_conversations');
    }
};
