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
        Schema::table('advice', function (Blueprint $table) {
            $table->foreignId('submitted_by')
                ->nullable()
                ->after('advice_category_id')
                ->constrained('users')
                ->nullOnDelete();
            $table->index(['submitted_by', 'created_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('advice', function (Blueprint $table) {
            $table->dropIndex(['submitted_by', 'created_at']);
            $table->dropConstrainedForeignId('submitted_by');
        });
    }
};
