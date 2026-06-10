<?php

namespace Database\Factories;

use App\Models\ChatMessage;
use App\Models\ChatSession;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ChatMessage>
 */
class ChatMessageFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'chat_session_id' => ChatSession::factory(),
            'role' => ChatMessage::ROLE_USER,
            'content' => fake()->sentence(),
            'citations' => null,
        ];
    }

    /**
     * Indicate that the message is an assistant reply.
     */
    public function assistant(): static
    {
        return $this->state(fn (array $attributes): array => [
            'role' => ChatMessage::ROLE_ASSISTANT,
        ]);
    }
}
