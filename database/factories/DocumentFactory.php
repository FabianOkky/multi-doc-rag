<?php

namespace Database\Factories;

use App\Models\Document;
use App\Models\Workspace;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Document>
 */
class DocumentFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $type = fake()->randomElement(['pdf', 'docx', 'txt']);

        return [
            'workspace_id' => Workspace::factory(),
            'filename' => fake()->slug(2).'.'.$type,
            'file_type' => $type,
            'file_url' => 'documents/'.fake()->uuid().'.'.$type,
            'summary' => null,
            'status' => Document::STATUS_PROCESSING,
        ];
    }

    /**
     * Indicate that the document has finished processing.
     */
    public function ready(): static
    {
        return $this->state(fn (array $attributes): array => [
            'status' => Document::STATUS_READY,
            'summary' => fake()->paragraph(),
        ]);
    }

    /**
     * Indicate that the document failed to process.
     */
    public function failed(): static
    {
        return $this->state(fn (array $attributes): array => [
            'status' => Document::STATUS_FAILED,
        ]);
    }
}
