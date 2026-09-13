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
            'What are the main conclusions of this report?',
            'How large is the proposed budget?',
            'Which stakeholders are mentioned?',
        ]],
    ]);

    $user = User::factory()->create();
    $workspace = Workspace::factory()->for($user)->create();
    Document::factory()->for($workspace)->ready()->create([
        'filename' => 'report.pdf',
        'summary' => 'An annual report covering the budget and the stakeholders involved.',
    ]);

    $this->actingAs($user);

    Livewire::test(Window::class, ['workspace' => $workspace])
        ->call('loadSuggestions')
        ->assertSet('suggestions', [
            'What are the main conclusions of this report?',
            'How large is the proposed budget?',
            'Which stakeholders are mentioned?',
        ])
        ->assertSee('What are the main conclusions of this report?')
        ->assertSee('How large is the proposed budget?')
        ->assertSee('Which stakeholders are mentioned?');

    // The agent was prompted with the document summary — never the real Gemini API.
    SuggestionAgent::assertPrompted(fn ($prompt) => str_contains(
        $prompt->prompt,
        'An annual report covering the budget and the stakeholders involved.'
    ));
});

test('clicking a suggestion chip sends that question through the chat flow', function () {
    SuggestionAgent::fake([
        ['questions' => ['What is in this document?', 'What are the key points?', 'What is the conclusion?']],
    ]);

    $user = User::factory()->create();
    $workspace = Workspace::factory()->for($user)->create();
    Document::factory()->for($workspace)->ready()->create(['summary' => 'A summary of the document.']);

    $this->actingAs($user);

    Livewire::test(Window::class, ['workspace' => $workspace])
        ->call('loadSuggestions')
        ->call('askSuggestion', 0)
        ->assertDispatched('chat-answer-requested')
        ->assertSet('question', '');

    // The chosen suggestion was persisted as the user's question via the same
    // send path as typing, proving the chat flow is reused, not duplicated.
    $userMessage = ChatMessage::where('role', ChatMessage::ROLE_USER)->first();
    expect($userMessage)->not->toBeNull()
        ->and($userMessage->content)->toBe('What is in this document?');
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
        ['questions' => ['Question A?', 'Question B?', 'Question C?']],
    ])->preventStrayPrompts();

    $user = User::factory()->create();
    $workspace = Workspace::factory()->for($user)->create();
    Document::factory()->for($workspace)->ready()->create(['summary' => 'A document summary.']);

    $suggester = app(Suggester::class);

    $first = $suggester->for($workspace);
    // A second call must hit the cache; were it to reach the agent again, the faked
    // gateway (preventStrayPrompts) would throw on the missing second response.
    $second = $suggester->for($workspace);

    expect($first)->toBe(['Question A?', 'Question B?', 'Question C?'])
        ->and($second)->toBe($first);

    SuggestionAgent::assertPrompted(fn ($prompt) => str_contains($prompt->prompt, 'A document summary.'));
});

test('a failing suggestion prompt degrades to no chips instead of breaking the chat', function () {
    SuggestionAgent::fake(function (): never {
        throw new RuntimeException('Suggestion provider unavailable.');
    });

    // The provider failure is faked so this test never reaches the network.
    // The chips are a convenience: losing them must not cost the user the page.
    $user = User::factory()->create();
    $workspace = Workspace::factory()->for($user)->create();
    Document::factory()->for($workspace)->ready()->create(['summary' => 'A summary of the document.']);

    $this->actingAs($user);

    Livewire::test(Window::class, ['workspace' => $workspace])
        ->call('loadSuggestions')
        ->assertOk()
        ->assertSet('suggestions', []);
});

test('a failed suggestion prompt is not cached, so the next visit retries', function () {
    SuggestionAgent::fake(function (): never {
        throw new RuntimeException('Suggestion provider unavailable.');
    });

    $user = User::factory()->create();
    $workspace = Workspace::factory()->for($user)->create();
    Document::factory()->for($workspace)->ready()->create(['summary' => 'A document summary.']);

    $suggester = app(Suggester::class);

    // First call fails and must leave the cache empty...
    expect($suggester->for($workspace))->toBe([]);

    // ...so once the provider recovers, the very next call succeeds.
    SuggestionAgent::fake([
        ['questions' => ['Question A?', 'Question B?', 'Question C?']],
    ]);

    expect($suggester->for($workspace))->toBe(['Question A?', 'Question B?', 'Question C?']);
});
