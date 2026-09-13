<?php

use App\Models\User;

test('the public landing page is reachable by a guest', function () {
    $this->get(route('home'))
        ->assertOk()
        ->assertSee('Multi-Doc RAG');
});

test('a signed-in user is sent straight to their dashboard', function () {
    $this->actingAs(User::factory()->create())
        ->get(route('home'))
        ->assertRedirect(route('dashboard'));
});
