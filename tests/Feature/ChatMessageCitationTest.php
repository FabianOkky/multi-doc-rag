<?php

use App\Agents\DocumentChatAgent;
use App\Livewire\Chat\Window;
use App\Models\ChatMessage;
use App\Models\Document;
use App\Models\DocumentChunk;
use App\Models\User;
use App\Models\Workspace;
use Laravel\Ai\Embeddings;
use Livewire\Livewire;

test('chatting stores the question, streams a grounded answer, and saves citations', function () {
    Embeddings::fake([[unitVector(0)]]);
    DocumentChatAgent::fake(['Menurut panduan.pdf hal. 3, prosedurnya dijelaskan di sana.']);

    $user = User::factory()->create();
    $workspace = Workspace::factory()->for($user)->create();
    $document = Document::factory()->for($workspace)->ready()->create(['filename' => 'panduan.pdf']);

    DocumentChunk::factory()->forDocument($document)->create([
        'embedding' => unitVector(0),
        'content' => 'Prosedur lengkap dijelaskan pada bagian ini.',
        'page_number' => 3,
    ]);

    $this->actingAs($user);

    Livewire::test(Window::class, ['workspace' => $workspace])
        ->set('question', 'Bagaimana prosedurnya?')
        ->call('sendMessage')
        ->assertDispatched('chat-answer-requested')
        ->call('streamAnswer');

    // The user's question was persisted with the correct role.
    $userMessage = ChatMessage::where('role', ChatMessage::ROLE_USER)->first();
    expect($userMessage)->not->toBeNull()
        ->and($userMessage->content)->toBe('Bagaimana prosedurnya?');

    // The assistant reply was persisted from the faked stream, with citations
    // drawn from the chunk that was actually retrieved.
    $assistant = ChatMessage::where('role', ChatMessage::ROLE_ASSISTANT)->first();
    expect($assistant)->not->toBeNull()
        ->and($assistant->content)->toBe('Menurut panduan.pdf hal. 3, prosedurnya dijelaskan di sana.')
        ->and($assistant->citations)->toHaveCount(1);
    expect($assistant->citations[0])->toMatchArray([
        'document_id' => $document->id,
        'filename' => 'panduan.pdf',
        'page_number' => 3,
    ]);

    // The agent was prompted with the question — never the real Gemini API, and
    // its instructions were grounded in the retrieved context.
    DocumentChatAgent::assertPrompted(function ($prompt) {
        return str_contains($prompt->prompt, 'Bagaimana prosedurnya?')
            && str_contains((string) $prompt->agent->instructions(), 'Prosedur lengkap dijelaskan pada bagian ini.');
    });
});

test('chat citations never reference a document from another workspace', function () {
    Embeddings::fake([[unitVector(0)]]);
    DocumentChatAgent::fake(['Jawaban berdasarkan dokumen Anda.']);

    $user = User::factory()->create();
    $mine = Workspace::factory()->for($user)->create();
    $other = Workspace::factory()->create();

    $myDocument = Document::factory()->for($mine)->ready()->create(['filename' => 'punyaku.pdf']);
    $otherDocument = Document::factory()->for($other)->ready()->create(['filename' => 'rahasia.pdf']);

    // Both chunks are a perfect match for the query, but only mine is in scope.
    DocumentChunk::factory()->forDocument($myDocument)->create([
        'embedding' => unitVector(0),
        'content' => 'Isi dokumen milik workspace saya.',
        'page_number' => 2,
    ]);
    DocumentChunk::factory()->forDocument($otherDocument)->create([
        'embedding' => unitVector(0),
        'content' => 'Isi dokumen milik workspace lain.',
        'page_number' => 9,
    ]);

    $this->actingAs($user);

    Livewire::test(Window::class, ['workspace' => $mine])
        ->set('question', 'Apa isi dokumennya?')
        ->call('sendMessage')
        ->call('streamAnswer');

    $assistant = ChatMessage::where('role', ChatMessage::ROLE_ASSISTANT)->first();

    expect($assistant->citations)->toHaveCount(1);
    expect($assistant->citations[0])->toMatchArray([
        'document_id' => $myDocument->id,
        'filename' => 'punyaku.pdf',
        'page_number' => 2,
    ]);

    $citedDocumentIds = collect($assistant->citations)->pluck('document_id');
    expect($citedDocumentIds)->not->toContain($otherDocument->id);
});

test('a blank question is rejected and never reaches the agent', function () {
    DocumentChatAgent::fake();

    $user = User::factory()->create();
    $workspace = Workspace::factory()->for($user)->create();

    $this->actingAs($user);

    Livewire::test(Window::class, ['workspace' => $workspace])
        ->set('question', '')
        ->call('sendMessage')
        ->assertHasErrors(['question' => 'required'])
        ->assertNotDispatched('chat-answer-requested');

    expect(ChatMessage::count())->toBe(0);
    DocumentChatAgent::assertNeverPrompted();
});
