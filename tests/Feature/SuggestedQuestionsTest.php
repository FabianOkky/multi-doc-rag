<?php

use App\Agents\SuggestionAgent;
use App\Livewire\Chat\Window;
use App\Models\ChatMessage;
use App\Models\Document;
use App\Models\User;
use App\Models\Workspace;
use App\Services\Suggester;
use Livewire\Livewire;

test('suggested questions are generated from the document summaries and shown as chips', function () {
    SuggestionAgent::fake([
        ['questions' => [
            'Apa kesimpulan utama laporan ini?',
            'Berapa anggaran yang diusulkan?',
            'Siapa pemangku kepentingan yang disebut?',
        ]],
    ]);

    $user = User::factory()->create();
    $workspace = Workspace::factory()->for($user)->create();
    Document::factory()->for($workspace)->ready()->create([
        'filename' => 'laporan.pdf',
        'summary' => 'Laporan tahunan tentang anggaran dan pemangku kepentingan.',
    ]);

    $this->actingAs($user);

    Livewire::test(Window::class, ['workspace' => $workspace])
        ->call('loadSuggestions')
        ->assertSet('suggestions', [
            'Apa kesimpulan utama laporan ini?',
            'Berapa anggaran yang diusulkan?',
            'Siapa pemangku kepentingan yang disebut?',
        ])
        ->assertSee('Apa kesimpulan utama laporan ini?')
        ->assertSee('Berapa anggaran yang diusulkan?')
        ->assertSee('Siapa pemangku kepentingan yang disebut?');

    // The agent was prompted with the document summary — never the real Gemini API.
    SuggestionAgent::assertPrompted(fn ($prompt) => str_contains(
        $prompt->prompt,
        'Laporan tahunan tentang anggaran dan pemangku kepentingan.'
    ));
});

test('clicking a suggestion chip sends that question through the chat flow', function () {
    SuggestionAgent::fake([
        ['questions' => ['Apa isi dokumen ini?', 'Apa poin pentingnya?', 'Bagaimana kesimpulannya?']],
    ]);

    $user = User::factory()->create();
    $workspace = Workspace::factory()->for($user)->create();
    Document::factory()->for($workspace)->ready()->create(['summary' => 'Sebuah ringkasan dokumen.']);

    $this->actingAs($user);

    Livewire::test(Window::class, ['workspace' => $workspace])
        ->call('loadSuggestions')
        ->call('askSuggestion', 0)
        ->assertDispatched('chat-answer-requested')
        ->assertSet('question', '');

    // The chosen suggestion was persisted as the user's question via the same
    // send path as typing, proving the Phase 04 chat flow is reused, not duplicated.
    $userMessage = ChatMessage::where('role', ChatMessage::ROLE_USER)->first();
    expect($userMessage)->not->toBeNull()
        ->and($userMessage->content)->toBe('Apa isi dokumen ini?');
});

test('no suggestions are shown while no document is ready', function () {
    SuggestionAgent::fake();

    $user = User::factory()->create();
    $workspace = Workspace::factory()->for($user)->create();
    // A document that is still processing — not ready, so there is nothing to suggest from.
    Document::factory()->for($workspace)->create();

    $this->actingAs($user);

    Livewire::test(Window::class, ['workspace' => $workspace])
        ->call('loadSuggestions')
        ->assertSet('suggestions', [])
        ->assertDontSee('Not sure where to start?');

    // With no ready document, the agent must never be prompted (saving quota).
    SuggestionAgent::assertNeverPrompted();
});

test('suggestions are generated once per workspace and then served from cache', function () {
    SuggestionAgent::fake([
        ['questions' => ['Pertanyaan A?', 'Pertanyaan B?', 'Pertanyaan C?']],
    ])->preventStrayPrompts();

    $user = User::factory()->create();
    $workspace = Workspace::factory()->for($user)->create();
    Document::factory()->for($workspace)->ready()->create(['summary' => 'Ringkasan dokumen.']);

    $suggester = app(Suggester::class);

    $first = $suggester->for($workspace);
    // A second call must hit the cache; were it to reach the agent again, the faked
    // gateway (preventStrayPrompts) would throw on the missing second response.
    $second = $suggester->for($workspace);

    expect($first)->toBe(['Pertanyaan A?', 'Pertanyaan B?', 'Pertanyaan C?'])
        ->and($second)->toBe($first);

    SuggestionAgent::assertPrompted(fn ($prompt) => str_contains($prompt->prompt, 'Ringkasan dokumen.'));
});
