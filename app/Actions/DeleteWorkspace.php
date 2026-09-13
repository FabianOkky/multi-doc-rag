<?php

namespace App\Actions;

use App\Models\Workspace;
use Illuminate\Support\Facades\Storage;

class DeleteWorkspace
{
    /**
     * Delete a workspace and every document file it owns.
     */
    public function execute(Workspace $workspace): void
    {
        $paths = $workspace->documents()
            ->whereNotNull('file_url')
            ->pluck('file_url')
            ->filter(fn (mixed $path): bool => is_string($path) && $path !== '')
            ->values()
            ->all();

        $workspace->delete();

        if ($paths !== []) {
            Storage::disk(config('filesystems.default'))->delete($paths);
        }
    }
}
