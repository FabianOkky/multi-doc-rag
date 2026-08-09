<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Which language this workspace's answers, summaries, and suggestions are
     * written in: "auto" (mirror the question / the document), "id", or "en".
     * Retrieval stays cross-lingual regardless — this only controls output.
     */
    public function up(): void
    {
        Schema::table('workspaces', function (Blueprint $table) {
            $table->string('answer_language', 10)->default('auto')->after('name');
        });
    }

    public function down(): void
    {
        Schema::table('workspaces', function (Blueprint $table) {
            $table->dropColumn('answer_language');
        });
    }
};
