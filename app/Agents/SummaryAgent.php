<?php

namespace App\Agents;

use Laravel\Ai\Contracts\Agent;
use Laravel\Ai\Promptable;
use Stringable;

/**
 * Summarizes an uploaded document into a few factual sentences, grounded only
 * in the supplied text. Uses the configured default provider/model
 * (Gemini gemini-2.5-flash) — exactly one prompt per document to save quota.
 */
class SummaryAgent implements Agent
{
    use Promptable;

    /**
     * Get the instructions that the agent should follow.
     */
    public function instructions(): Stringable|string
    {
        return <<<'INSTRUCTIONS'
            Anda adalah asisten yang meringkas dokumen. Ringkas dokumen yang diberikan dalam
            3-4 kalimat menggunakan Bahasa Indonesia yang jelas dan faktual. Gunakan HANYA
            informasi yang ada di dalam teks dokumen — jangan menambah, menafsirkan berlebihan,
            atau mengarang fakta apa pun di luar dokumen. Tulis sebagai satu paragraf teks biasa
            tanpa format markdown, tanpa judul, dan tanpa poin-poin.
            INSTRUCTIONS;
    }
}
