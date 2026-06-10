<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Chunking
    |--------------------------------------------------------------------------
    |
    | Controls how extracted document text is split before embedding. Larger
    | chunks keep more context per vector; the overlap preserves continuity
    | across chunk boundaries so sentences are not cut mid-thought.
    |
    */

    'chunk_size' => (int) env('RAG_CHUNK_SIZE', 1000),
    'chunk_overlap' => (int) env('RAG_CHUNK_OVERLAP', 150),

    /*
    |--------------------------------------------------------------------------
    | Retrieval
    |--------------------------------------------------------------------------
    |
    | How many chunks to pull per question and the minimum cosine similarity
    | (0.0 - 1.0, where 1.0 is identical) a chunk must reach to be considered
    | relevant. Used by whereVectorSimilarTo() during chat retrieval.
    |
    */

    'retrieve_limit' => (int) env('RAG_RETRIEVE_LIMIT', 5),
    'min_similarity' => (float) env('RAG_MIN_SIMILARITY', 0.4),

    /*
    |--------------------------------------------------------------------------
    | Parsing
    |--------------------------------------------------------------------------
    |
    | Which extractor runs first: "local" (smalot/pdfparser + phpoffice/phpword)
    | or "gemini" (Gemini Files API). The other is used as a fallback.
    |
    */

    'parser_primary' => env('RAG_PARSER_PRIMARY', 'local'), // local | gemini

    /*
    |--------------------------------------------------------------------------
    | Text provider failover
    |--------------------------------------------------------------------------
    |
    | The provider/model chain used for every TEXT generation (chat answers,
    | summaries, suggestions). laravel/ai advances to the next entry whenever a
    | FailoverableException is thrown — a rate limit (429), insufficient credits
    | (402), or an overloaded provider (503), i.e. exactly when the Gemini free
    | tier is exhausted. Keyed by the Lab enum's value => model name.
    |
    | The default is Gemini only, so there is no behavior change until you set a
    | fallback model env in production (e.g. AI_TEXT_FALLBACK_GROQ_MODEL). Groq
    | has a free tier and is OpenAI-compatible; Ollama is only realistic when
    | self-hosted (PaaS free tiers cannot run it).
    |
    | This chain is for TEXT ONLY. Embeddings must never failover across models:
    | vectors from a different model live in a different space/dimension and would
    | corrupt cosine retrieval against the stored 768-d vectors (see the "768 is
    | locked in three places" decision). Embeddings rely on caching + job retries.
    |
    */

    'text_failover' => array_filter([
        'gemini' => env('AI_TEXT_MODEL', 'gemini-2.5-flash'),
        'groq' => env('AI_TEXT_FALLBACK_GROQ_MODEL'),
        'ollama' => env('AI_TEXT_FALLBACK_OLLAMA_MODEL'),
    ]),

    /*
    |--------------------------------------------------------------------------
    | Summarization
    |--------------------------------------------------------------------------
    |
    | Maximum number of characters (taken from the start of the document, in
    | chunk order) sent to the SummaryAgent. Caps token usage so summarizing a
    | large document still costs a single, bounded Gemini call.
    |
    */

    'summary_input_chars' => (int) env('RAG_SUMMARY_INPUT_CHARS', 6000),

    /*
    |--------------------------------------------------------------------------
    | Embedding dimensions
    |--------------------------------------------------------------------------
    |
    | Gemini text-embedding-004 outputs 768-dimensional vectors. This number
    | MUST stay in sync in three places:
    |   1. config/ai.php  -> providers.gemini.models.embeddings.dimensions
    |   2. the migration  -> $table->vector('embedding', dimensions: 768)
    |   3. here.
    | Change the embedding model and you must change all three together.
    |
    */

    'embedding_dimensions' => (int) env('AI_EMBEDDINGS_DIMENSIONS', 768),

];
