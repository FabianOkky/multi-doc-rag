<?php

use App\Livewire\Workspace\Index;
use App\Models\Document;
use App\Models\User;
use App\Models\Workspace;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;

test('guests are redirected to login from the workspaces index', function () {
    $this->get(route('workspaces.index'))->assertRedirect(route('login'));
});

test('guests are redirected to login from a workspace page', function () {
    $workspace = Workspace::factory()->create();

    $this->get(route('workspaces.show', $workspace))->assertRedirect(route('login'));
});

test('authenticated users can view the workspaces index', function () {
    $this->actingAs(User::factory()->create());

    $this->get(route('workspaces.index'))->assertOk();
});

test('a user can create a workspace and see it in their list', function () {
    $user = User::factory()->create();
    $this->actingAs($user);

    Livewire::test(Index::class)
        ->set('name', 'Research papers')
        ->call('createWorkspace')
        ->assertHasNoErrors()
        ->assertSee('Research papers');

    $workspace = Workspace::sole();

    expect($workspace->name)->toBe('Research papers')
        ->and($workspace->user_id)->toBe($user->id);
});

test('the workspace name is required', function () {
    $this->actingAs(User::factory()->create());

    Livewire::test(Index::class)
        ->set('name', '')
        ->call('createWorkspace')
        ->assertHasErrors(['name' => 'required']);

    expect(Workspace::count())->toBe(0);
});

test('workspace names are trimmed before create and rename', function () {
    $user = User::factory()->create();
    $this->actingAs($user);

    Livewire::test(Index::class)
        ->set('name', '  Research papers  ')
        ->call('createWorkspace');

    $workspace = Workspace::sole();

    Livewire::test(Index::class)
        ->call('startRename', $workspace->id)
        ->set('editName', '  Annual reports  ')
        ->call('rename');

    expect($workspace->fresh()->name)->toBe('Annual reports');
});

test('the index only lists the current user workspaces', function () {
    $user = User::factory()->create();
    Workspace::factory()->for($user)->create(['name' => 'My workspace']);
    Workspace::factory()->create(['name' => 'Someone elses workspace']);

    $this->actingAs($user);

    Livewire::test(Index::class)
        ->assertSee('My workspace')
        ->assertDontSee('Someone elses workspace');
});

test('a user can view their own workspace', function () {
    $user = User::factory()->create();
    $workspace = Workspace::factory()->for($user)->create(['name' => 'My research']);

    $this->actingAs($user)
        ->get(route('workspaces.show', $workspace))
        ->assertOk()
        ->assertSee('My research');
});

test('a user cannot view another user workspace', function () {
    $workspace = Workspace::factory()->create();

    $this->actingAs(User::factory()->create())
        ->get(route('workspaces.show', $workspace))
        ->assertForbidden();
});

test('a user can rename their own workspace', function () {
    $user = User::factory()->create();
    $workspace = Workspace::factory()->for($user)->create(['name' => 'Old name']);

    $this->actingAs($user);

    Livewire::test(Index::class)
        ->call('startRename', $workspace->id)
        ->assertSet('editName', 'Old name')
        ->assertSet('showEditModal', true)
        ->set('editName', 'New name')
        ->call('rename')
        ->assertHasNoErrors()
        ->assertSet('showEditModal', false);

    expect($workspace->refresh()->name)->toBe('New name');
});

test('a user cannot open the rename modal for another user workspace', function () {
    $workspace = Workspace::factory()->create();

    $this->actingAs(User::factory()->create());

    Livewire::test(Index::class)
        ->call('startRename', $workspace->id)
        ->assertForbidden();
});

test('a user cannot rename another user workspace by spoofing the id', function () {
    $workspace = Workspace::factory()->create(['name' => 'Old name']);

    $this->actingAs(User::factory()->create());

    Livewire::test(Index::class)
        ->set('editingId', $workspace->id)
        ->set('editName', 'Hacked')
        ->call('rename')
        ->assertForbidden();

    expect($workspace->refresh()->name)->toBe('Old name');
});

test('a user can delete their own workspace', function () {
    Storage::fake('local');

    $user = User::factory()->create();
    $workspace = Workspace::factory()->for($user)->create();
    $document = Document::factory()->for($workspace)->create(['file_url' => 'documents/workspace-file.txt']);
    Storage::disk('local')->put($document->file_url, 'contents');

    $this->actingAs($user);

    Livewire::test(Index::class)
        ->call('confirmDelete', $workspace->id)
        ->assertSet('showDeleteModal', true)
        ->call('deleteWorkspace')
        ->assertHasNoErrors()
        ->assertSet('showDeleteModal', false);

    expect(Workspace::find($workspace->id))->toBeNull();
    Storage::disk('local')->assertMissing('documents/workspace-file.txt');
});

test('a user cannot open the delete modal for another user workspace', function () {
    $workspace = Workspace::factory()->create();

    $this->actingAs(User::factory()->create());

    Livewire::test(Index::class)
        ->call('confirmDelete', $workspace->id)
        ->assertForbidden();
});

test('a user cannot delete another user workspace by spoofing the id', function () {
    $workspace = Workspace::factory()->create();

    $this->actingAs(User::factory()->create());

    Livewire::test(Index::class)
        ->set('deletingId', $workspace->id)
        ->call('deleteWorkspace')
        ->assertForbidden();

    expect(Workspace::find($workspace->id))->not->toBeNull();
});
