<?php

use App\Jobs\ParseAndEmbedDocument;
use App\Livewire\Workspace\Show;
use App\Models\Document;
use App\Models\User;
use App\Models\Workspace;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;

test('a user can upload a document, creating a processing record and queuing the job', function () {
    Storage::fake('local');
    Queue::fake();

    $user = User::factory()->create();
    $workspace = Workspace::factory()->for($user)->create();

    $this->actingAs($user);

    $file = UploadedFile::fake()->createWithContent('notes.txt', 'Hello world. This is a real document.');

    Livewire::test(Show::class, ['workspace' => $workspace])
        ->set('files', [$file])
        ->call('save')
        ->assertHasNoErrors();

    $document = Document::sole();

    expect($document->workspace_id)->toBe($workspace->id)
        ->and($document->filename)->toBe('notes.txt')
        ->and($document->file_type)->toBe('txt')
        ->and($document->status)->toBe(Document::STATUS_PROCESSING)
        ->and($document->file_url)->not->toBeNull();

    Storage::disk('local')->assertExists($document->file_url);

    Queue::assertPushed(
        ParseAndEmbedDocument::class,
        fn (ParseAndEmbedDocument $job) => $job->document->is($document)
    );
});

test('uploading an unsupported file type fails validation and creates no document', function () {
    Storage::fake('local');
    Queue::fake();

    $user = User::factory()->create();
    $workspace = Workspace::factory()->for($user)->create();

    $this->actingAs($user);

    $file = UploadedFile::fake()->create('malware.exe', 10, 'application/octet-stream');

    Livewire::test(Show::class, ['workspace' => $workspace])
        ->set('files', [$file])
        ->call('save')
        ->assertHasErrors('files.0');

    expect(Document::count())->toBe(0);

    Queue::assertNothingPushed();
});

test('uploading without selecting a file fails validation', function () {
    Storage::fake('local');
    Queue::fake();

    $user = User::factory()->create();
    $workspace = Workspace::factory()->for($user)->create();

    $this->actingAs($user);

    Livewire::test(Show::class, ['workspace' => $workspace])
        ->call('save')
        ->assertHasErrors('files');

    expect(Document::count())->toBe(0);
    Queue::assertNothingPushed();
});

test('a user cannot open another user workspace to upload', function () {
    $workspace = Workspace::factory()->create();

    $this->actingAs(User::factory()->create());

    Livewire::test(Show::class, ['workspace' => $workspace])
        ->assertForbidden();
});
