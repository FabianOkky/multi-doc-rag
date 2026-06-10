<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // The search_path (public, or "testing, public" under the test suite) is set on connect
        // by AppServiceProvider, so the pgvector "vector" type resolves here automatically.
        // Enable the pgvector extension (Supabase already ships it).
        Schema::ensureVectorExtensionExists();

        Schema::create('workspaces', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->string('share_token')->nullable()->unique(); // read-only share link
            $table->boolean('is_shared')->default(false);
            $table->timestamps();
        });

        Schema::create('documents', function (Blueprint $table) {
            $table->id();
            $table->foreignId('workspace_id')->constrained()->cascadeOnDelete();
            $table->string('filename');
            $table->string('file_type', 10);            // pdf | docx | txt
            $table->string('file_url')->nullable();     // disk path or Gemini File ID
            $table->text('summary')->nullable();        // auto-summary, 3-4 sentences
            $table->string('status')->default('processing'); // processing | ready | failed
            $table->timestamps();
        });

        Schema::create('document_chunks', function (Blueprint $table) {
            $table->id();
            $table->foreignId('document_id')->constrained()->cascadeOnDelete();
            // Also stored on the chunk so retrieval can be scoped per-workspace.
            $table->foreignId('workspace_id')->constrained()->cascadeOnDelete();
            $table->longText('content');
            $table->unsignedInteger('page_number')->nullable(); // citation source
            // 768 = Gemini gemini-embedding-001 output dimensions. Keep this in sync with
            // config('rag.embedding_dimensions') and config/ai.php embeddings dimensions.
            $table->vector('embedding', dimensions: 768);
            // HNSW index with vector_cosine_ops (a plain ->index() btree is rejected by pgvector).
            $table->vectorIndex('embedding');
            $table->timestamps();
        });

        Schema::create('chat_sessions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('workspace_id')->constrained()->cascadeOnDelete();
            $table->string('title')->nullable();
            $table->timestamps();
        });

        Schema::create('chat_messages', function (Blueprint $table) {
            $table->id();
            $table->foreignId('chat_session_id')->constrained()->cascadeOnDelete();
            $table->string('role', 20);                 // user | assistant
            $table->longText('content');
            $table->json('citations')->nullable();      // [{document_id, filename, page_number}]
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('chat_messages');
        Schema::dropIfExists('chat_sessions');
        Schema::dropIfExists('document_chunks');
        Schema::dropIfExists('documents');
        Schema::dropIfExists('workspaces');
    }
};
