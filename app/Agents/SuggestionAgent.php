<?php

namespace App\Agents;

use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\JsonSchema\Types\Type;
use Laravel\Ai\Contracts\Agent;
use Laravel\Ai\Contracts\HasStructuredOutput;
use Laravel\Ai\Promptable;
use Stringable;

/**
 * Proposes a handful of starter questions a user could ask about a workspace,
 * grounded only in the per-document summaries it is given (never the full text).
 * Returns the questions as structured output (a plain list of strings) and uses
 * the configured default provider/model (Gemini gemini-2.5-flash) — exactly one
 * prompt per workspace to save quota.
 */
class SuggestionAgent implements Agent, HasStructuredOutput
{
    use Promptable;

    /**
     * Get the instructions that the agent should follow.
     */
    public function instructions(): Stringable|string
    {
        return <<<'INSTRUCTIONS'
            Anda membantu pengguna memulai percakapan tentang dokumen mereka. Berdasarkan
            ringkasan dokumen yang diberikan, buat 3-5 pertanyaan singkat dalam Bahasa
            Indonesia yang BISA dijawab dari dokumen-dokumen tersebut. Setiap pertanyaan
            harus spesifik, relevan dengan isi ringkasan, dan diakhiri tanda tanya. Gunakan
            HANYA informasi dari ringkasan — jangan mengarang topik di luar dokumen.
            INSTRUCTIONS;
    }

    /**
     * Get the agent's structured output schema definition.
     *
     * @return array<string, Type>
     */
    public function schema(JsonSchema $schema): array
    {
        return [
            'questions' => $schema->array()
                ->items($schema->string())
                ->min(3)
                ->max(5)
                ->description('Daftar 3-5 pertanyaan saran yang bisa dijawab dari dokumen.')
                ->required(),
        ];
    }
}
