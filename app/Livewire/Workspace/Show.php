<?php

namespace App\Livewire\Workspace;

use App\Jobs\ParseAndEmbedDocument;
use App\Models\Document;
use App\Models\Workspace;
use Flux\Flux;
use Illuminate\Contracts\View\View;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Support\Facades\Storage;
use Livewire\Attributes\Layout;
use Livewire\Component;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;
use Livewire\WithFileUploads;

#[Layout('layouts.app')]
class Show extends Component
{
    use AuthorizesRequests;
    use WithFileUploads;

    public Workspace $workspace;

    /**
     * Whether this workspace is published behind a public read-only share link.
     * Bound to the share switch; toggling it persists via the model methods.
     */
    public bool $isShared = false;

    /**
     * Pending file uploads bound to the upload field.
     *
     * @var array<int, TemporaryUploadedFile>
     */
    public array $files = [];

    public function mount(Workspace $workspace): void
    {
        $this->authorize('view', $workspace);

        $this->workspace = $workspace;
        $this->isShared = (bool) $workspace->is_shared;
    }

    /**
     * Toggle the public share link on or off when the owner flips the switch.
     * Re-authorized here so a tampered request cannot share someone else's
     * workspace, and kept in sync with the persisted value.
     */
    public function updatedIsShared(bool $value): void
    {
        $this->authorize('update', $this->workspace);

        if ($value) {
            $this->workspace->enableSharing();
        } else {
            $this->workspace->disableSharing();
        }

        $this->isShared = $this->workspace->is_shared;
    }

    /**
     * Validation rules for the uploaded files (max 20MB each).
     *
     * @return array<string, mixed>
     */
    protected function rules(): array
    {
        return [
            'files' => ['required', 'array'],
            'files.*' => ['file', 'mimes:pdf,docx,txt', 'max:'.(20 * 1024)],
        ];
    }

    /**
     * Store each uploaded file, create its Document, and queue processing.
     */
    public function save(): void
    {
        $this->authorize('update', $this->workspace);

        $this->validate();

        foreach ($this->files as $file) {
            $path = $file->store('documents', config('filesystems.default'));

            $document = $this->workspace->documents()->create([
                'filename' => $file->getClientOriginalName(),
                'file_type' => strtolower($file->getClientOriginalExtension()),
                'file_url' => $path,
                'status' => Document::STATUS_PROCESSING,
            ]);

            ParseAndEmbedDocument::dispatch($document);
        }

        $this->reset('files');

        Flux::toast(variant: 'success', text: __('Uploading and processing your documents.'));
    }

    /**
     * Delete a document (its stored file and embedded chunks) from this workspace.
     *
     * The document is re-fetched scoped to the workspace and authorization is
     * re-checked on every call, so a spoofed id cannot delete a document the
     * current user does not own. Chunks are removed via the foreign-key cascade.
     */
    public function deleteDocument(int $documentId): void
    {
        $this->authorize('update', $this->workspace);

        $document = $this->workspace->documents()->find($documentId);

        abort_if($document === null, 404);

        if ($document->file_url) {
            Storage::disk(config('filesystems.default'))->delete($document->file_url);
        }

        $document->delete();

        Flux::toast(variant: 'success', text: __('Document deleted.'));
    }

    /**
     * Re-queue parsing and embedding for a document that previously failed.
     *
     * Like deleteDocument(), the document is re-fetched scoped to the workspace
     * and authorization is re-checked, so a spoofed id cannot touch a document
     * the current user does not own.
     */
    public function retryDocument(int $documentId): void
    {
        $this->authorize('update', $this->workspace);

        $document = $this->workspace->documents()->find($documentId);

        abort_if($document === null, 404);

        $document->update(['status' => Document::STATUS_PROCESSING]);

        ParseAndEmbedDocument::dispatch($document);

        Flux::toast(text: __('Retrying document processing.'));
    }

    public function render(): View
    {
        $documents = $this->workspace->documents()->latest()->get();

        return view('livewire.workspace.show', [
            'documents' => $documents,
            'isProcessing' => $documents->contains('status', Document::STATUS_PROCESSING),
        ])->title($this->workspace->name);
    }
}
