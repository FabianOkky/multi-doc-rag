<?php

use App\Livewire\Dashboard;
use App\Livewire\Shared\Show as SharedShow;
use App\Livewire\Workspace\Index as WorkspaceIndex;
use App\Livewire\Workspace\Show as WorkspaceShow;
use Illuminate\Support\Facades\Route;

Route::view('/', 'welcome')->name('home');

// Public read-only share link — no auth. The component 404s unless the token
// belongs to a workspace with sharing enabled.
Route::livewire('/s/{token}', SharedShow::class)->name('workspaces.shared');

Route::middleware(['auth', 'verified'])->group(function () {
    Route::livewire('dashboard', Dashboard::class)->name('dashboard');

    Route::livewire('workspaces', WorkspaceIndex::class)->name('workspaces.index');
    Route::livewire('workspaces/{workspace}', WorkspaceShow::class)->name('workspaces.show');
});

require __DIR__.'/settings.php';
