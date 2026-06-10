<?php

use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

it('runs on postgres with the pgvector extension, never sqlite', function () {
    expect(DB::connection()->getDriverName())->toBe('pgsql');

    $pgvectorEnabled = DB::table('pg_extension')->where('extname', 'vector')->exists();

    expect($pgvectorEnabled)->toBeTrue();
});

it('created every multi-doc rag table', function () {
    foreach (['workspaces', 'documents', 'document_chunks', 'chat_sessions', 'chat_messages'] as $table) {
        expect(Schema::hasTable($table))->toBeTrue();
    }
});

it('stores a 768-dim embedding and runs a scoped vector similarity search', function () {
    $dimensions = config('rag.embedding_dimensions');

    expect($dimensions)->toBe(768);

    $user = User::factory()->create();

    $workspaceId = DB::table('workspaces')->insertGetId([
        'user_id' => $user->id,
        'name' => 'Smoke Test',
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    $documentId = DB::table('documents')->insertGetId([
        'workspace_id' => $workspaceId,
        'filename' => 'smoke.txt',
        'file_type' => 'txt',
        'status' => 'ready',
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    $embedding = array_fill(0, $dimensions, 0.1);

    DB::table('document_chunks')->insert([
        'document_id' => $documentId,
        'workspace_id' => $workspaceId,
        'content' => 'Hello from the smoke test.',
        'page_number' => 1,
        'embedding' => '['.implode(',', $embedding).']',
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    // Passing an array (not a string) keeps this offline — no Gemini API call.
    $results = DB::table('document_chunks')
        ->where('workspace_id', $workspaceId)
        ->whereVectorSimilarTo('embedding', $embedding, minSimilarity: 0.1)
        ->get();

    expect($results)->toHaveCount(1)
        ->and($results->first()->content)->toBe('Hello from the smoke test.');
});
