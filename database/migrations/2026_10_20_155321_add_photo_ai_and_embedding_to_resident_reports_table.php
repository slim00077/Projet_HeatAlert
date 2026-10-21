<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Additive only: optional photo (private disk), whether the resident used the AI Incident Lens,
     * and the cached semantic vector used by the Incident Constellation.
     */
    public function up(): void
    {
        Schema::table('resident_reports', function (Blueprint $table) {
            $table->string('photo_path')->nullable()->after('description');
            $table->boolean('ai_assisted')->default(false)->after('photo_path');
            $table->longText('embedding')->nullable()->after('resolved_at');
            $table->string('embedding_signature', 64)->nullable()->after('embedding');
        });
    }

    public function down(): void
    {
        Schema::table('resident_reports', function (Blueprint $table) {
            $table->dropColumn(['photo_path', 'ai_assisted', 'embedding', 'embedding_signature']);
        });
    }
};
