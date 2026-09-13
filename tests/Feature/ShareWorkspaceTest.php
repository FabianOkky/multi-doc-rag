<?php

use App\Livewire\Workspace\Show as WorkspaceShow;
use App\Models\ChatMessage;
use App\Models\ChatSession;
use App\Models\Document;
use App\Models\User;
use App\Models\Workspace;
use Livewire\Livewire;

test('enabling sharing generates a unique token and marks the workspace shared', function () {
    $workspace = Workspace::factory()->create(['is_shared' => false]);

    $workspace->enableSharing();

    expect($workspace->is_shared)->toBeTrue()
        ->and($workspace->share_token)->toBeString()
        ->and(strlen($workspace->share_token))->toBeGreaterThanOrEqual(40);
});

test('disabling sharing keeps the token but turns the link off', function () {
    $workspace = Workspace::factory()->create();
    $workspace->enableSharing();
    $token = $workspace->share_token;

    $workspace->disableSharing();

    expect($workspace->is_shared)->toBeFalse()
        // The token is preserved so re-enabling restores the same link.
        ->and($workspace->share_token)->toBe($token);
});

test('the owner can turn the public link on from the workspace page', function () {
    $user = User::factory()->create();
    $workspace = Workspace::factory()->for($user)->create();

    $this->actingAs($user);

    Livewire::test(WorkspaceShow::class, ['workspace' => $workspace])
        ->assertSee('Share')
        ->assertSet('isShared', false)
        ->set('isShared', true)
        ->assertSet('isShared', true)
        ->assertSee(route('workspaces.shared', $workspace->fresh()->share_token));

    $workspace->refresh();

    expect($workspace->is_shared)->toBeTrue()
        ->and($workspace->share_token)->not->toBeNull();
});

test('the owner can turn the public link off again', function () {
    $user = User::factory()->create();
    $workspace = Workspace::factory()->for($user)->create();

    $this->actingAs($user);

    Livewire::test(WorkspaceShow::class, ['workspace' => $workspace])
        ->set('isShared', true)
        ->set('isShared', false)
        ->assertSet('isShared', false);

    expect($workspace->fresh()->is_shared)->toBeFalse();
});

test('a non-owner cannot open the workspace page to toggle sharing', function () {
    $workspace = Workspace::factory()->create();

    $this->actingAs(User::factory()->create());

    Livewire::test(WorkspaceShow::class, ['workspace' => $workspace])
        ->assertForbidden();
});

test('a shared workspace link is reachable without logging in', function () {
    $user = User::factory()->create();
    $workspace = Workspace::factory()->for($user)->create(['name' => 'Shared research']);
    $workspace->enableSharing();

    Document::factory()->for($workspace)->ready()->create([
        'filename' => 'public-report.pdf',
        'summary' => 'A summary of the document shared with the public.',
    ]);

    // No actingAs(): this is an anonymous visitor.
    $this->get(route('workspaces.shared', $workspace->share_token))
        ->assertOk()
        ->assertSee('<meta name="robots" content="noindex,nofollow,noarchive"', escape: false)
        ->assertSee('Shared research')
        ->assertSee('public-report.pdf')
        ->assertSee('A summary of the document shared with the public.');

    expect(file_get_contents(public_path('robots.txt')))->toContain('Disallow: /s/');
});

test('a disabled share link returns 404', function () {
    $workspace = Workspace::factory()->create();
    $workspace->enableSharing();
    $token = $workspace->share_token;

    $workspace->disableSharing();

    $this->get(route('workspaces.shared', $token))->assertNotFound();
});

test('an unknown share token returns 404', function () {
    $this->get(route('workspaces.shared', 'a-token-that-was-never-issued'))->assertNotFound();
});

test('the shared page shows no owner actions', function () {
    $user = User::factory()->create();
    $workspace = Workspace::factory()->for($user)->create();
    $workspace->enableSharing();

    Document::factory()->for($workspace)->ready()->create(['filename' => 'source.pdf']);

    $this->get(route('workspaces.shared', $workspace->share_token))
        ->assertOk()
        ->assertSee('source.pdf')
        ->assertDontSee('Upload documents')                       // upload form (owner only)
        ->assertDontSee('wire:model.live="isShared"', escape: false) // share toggle (owner only)
        ->assertDontSee('deleteDocument', escape: false);          // delete button (owner only)
});

test('the shared page shows the conversation read-only', function () {
    $user = User::factory()->create();
    $workspace = Workspace::factory()->for($user)->create();
    $workspace->enableSharing();

    $document = Document::factory()->for($workspace)->ready()->create(['filename' => 'source.pdf']);
    $session = ChatSession::factory()->for($workspace)->create();
    $session->messages()->create([
        'role' => ChatMessage::ROLE_USER,
        'content' => 'What is the gist of this document?',
    ]);
    $session->messages()->create([
        'role' => ChatMessage::ROLE_ASSISTANT,
        'content' => 'An answer sourced from the document.',
        'citations' => [['document_id' => $document->id, 'filename' => 'source.pdf', 'page_number' => 3]],
    ]);

    $this->get(route('workspaces.shared', $workspace->share_token))
        ->assertOk()
        ->assertSee('What is the gist of this document?')
        ->assertSee('An answer sourced from the document.')
        ->assertSee('p. 3')   // citation page label
        // ...but a visitor cannot send new messages: there is no composer.
        ->assertDontSee('wire:submit="sendMessage"', escape: false);
});
