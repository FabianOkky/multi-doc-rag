<?php

use App\Agents\DocumentChatAgent;
use App\Agents\QueryTranslationAgent;
use App\Livewire\Chat\Window;
use App\Models\Document;
use App\Models\DocumentChunk;
use App\Models\User;
use App\Models\Workspace;
use App\Services\AnswerLanguage;
use App\Services\QueryTranslator;
use App\Services\Retriever;
use Laravel\Ai\Embeddings;
use Laravel\Ai\Prompts\EmbeddingsPrompt;
use Livewire\Livewire;

// Cross-lingual search costs one prompt per distinct question, so the suite runs
// with it off by default (see phpunit.xml) and switches it on right here, where
// the translation agent is faked.
beforeEach(fn () => config(['rag.multilingual_query' => true]));

test('automatic fallback messages follow the question language without an AI call', function () {
    expect(AnswerLanguage::Auto->unavailableMessage('Apa isi dokumen ini?'))
        ->toStartWith('Maaf, jawaban belum dapat dibuat')
        ->and(AnswerLanguage::Auto->unavailableMessage('What is in this document?'))
        ->toStartWith('Sorry, an answer could not be generated');
});

test('an indonesian question retrieves an english passage it would otherwise miss', function () {
    // The Indonesian wording embeds to unit(1); its English translation embeds to
    // unit(0), which is the only vector the stored (English) chunk matches. If the
    // question were searched on its own, nothing would come back.
    QueryTranslationAgent::fake([
        implode("\n", ['LANG: id', 'ID: Berapa anggarannya?', 'EN: What is the budget?']),
    ]);
    Embeddings::fake([[unitVector(1), unitVector(0)]]);

    $workspace = Workspace::factory()->create();
    $document = Document::factory()->for($workspace)->ready()->create(['filename' => 'budget.pdf']);

    DocumentChunk::factory()->forDocument($document)->create([
        'embedding' => unitVector(0),
        'content' => 'The proposed budget for the next fiscal year is 4.2 million.',
        'page_number' => 2,
    ]);

    $result = app(Retriever::class)->retrieve($workspace, 'Berapa anggarannya?');

    expect($result->chunks)->toHaveCount(1)
        ->and($result->context)->toContain('The proposed budget for the next fiscal year is 4.2 million.')
        ->and($result->context)->toContain('budget.pdf, page 2');

    // The user's own wording is always searched too, alongside both translations.
    QueryTranslationAgent::assertPrompted(fn ($prompt) => str_contains($prompt->prompt, 'Berapa anggarannya?'));
    Embeddings::assertGenerated(fn (EmbeddingsPrompt $prompt) => $prompt->inputs === ['Berapa anggarannya?', 'What is the budget?']);
});

test('hits from different phrasings are merged and ranked by their best distance', function () {
    QueryTranslationAgent::fake([
        implode("\n", ['LANG: id', 'ID: Apa prosedurnya?', 'EN: What is the procedure?']),
    ]);
    // The Indonesian phrasing matches chunk A exactly; the English one matches B.
    Embeddings::fake([[unitVector(0), unitVector(1)]]);

    $workspace = Workspace::factory()->create();
    $document = Document::factory()->for($workspace)->ready()->create();

    DocumentChunk::factory()->forDocument($document)->create([
        'embedding' => unitVector(0),
        'content' => 'Prosedur lengkap dijelaskan di sini.',
        'page_number' => 1,
    ]);
    DocumentChunk::factory()->forDocument($document)->create([
        'embedding' => unitVector(1),
        'content' => 'The full procedure is described here.',
        'page_number' => 9,
    ]);

    $result = app(Retriever::class)->retrieve($workspace, 'Apa prosedurnya?');

    // Both languages contribute, and neither chunk is returned twice.
    expect($result->chunks)->toHaveCount(2)
        ->and($result->chunks->pluck('content')->all())->toEqualCanonicalizing([
            'Prosedur lengkap dijelaskan di sini.',
            'The full procedure is described here.',
        ])
        ->and($result->citations)->toHaveCount(2);
});

test('a translated question is only prompted for once and then served from cache', function () {
    QueryTranslationAgent::fake([
        implode("\n", ['LANG: en', 'ID: Apa isinya?', 'EN: What is in it?']),
    ])->preventStrayPrompts();

    $translator = app(QueryTranslator::class);

    $first = $translator->translate('What is in it?');
    // A second call must hit the cache; reaching the agent again would throw on
    // the missing second faked response.
    $second = $translator->translate('What is in it?');

    expect($first->variants)->toBe(['What is in it?', 'Apa isinya?'])
        ->and($second->variants)->toBe($first->variants)
        ->and($first->language)->toBe('en');
});

test('retrieval falls back to the users own wording when translation fails', function () {
    QueryTranslationAgent::fake(function (): never {
        throw new RuntimeException('Translation provider unavailable.');
    });

    // The provider failure is faked so the test stays offline and deterministic;
    // chat must still work, just without the cross-lingual second phrasing.
    Embeddings::fake([[unitVector(0)]]);

    $workspace = Workspace::factory()->create();
    $document = Document::factory()->for($workspace)->ready()->create();

    DocumentChunk::factory()->forDocument($document)->create([
        'embedding' => unitVector(0),
        'content' => 'Isi dokumen yang relevan.',
        'page_number' => 1,
    ]);

    $result = app(Retriever::class)->retrieve($workspace, 'Apa isi dokumennya?');

    // The Indonesian wording here is deliberate: this test is about an Indonesian
    // question still finding its answer when the translation step is unavailable.
    expect($result->chunks)->toHaveCount(1)
        ->and($result->chunks->first()->content)->toBe('Isi dokumen yang relevan.');

    Embeddings::assertGenerated(fn (EmbeddingsPrompt $prompt) => $prompt->inputs === ['Apa isi dokumennya?']);
});

test('translation is skipped entirely when multilingual search is disabled', function () {
    config(['rag.multilingual_query' => false]);

    QueryTranslationAgent::fake();
    Embeddings::fake([[unitVector(0)]]);

    $workspace = Workspace::factory()->create();

    app(Retriever::class)->retrieve($workspace, 'Berapa anggarannya?');

    QueryTranslationAgent::assertNeverPrompted();
});

test('the workspace answer language is persisted and reaches the chat agent', function () {
    QueryTranslationAgent::fake([
        implode("\n", ['LANG: id', 'ID: Apa isinya?', 'EN: What is in it?']),
    ]);
    Embeddings::fake([[unitVector(0), unitVector(1)]]);
    DocumentChatAgent::fake(['The document covers the quarterly results.']);

    $user = User::factory()->create();
    $workspace = Workspace::factory()->for($user)->create();

    $this->actingAs($user);

    captureStreamedOutput(fn () => Livewire::test(Window::class, ['workspace' => $workspace])
        ->assertSet('answerLanguage', AnswerLanguage::Auto->value)
        ->set('answerLanguage', AnswerLanguage::English->value)
        ->set('question', 'Apa isinya?')
        ->call('sendMessage')
        ->call('streamAnswer'));

    // Persisted on the workspace, so summaries and suggestions follow it too.
    expect($workspace->fresh()->answer_language)->toBe(AnswerLanguage::English);

    DocumentChatAgent::assertPrompted(fn ($prompt) => str_contains(
        (string) $prompt->agent->instructions(),
        'Write your answer in English',
    ));
});

test('the default answer language tells the agent to mirror the question', function () {
    QueryTranslationAgent::fake([
        implode("\n", ['LANG: id', 'ID: Apa isinya?', 'EN: What is in it?']),
    ]);
    Embeddings::fake([[unitVector(0), unitVector(1)]]);
    DocumentChatAgent::fake(['Dokumen ini membahas hasil kuartalan.']);

    $user = User::factory()->create();
    $workspace = Workspace::factory()->for($user)->create();

    $this->actingAs($user);

    captureStreamedOutput(fn () => Livewire::test(Window::class, ['workspace' => $workspace])
        ->set('question', 'Apa isinya?')
        ->call('sendMessage')
        ->call('streamAnswer'));

    DocumentChatAgent::assertPrompted(fn ($prompt) => str_contains(
        (string) $prompt->agent->instructions(),
        'same language the user asked in',
    ));
});
