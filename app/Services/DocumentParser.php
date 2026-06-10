<?php

namespace App\Services;

use Laravel\Ai\AnonymousAgent;
use Laravel\Ai\Files\Document as FileDocument;
use PhpOffice\PhpWord\Element\PageBreak;
use PhpOffice\PhpWord\IOFactory;
use RuntimeException;
use Smalot\PdfParser\Parser as PdfParser;

class DocumentParser
{
    /**
     * Below this many extracted characters the local extraction is treated as
     * empty/insufficient and the Gemini fallback is attempted.
     */
    private const int MIN_USEFUL_CHARS = 16;

    /**
     * Extract a document's text, one entry per page.
     *
     * The primary extractor is chosen by config('rag.parser_primary'); the
     * other one is used as a fallback when the first yields little or no text.
     *
     * @return array<int, array{page_number: int|null, text: string}>
     */
    public function parse(string $absolutePath, string $type): array
    {
        if (! is_file($absolutePath)) {
            throw new RuntimeException("Document file not found at [{$absolutePath}].");
        }

        $type = strtolower($type);
        $primary = config('rag.parser_primary', 'local');

        $pages = $primary === 'gemini'
            ? $this->parseWithGemini($absolutePath, $type)
            : $this->parseLocally($absolutePath, $type);

        if ($this->isInsufficient($pages)) {
            $pages = $primary === 'gemini'
                ? $this->parseLocally($absolutePath, $type)
                : $this->parseWithGemini($absolutePath, $type);
        }

        return $this->normalize($pages);
    }

    /**
     * Extract text locally based on the file type.
     *
     * @return array<int, array{page_number: int|null, text: string}>
     */
    private function parseLocally(string $absolutePath, string $type): array
    {
        return match ($type) {
            'pdf' => $this->parsePdf($absolutePath),
            'docx' => $this->parseDocx($absolutePath),
            'txt' => $this->parseTxt($absolutePath),
            default => throw new RuntimeException("Unsupported document type [{$type}]."),
        };
    }

    /**
     * Extract one entry per PDF page via smalot/pdfparser.
     *
     * @return array<int, array{page_number: int|null, text: string}>
     */
    private function parsePdf(string $absolutePath): array
    {
        $pdf = (new PdfParser)->parseFile($absolutePath);

        $pages = [];

        foreach ($pdf->getPages() as $index => $page) {
            $pages[] = [
                'page_number' => $index + 1,
                'text' => $page->getText(),
            ];
        }

        return $pages;
    }

    /**
     * Extract DOCX text via phpoffice/phpword, splitting on explicit page breaks.
     *
     * @return array<int, array{page_number: int|null, text: string}>
     */
    private function parseDocx(string $absolutePath): array
    {
        $phpWord = IOFactory::load($absolutePath);

        $pages = [];
        $page = 1;
        $buffer = '';

        foreach ($phpWord->getSections() as $section) {
            foreach ($section->getElements() as $element) {
                if ($element instanceof PageBreak) {
                    $pages[] = ['page_number' => $page, 'text' => $buffer];
                    $buffer = '';
                    $page++;

                    continue;
                }

                $buffer .= $this->elementText($element)."\n";
            }
        }

        $pages[] = ['page_number' => $page, 'text' => $buffer];

        return $pages;
    }

    /**
     * Recursively pull text out of a PhpWord element tree.
     */
    private function elementText(object $element): string
    {
        $text = '';

        if (method_exists($element, 'getText')) {
            $value = $element->getText();

            if (is_string($value)) {
                $text .= $value.' ';
            }
        }

        if (method_exists($element, 'getElements')) {
            foreach ($element->getElements() as $child) {
                $text .= $this->elementText($child);
            }
        }

        return $text;
    }

    /**
     * Read a plain-text file as a single page.
     *
     * @return array<int, array{page_number: int|null, text: string}>
     */
    private function parseTxt(string $absolutePath): array
    {
        $content = file_get_contents($absolutePath);

        return [[
            'page_number' => 1,
            'text' => $content === false ? '' : $content,
        ]];
    }

    /**
     * Fallback extraction: hand the raw file to Gemini and ask for its text.
     *
     * Page numbers are not recoverable here, so the whole document is one page.
     *
     * @return array<int, array{page_number: int|null, text: string}>
     */
    private function parseWithGemini(string $absolutePath, string $type): array
    {
        $agent = new AnonymousAgent(
            instructions: 'You are a precise document text extractor. You return the raw, readable text of a document exactly as written, with no commentary, summaries, or formatting of your own.',
            messages: [],
            tools: [],
        );

        $response = $agent->prompt(
            'Extract all readable text from the attached document verbatim. Return only the extracted text.',
            [FileDocument::fromPath($absolutePath)],
        );

        return [[
            'page_number' => null,
            'text' => $response->text,
        ]];
    }

    /**
     * Determine if extraction produced too little text to be useful.
     *
     * @param  array<int, array{page_number: int|null, text: string}>  $pages
     */
    private function isInsufficient(array $pages): bool
    {
        $characters = 0;

        foreach ($pages as $page) {
            $characters += mb_strlen(trim((string) ($page['text'] ?? '')));
        }

        return $characters < self::MIN_USEFUL_CHARS;
    }

    /**
     * Trim text, drop blank pages, and guarantee the documented array shape.
     *
     * @param  array<int, array{page_number: int|null, text: string}>  $pages
     * @return array<int, array{page_number: int|null, text: string}>
     */
    private function normalize(array $pages): array
    {
        $normalized = [];

        foreach ($pages as $page) {
            $text = trim((string) ($page['text'] ?? ''));

            if ($text === '') {
                continue;
            }

            $normalized[] = [
                'page_number' => $page['page_number'] ?? null,
                'text' => $text,
            ];
        }

        return array_values($normalized);
    }
}
