<?php

use App\Jobs\ParseAndEmbedDocument;
use App\Livewire\Workspace\Show;
use App\Models\Document;
use App\Models\User;
use App\Models\Workspace;
use Illuminate\Foundation\Console\ServeCommand;
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
        ->assertHasErrors('files');

    expect(Document::count())->toBe(0);

    Queue::assertNothingPushed();
});

test('the advertised twenty megabyte upload limit is also configured globally', function () {
    Storage::fake('local');
    Queue::fake();

    expect(config('livewire.temporary_file_upload.rules'))->toContain('max:20480');

    // PHP rejects an oversized upload at request startup, before any of the rules
    // above run, so its own limits have to agree with what the UI advertises. Two
    // files cover the two ways this app is served: .user.ini for FastCGI (Herd,
    // nginx + PHP-FPM) and deploy/php.ini for the `artisan serve` fallback.
    expect(file_get_contents(public_path('.user.ini')))
        ->toContain('upload_max_filesize=21M')
        ->toContain('post_max_size=210M');
    expect(file_get_contents(base_path('deploy/php.ini')))
        ->toContain('upload_max_filesize = 21M')
        ->toContain('post_max_size = 210M');

    $user = User::factory()->create();
    $workspace = Workspace::factory()->for($user)->create();
    $file = UploadedFile::fake()->create('large-notes.txt', 15 * 1024, 'text/plain');

    $this->actingAs($user);

    Livewire::test(Show::class, ['workspace' => $workspace])
        ->set('files', [$file])
        ->call('save')
        ->assertHasNoErrors();

    expect(Document::count())->toBe(1);
});

test('an allowed extension is rejected when it does not match the file contents', function () {
    Storage::fake('local');
    Queue::fake();

    $user = User::factory()->create();
    $workspace = Workspace::factory()->for($user)->create();
    $renamedPdf = UploadedFile::fake()->create('renamed.txt', 10, 'application/pdf');

    $this->actingAs($user);

    Livewire::test(Show::class, ['workspace' => $workspace])
        ->set('files', [$renamedPdf])
        ->call('save')
        ->assertHasErrors('files.0');

    expect(Document::count())->toBe(0);
    Queue::assertNothingPushed();
});

test('an upload is limited to ten files at a time', function () {
    Storage::fake('local');
    Queue::fake();

    $user = User::factory()->create();
    $workspace = Workspace::factory()->for($user)->create();
    $files = collect(range(1, 11))
        ->map(fn (int $number): UploadedFile => UploadedFile::fake()->createWithContent("notes-{$number}.txt", 'Document text.'))
        ->all();

    $this->actingAs($user);

    Livewire::test(Show::class, ['workspace' => $workspace])
        ->set('files', $files)
        ->call('save')
        ->assertHasErrors(['files' => 'max']);

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

test('the development server is allowed to locate a temporary directory', function () {
    // `artisan serve` runs `php -S` as a child process and blanks every environment
    // variable outside this allow-list. PHP resolves its temporary directory from
    // TMP / TEMP / USERPROFILE, so without them it cannot create the temporary file
    // an upload needs, and every upload fails at request startup before any of this
    // application's code runs. PHPRC lets that child be pointed at deploy/php.ini.
    expect(ServeCommand::$passthroughVariables)
        ->toContain('TMP')
        ->toContain('TEMP')
        ->toContain('USERPROFILE')
        ->toContain('PHPRC');
});

test('a user cannot open another user workspace to upload', function () {
    $workspace = Workspace::factory()->create();

    $this->actingAs(User::factory()->create());

    Livewire::test(Show::class, ['workspace' => $workspace])
        ->assertForbidden();
});
