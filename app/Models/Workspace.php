<?php

namespace App\Models;

use App\Services\AnswerLanguage;
use Database\Factories\WorkspaceFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

#[Fillable(['name', 'answer_language'])]
class Workspace extends Model
{
    /** @use HasFactory<WorkspaceFactory> */
    use HasFactory;

    /**
     * The length of a generated public share token.
     */
    private const int SHARE_TOKEN_LENGTH = 40;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'is_shared' => 'boolean',
            'answer_language' => AnswerLanguage::class,
        ];
    }

    /**
     * The language answers, summaries, and suggestions are written in for this
     * workspace. Retrieval is cross-lingual either way; this only controls output.
     */
    public function answerLanguage(): AnswerLanguage
    {
        return $this->answer_language ?? AnswerLanguage::Auto;
    }

    /**
     * Turn on the public read-only share link, generating a unique token the
     * first time so re-enabling later keeps the same link.
     */
    public function enableSharing(): void
    {
        if ($this->share_token === null) {
            $this->share_token = $this->generateUniqueShareToken();
        }

        $this->is_shared = true;
        $this->save();
    }

    /**
     * Turn off the public share link. The token is kept so the owner can
     * re-enable the same link; meanwhile the public route returns 404.
     */
    public function disableSharing(): void
    {
        $this->is_shared = false;
        $this->save();
    }

    /**
     * Generate a share token that is not already used by another workspace.
     */
    protected function generateUniqueShareToken(): string
    {
        do {
            $token = Str::random(self::SHARE_TOKEN_LENGTH);
        } while (static::query()->where('share_token', $token)->exists());

        return $token;
    }

    /**
     * The user that owns the workspace.
     *
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * The documents uploaded to this workspace (populated in Phase 02).
     *
     * @return HasMany<Document, $this>
     */
    public function documents(): HasMany
    {
        return $this->hasMany(Document::class);
    }

    /**
     * The chat sessions opened against this workspace.
     *
     * @return HasMany<ChatSession, $this>
     */
    public function chatSessions(): HasMany
    {
        return $this->hasMany(ChatSession::class);
    }
}
