<?php

use App\Agents\DocumentChatAgent;
use App\Livewire\Chat\Window;
use App\Models\ChatMessage;
use App\Models\Document;
use App\Models\DocumentChunk;
use App\Models\User;
use App\Models\Workspace;
use Illuminate\Support\Facades\RateLimiter;
use Laravel\Ai\Embeddings;
use Livewire\Features\SupportLockedProperties\CannotUpdateLockedPropertyException;
use Livewire\Livewire;

test('chatting stores the question, streams a grounded answer, and saves citations', function () {
    Embeddings::fake([[unitVector(0)]]);
    DocumentChatAgent::fake(['According to handbook.pdf, page 3, the procedure is described there.']);

    $user = User::factory()->create();
    $workspace = Workspace::factory()->for($user)->create();
    $document = Document::factory()->for($workspace)->ready()->create(['filename' => 'handbook.pdf']);

    DocumentChunk::factory()->forDocument($document)->create([
        'embedding' => unitVector(0),
        'content' => 'The full procedure is described in this section.',
        'page_number' => 3,
    ]);

    $this->actingAs($user);

    captureStreamedOutput(fn () => Livewire::test(Window::class, ['workspace' => $workspace])
        ->set('question', 'What is the procedure?')
        ->call('sendMessage')
        ->assertDispatched('chat-answer-requested')
        ->call('streamAnswer'));

    // The user's question was persisted with the correct role.
    $userMessage = ChatMessage::where('role', ChatMessage::ROLE_USER)->first();
    expect($userMessage)->not->toBeNull()
        ->and($userMessage->content)->toBe('What is the procedure?');

    // The assistant reply was persisted from the faked stream, with citations
    // drawn from the chunk that was actually retrieved.
    $assistant = ChatMessage::where('role', ChatMessage::ROLE_ASSISTANT)->first();
    expect($assistant)->not->toBeNull()
        ->and($assistant->content)->toBe('According to handbook.pdf, page 3, the procedure is described there.')
        ->and($assistant->citations)->toHaveCount(1);
    expect($assistant->citations[0])->toBeCitation($document->id, 'handbook.pdf', 3);

    // The agent was prompted with the question — never the real Gemini API, and
    // its instructions were grounded in the retrieved context.
    DocumentChatAgent::assertPrompted(function ($prompt) {
        return str_contains($prompt->prompt, 'What is the procedure?')
            && str_contains((string) $prompt->agent->instructions(), 'The full procedure is described in this section.');
    });
});

test('a citation is matched even when the answer names the page in indonesian', function () {
    // Answers follow the language of the question, so an Indonesian reply cites
    // "hal. 3" rather than "page 3". RetrievalResult has to recognise both words,
    // or a perfectly good Indonesian answer would be shown with no sources at all.
    Embeddings::fake([[unitVector(0)]]);
    DocumentChatAgent::fake(['Menurut handbook.pdf hal. 3, prosedurnya dijelaskan di sana.']);

    $user = User::factory()->create();
    $workspace = Workspace::factory()->for($user)->create();
    $document = Document::factory()->for($workspace)->ready()->create(['filename' => 'handbook.pdf']);

    DocumentChunk::factory()->forDocument($document)->create([
        'embedding' => unitVector(0),
        'content' => 'The full procedure is described in this section.',
        'page_number' => 3,
    ]);

    $this->actingAs($user);

    captureStreamedOutput(fn () => Livewire::test(Window::class, ['workspace' => $workspace])
        ->set('question', 'Bagaimana prosedurnya?')
        ->call('sendMessage')
        ->call('streamAnswer'));

    expect(ChatMessage::where('role', ChatMessage::ROLE_ASSISTANT)->sole()->citations[0])
        ->toBeCitation($document->id, 'handbook.pdf', 3);
});

test('chat citations never reference a document from another workspace', function () {
    Embeddings::fake([[unitVector(0)]]);
    DocumentChatAgent::fake(['According to mine.pdf, page 2, this answer comes from your document.']);

    $user = User::factory()->create();
    $mine = Workspace::factory()->for($user)->create();
    $other = Workspace::factory()->create();

    $myDocument = Document::factory()->for($mine)->ready()->create(['filename' => 'mine.pdf']);
    $otherDocument = Document::factory()->for($other)->ready()->create(['filename' => 'confidential.pdf']);

    // Both chunks are a perfect match for the query, but only mine is in scope.
    DocumentChunk::factory()->forDocument($myDocument)->create([
        'embedding' => unitVector(0),
        'content' => 'Content belonging to my own workspace.',
        'page_number' => 2,
    ]);
    DocumentChunk::factory()->forDocument($otherDocument)->create([
        'embedding' => unitVector(0),
        'content' => 'Content belonging to another workspace entirely.',
        'page_number' => 9,
    ]);

    $this->actingAs($user);

    captureStreamedOutput(fn () => Livewire::test(Window::class, ['workspace' => $mine])
        ->set('question', 'What is in the document?')
        ->call('sendMessage')
        ->call('streamAnswer'));

    $assistant = ChatMessage::where('role', ChatMessage::ROLE_ASSISTANT)->first();

    expect($assistant->citations)->toHaveCount(1)
        ->and($assistant->citations[0])->toBeCitation($myDocument->id, 'mine.pdf', 2);

    $citedDocumentIds = collect($assistant->citations)->pluck('document_id');
    expect($citedDocumentIds)->not->toContain($otherDocument->id);
});

test('retrieved passages are not presented as citations unless the answer names them', function () {
    Embeddings::fake([[unitVector(0)]]);
    DocumentChatAgent::fake(['The information requested is not clear enough.']);

    $user = User::factory()->create();
    $workspace = Workspace::factory()->for($user)->create();
    $document = Document::factory()->for($workspace)->ready()->create(['filename' => 'notes.pdf']);

    DocumentChunk::factory()->forDocument($document)->create([
        'embedding' => unitVector(0),
        'content' => 'A passage that was retrieved but never used in the answer.',
        'page_number' => 4,
    ]);

    $this->actingAs($user);

    captureStreamedOutput(fn () => Livewire::test(Window::class, ['workspace' => $workspace])
        ->set('question', 'What is the answer?')
        ->call('sendMessage')
        ->call('streamAnswer'));

    expect(ChatMessage::where('role', ChatMessage::ROLE_ASSISTANT)->sole()->citations)->toBe([]);
});

test('an unavailable AI provider saves a fallback in the question language, without citations', function () {
    Embeddings::fake([[unitVector(0)]]);
    DocumentChatAgent::fake(function (): never {
        throw new RuntimeException('Chat provider unavailable.');
    });

    $user = User::factory()->create();
    $workspace = Workspace::factory()->for($user)->create();
    $document = Document::factory()->for($workspace)->ready()->create();

    DocumentChunk::factory()->forDocument($document)->create([
        'embedding' => unitVector(0),
        'content' => 'Relevant document content.',
        'page_number' => 1,
    ]);

    $this->actingAs($user);

    // Asked in Indonesian, so the offline fallback answers in Indonesian too. It is
    // built without an AI call, which is the whole point: the provider is down.
    captureStreamedOutput(fn () => Livewire::test(Window::class, ['workspace' => $workspace])
        ->set('question', 'Apa isi dokumen ini?')
        ->call('sendMessage')
        ->call('streamAnswer')
        ->assertSet('pendingQuestion', null));

    $assistant = ChatMessage::where('role', ChatMessage::ROLE_ASSISTANT)->sole();

    expect($assistant->content)
        ->toStartWith('Maaf, jawaban belum dapat dibuat')
        ->and($assistant->citations)->toBe([]);
});

test('questions are trimmed before they are persisted', function () {
    $user = User::factory()->create();
    $workspace = Workspace::factory()->for($user)->create();

    $this->actingAs($user);

    Livewire::test(Window::class, ['workspace' => $workspace])
        ->set('question', '  What is the summary?  ')
        ->call('sendMessage');

    expect(ChatMessage::where('role', ChatMessage::ROLE_USER)->sole()->content)->toBe('What is the summary?');
});

test('chat prompts are rate limited before a message is persisted', function () {
    $user = User::factory()->create();
    $workspace = Workspace::factory()->for($user)->create();
    $rateLimitKey = 'rag:chat:'.$workspace->id.':'.$user->id;

    RateLimiter::clear($rateLimitKey);

    foreach (range(1, 10) as $attempt) {
        RateLimiter::hit($rateLimitKey, 60);
    }

    $this->actingAs($user);

    Livewire::test(Window::class, ['workspace' => $workspace])
        ->set('question', 'What is in it?')
        ->call('sendMessage')
        ->assertHasErrors('question')
        ->assertNotDispatched('chat-answer-requested');

    expect(ChatMessage::count())->toBe(0);

    RateLimiter::clear($rateLimitKey);
});

test('the pending question cannot be changed by the browser', function () {
    $user = User::factory()->create();
    $workspace = Workspace::factory()->for($user)->create();

    $this->actingAs($user);

    expect(fn () => Livewire::test(Window::class, ['workspace' => $workspace])
        ->set('pendingQuestion', str_repeat('x', 3000)))
        ->toThrow(CannotUpdateLockedPropertyException::class);
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
