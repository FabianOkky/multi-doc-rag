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
        Schema::table('workspaces', function (Blueprint $table) {
            $table->index(['user_id', 'created_at']);
        });

        Schema::table('documents', function (Blueprint $table) {
            $table->index(['workspace_id', 'status']);
            $table->index(['workspace_id', 'created_at']);
        });

        Schema::table('document_chunks', function (Blueprint $table) {
            $table->index('document_id');
            $table->index('workspace_id');
        });

        Schema::table('chat_sessions', function (Blueprint $table) {
            $table->unique('workspace_id');
        });

        Schema::table('chat_messages', function (Blueprint $table) {
            $table->index(['chat_session_id', 'id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('chat_messages', function (Blueprint $table) {
            $table->dropIndex(['chat_session_id', 'id']);
        });

        Schema::table('chat_sessions', function (Blueprint $table) {
            $table->dropUnique(['workspace_id']);
        });

        Schema::table('document_chunks', function (Blueprint $table) {
            $table->dropIndex(['document_id']);
            $table->dropIndex(['workspace_id']);
        });

        Schema::table('documents', function (Blueprint $table) {
            $table->dropIndex(['workspace_id', 'status']);
            $table->dropIndex(['workspace_id', 'created_at']);
        });

        Schema::table('workspaces', function (Blueprint $table) {
            $table->dropIndex(['user_id', 'created_at']);
        });
    }
};
