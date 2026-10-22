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
            $table->string('source_url', 2048)->nullable()->after('content');
            $table->string('moderation_status', 32)->default('pending')->after('is_approved')->index();
        });

        DB::table('advice')
            ->where('is_approved', true)
            ->update(['moderation_status' => 'approved']);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('advice', function (Blueprint $table) {
            $table->dropIndex(['moderation_status']);
            $table->dropColumn(['source_url', 'moderation_status']);
        });
    }
};
