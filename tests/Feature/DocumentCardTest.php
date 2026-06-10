<?php

use App\Jobs\ParseAndEmbedDocument;
use App\Livewire\Workspace\Show;
use App\Models\Document;
use App\Models\User;
use App\Models\Workspace;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;

test('the workspace page shows a ready document with its summary and status', function () {
    $user = User::factory()->create();
    $workspace = Workspace::factory()->for($user)->create();

    Document::factory()->for($workspace)->ready()->create([
        'filename' => 'laporan-tahunan.pdf',
        'summary' => 'Dokumen ini merangkum kinerja keuangan tahun berjalan.',
    ]);

    $this->actingAs($user);

    Livewire::test(Show::class, ['workspace' => $workspace])
        ->assertSee('laporan-tahunan.pdf')
        ->assertSee('Dokumen ini merangkum kinerja keuangan tahun berjalan.')
        ->assertSee('Ready');
});

test('a processing document is shown in a loading state without a summary', function () {
    $user = User::factory()->create();
    $workspace = Workspace::factory()->for($user)->create();

    Document::factory()->for($workspace)->create([
        'filename' => 'sedang-diproses.pdf',
        'status' => Document::STATUS_PROCESSING,
        'summary' => null,
    ]);

    $this->actingAs($user);

    Livewire::test(Show::class, ['workspace' => $workspace])
        ->assertSee('sedang-diproses.pdf')
        ->assertSee('Processing')
        ->assertSee('Generating summary');
});

test('the workspace owner can delete a document', function () {
    Storage::fake('local');

    $user = User::factory()->create();
    $workspace = Workspace::factory()->for($user)->create();

    $document = Document::factory()->for($workspace)->ready()->create([
        'file_url' => 'documents/keep.txt',
    ]);
    Storage::disk('local')->put($document->file_url, 'stored contents');

    $this->actingAs($user);

    Livewire::test(Show::class, ['workspace' => $workspace])
        ->call('deleteDocument', $document->id)
        ->assertHasNoErrors();

    expect(Document::find($document->id))->toBeNull();
    Storage::disk('local')->assertMissing('documents/keep.txt');
});

test('a failed document shows a retry control', function () {
    $user = User::factory()->create();
    $workspace = Workspace::factory()->for($user)->create();

    Document::factory()->for($workspace)->failed()->create([
        'filename' => 'gagal-diproses.pdf',
    ]);

    $this->actingAs($user);

    Livewire::test(Show::class, ['workspace' => $workspace])
        ->assertSee('gagal-diproses.pdf')
        ->assertSee('Failed')
        ->assertSee('Retry')
        ->assertSeeHtml('wire:click="retryDocument');
});

test('the workspace owner can retry a failed document', function () {
    Queue::fake();

    $user = User::factory()->create();
    $workspace = Workspace::factory()->for($user)->create();

    $document = Document::factory()->for($workspace)->failed()->create();

    $this->actingAs($user);

    Livewire::test(Show::class, ['workspace' => $workspace])
        ->call('retryDocument', $document->id)
        ->assertHasNoErrors();

    expect($document->fresh()->status)->toBe(Document::STATUS_PROCESSING);
    Queue::assertPushed(ParseAndEmbedDocument::class);
});

test('a user cannot retry a document from a workspace they do not own', function () {
    Queue::fake();

    $user = User::factory()->create();
    $myWorkspace = Workspace::factory()->for($user)->create();

    $victimWorkspace = Workspace::factory()->create();
    $victimDocument = Document::factory()->for($victimWorkspace)->failed()->create();

    $this->actingAs($user);

    Livewire::test(Show::class, ['workspace' => $myWorkspace])
        ->call('retryDocument', $victimDocument->id)
        ->assertStatus(404);

    expect($victimDocument->fresh()->status)->toBe(Document::STATUS_FAILED);
    Queue::assertNothingPushed();
});

test('a non-owner cannot open the workspace page to manage its documents', function () {
    $owner = User::factory()->create();
    $workspace = Workspace::factory()->for($owner)->create();
    Document::factory()->for($workspace)->create();

    $this->actingAs(User::factory()->create())
        ->get(route('workspaces.show', $workspace))
        ->assertForbidden();
});

test('a user cannot delete a document from a workspace they do not own', function () {
    $user = User::factory()->create();
    $myWorkspace = Workspace::factory()->for($user)->create();

    $victimWorkspace = Workspace::factory()->create();
    $victimDocument = Document::factory()->for($victimWorkspace)->create();

    $this->actingAs($user);

    Livewire::test(Show::class, ['workspace' => $myWorkspace])
        ->call('deleteDocument', $victimDocument->id)
        ->assertStatus(404);

    expect(Document::find($victimDocument->id))->not->toBeNull();
});
