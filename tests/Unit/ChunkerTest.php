<?php

use App\Services\Chunker;

beforeEach(function () {
    $this->chunker = new Chunker;
});

it('returns no chunks for blank text', function () {
    expect($this->chunker->chunk('   ', 100, 20))->toBe([]);
});

it('returns a single chunk when the text fits in one window', function () {
    expect($this->chunker->chunk('hello world', 100, 20))->toBe(['hello world']);
});

it('splits text into overlapping, word-aligned chunks', function () {
    $chunks = $this->chunker->chunk('aaaa bbbb cccc dddd eeee', size: 10, overlap: 4);

    expect($chunks)->toBe([
        'aaaa bbbb',
        'bbbb cccc',
        'cccc dddd',
        'dddd eeee',
    ]);
});

it('never exceeds the size and never splits a word that fits', function () {
    $size = 100;
    $text = trim(str_repeat('lorem ', 400));

    $chunks = $this->chunker->chunk($text, size: $size, overlap: 20);

    expect(count($chunks))->toBeGreaterThan(1);

    foreach ($chunks as $chunk) {
        expect(mb_strlen($chunk))->toBeLessThanOrEqual($size);

        foreach (explode(' ', $chunk) as $word) {
            expect($word)->toBe('lorem');
        }
    }
});

it('produces overlapping chunks that share trailing words', function () {
    $chunks = $this->chunker->chunk('one two three four five six seven eight', size: 20, overlap: 8);

    // Each chunk after the first should begin with a word carried over from the previous one.
    for ($i = 1; $i < count($chunks); $i++) {
        $previousWords = explode(' ', $chunks[$i - 1]);
        $currentWords = explode(' ', $chunks[$i]);

        expect($previousWords)->toContain($currentWords[0]);
    }
});

it('hard-splits a single word longer than the chunk size', function () {
    $text = str_repeat('x', 250);

    $chunks = $this->chunker->chunk($text, size: 100, overlap: 10);

    expect($chunks)->toHaveCount(3)
        ->and(mb_strlen($chunks[0]))->toBe(100)
        ->and(mb_strlen($chunks[1]))->toBe(100)
        ->and(mb_strlen($chunks[2]))->toBe(50)
        ->and(implode('', $chunks))->toBe($text);
});
