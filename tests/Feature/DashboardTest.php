<?php

use App\Models\Document;
use App\Models\User;
use App\Models\Workspace;

test('guests are redirected to the login page', function () {
    $response = $this->get(route('dashboard'));
    $response->assertRedirect(route('login'));
});

test('authenticated users can visit the dashboard', function () {
    $user = User::factory()->create();
    $this->actingAs($user);

    $response = $this->get(route('dashboard'));
    $response->assertOk();
});

test('the dashboard shows the user workspaces and recent documents', function () {
    $user = User::factory()->create();
    $workspace = Workspace::factory()->for($user)->create(['name' => 'Research papers']);
    Document::factory()->for($workspace)->ready()->create(['filename' => 'thesis.pdf']);

    $this->actingAs($user)
        ->get(route('dashboard'))
        ->assertOk()
        ->assertSee('Recent workspaces')
        ->assertSee('Research papers')
        ->assertSee('thesis.pdf');
});

test('a user with no workspaces sees the onboarding empty state', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->get(route('dashboard'))
        ->assertOk()
        ->assertSee('Create your first workspace')
        ->assertDontSee('Recent workspaces');
});

test('the dashboard does not leak another user data', function () {
    $user = User::factory()->create();
    $other = User::factory()->create();
    $otherWorkspace = Workspace::factory()->for($other)->create(['name' => 'Private notes']);
    Document::factory()->for($otherWorkspace)->ready()->create(['filename' => 'secret.pdf']);

    $this->actingAs($user)
        ->get(route('dashboard'))
        ->assertOk()
        ->assertDontSee('Private notes')
        ->assertDontSee('secret.pdf');
});
