<?php

namespace App\Models;

use Database\Factories\ChatMessageFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['chat_session_id', 'role', 'content', 'citations'])]
class ChatMessage extends Model
{
    /** @use HasFactory<ChatMessageFactory> */
    use HasFactory;

    public const string ROLE_USER = 'user';

    public const string ROLE_ASSISTANT = 'assistant';

    /**
     * Get the attributes that should be cast.
     *
     * Citations are stored as JSON: a list of {document_id, filename, page_number}
     * derived from the chunks that grounded the answer.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'citations' => 'array',
        ];
    }

    /**
     * The session this message belongs to.
     *
     * @return BelongsTo<ChatSession, $this>
     */
    public function chatSession(): BelongsTo
    {
        return $this->belongsTo(ChatSession::class);
    }
}
