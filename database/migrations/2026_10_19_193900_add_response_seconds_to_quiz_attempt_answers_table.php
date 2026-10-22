<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('quiz_attempt_answers', function (Blueprint $table) {
            $table->unsignedInteger('response_seconds')->default(0)->after('is_correct');
        });
    }

    public function down(): void
    {
        Schema::table('quiz_attempt_answers', function (Blueprint $table) {
            $table->dropColumn('response_seconds');
        });
    }
};
