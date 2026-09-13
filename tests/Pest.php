<?php

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/*
|--------------------------------------------------------------------------
| Test Case
|--------------------------------------------------------------------------
|
| The closure you provide to your test functions is always bound to a specific PHPUnit test
| case class. By default, that class is "PHPUnit\Framework\TestCase". Of course, you may
| need to change it using the "pest()" function to bind different classes or traits.
|
*/

pest()->extend(TestCase::class)
    ->use(RefreshDatabase::class)
    ->in('Feature');

/*
|--------------------------------------------------------------------------
| Expectations
|--------------------------------------------------------------------------
|
| When you're writing tests, you often need to check that values meet certain conditions. The
| "expect()" function gives you access to a set of "expectations" methods that you can use
| to assert different things. Of course, you may extend the Expectation API at any time.
|
*/

expect()->extend('toBeCitation', function (int $documentId, string $filename, ?int $page) {
    return $this->toMatchArray([
        'document_id' => $documentId,
        'filename' => $filename,
        'page_number' => $page,
    ]);
});

/*
|--------------------------------------------------------------------------
| Functions
|--------------------------------------------------------------------------
|
| While Pest is very powerful out-of-the-box, you may have some testing code specific to your
| project that you don't want to repeat in every file. Here you can also expose helpers as
| global functions to help you to reduce the number of lines of code in your test files.
|
*/

/**
 * Run a callback with Livewire's streamed output captured and discarded.
 *
 * Livewire streams a chat answer by echoing each delta straight out, which is
 * exactly right in a browser but floods the test runner's report with raw
 * stream directives. Buffering keeps the suite readable without changing what
 * the component does or what it asserts.
 *
 * @template TReturn
 *
 * @param  callable(): TReturn  $callback
 * @return TReturn
 */
function captureStreamedOutput(callable $callback): mixed
{
    // Livewire calls ob_flush() after every delta, which would push a plain
    // buffer's contents straight through to the terminal. A buffer with a
    // callback that returns an empty string swallows those flushes too.
    ob_start(fn (string $buffer): string => '');

    try {
        return $callback();
    } finally {
        ob_end_clean();
    }
}

/**
 * Build a deterministic unit vector of the configured embedding dimensions with a
 * single 1.0 at the given index. Two such vectors at different indexes are
 * orthogonal (cosine similarity 0), which keeps offline pgvector similarity tests
 * predictable without ever calling the embeddings API.
 *
 * @return array<int, float>
 */
function unitVector(int $index, int $dimensions = 768): array
{
    $vector = array_fill(0, $dimensions, 0.0);
    $vector[$index] = 1.0;

    return $vector;
}
