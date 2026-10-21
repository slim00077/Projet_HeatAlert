<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Activity timeline of a response case. Only published entries are visible to residents.
     */
    public function up(): void
    {
        Schema::create('response_updates', function (Blueprint $table) {
            $table->id();
            $table->foreignId('response_case_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('kind', 30)->default('note');
            $table->text('body');
            $table->json('meta')->nullable();
            $table->boolean('is_public')->default(false);
            $table->timestamp('published_at')->nullable();
            $table->timestamps();

            $table->index(['response_case_id', 'is_public']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('response_updates');
    }
};
