<?php

namespace App\Services;

class Chunker
{
    /**
     * Split text into overlapping, word-aligned chunks.
     *
     * A sliding window of (roughly) `$size` characters advances across the
     * text, retaining about `$overlap` characters of the previous chunk so a
     * sentence is never lost across a boundary. Words are never split unless a
     * single word is itself longer than `$size`.
     *
     * @return array<int, string>
     */
    public function chunk(string $text, ?int $size = null, ?int $overlap = null): array
    {
        $size = $size ?? (int) config('rag.chunk_size', 1000);
        $overlap = $overlap ?? (int) config('rag.chunk_overlap', 150);

        $size = max(1, $size);
        $overlap = max(0, min($overlap, $size - 1));

        $text = trim($text);

        if ($text === '') {
            return [];
        }

        $words = $this->splitIntoWords($text, $size);
        $total = count($words);

        $chunks = [];
        $start = 0;

        while ($start < $total) {
            [$end, $chunkWords] = $this->packChunk($words, $start, $size);

            $chunks[] = implode(' ', $chunkWords);

            if ($end >= $total) {
                break;
            }

            $start = $this->nextStart($words, $start, $end, $overlap);
        }

        return $chunks;
    }

    /**
     * Split text on whitespace, hard-splitting any single word longer than the
     * chunk size so the main loop never has to break a word mid-stream.
     *
     * @return array<int, string>
     */
    private function splitIntoWords(string $text, int $size): array
    {
        $words = preg_split('/\s+/u', $text, -1, PREG_SPLIT_NO_EMPTY) ?: [];

        $normalized = [];

        foreach ($words as $word) {
            if (mb_strlen($word) <= $size) {
                $normalized[] = $word;

                continue;
            }

            foreach (mb_str_split($word, $size) as $piece) {
                $normalized[] = $piece;
            }
        }

        return $normalized;
    }

    /**
     * Greedily pack words into a chunk starting at $start without exceeding $size.
     *
     * @param  array<int, string>  $words
     * @return array{0: int, 1: array<int, string>} The next index and the packed words.
     */
    private function packChunk(array $words, int $start, int $size): array
    {
        $total = count($words);
        $length = 0;
        $packed = [];
        $index = $start;

        while ($index < $total) {
            $wordLength = mb_strlen($words[$index]);
            $addition = $packed === [] ? $wordLength : $wordLength + 1;

            if ($packed !== [] && $length + $addition > $size) {
                break;
            }

            $packed[] = $words[$index];
            $length += $addition;
            $index++;
        }

        return [$index, $packed];
    }

    /**
     * Walk back from $end to retain ~$overlap characters of trailing words,
     * always making forward progress past $start.
     *
     * @param  array<int, string>  $words
     */
    private function nextStart(array $words, int $start, int $end, int $overlap): int
    {
        if ($overlap === 0) {
            return $end;
        }

        $retained = 0;
        $index = $end - 1;

        while ($index > $start) {
            $wordLength = mb_strlen($words[$index]);
            $addition = $index === $end - 1 ? $wordLength : $wordLength + 1;

            if ($retained + $addition > $overlap) {
                break;
            }

            $retained += $addition;
            $index--;
        }

        return max($start + 1, $index + 1);
    }
}
