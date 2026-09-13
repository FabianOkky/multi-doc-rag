# The RAG pipeline

Everything between "I dropped a PDF here" and "here is the answer, from page 3".

---

## Ingestion

```mermaid
flowchart LR
    A[Upload<br/>PDF / DOCX / TXT] --> B[DocumentParser<br/>text + page numbers]
    B --> C[Chunker<br/>overlapping windows]
    C --> D[Embeddings::for&lpar;…&rpar;<br/>batches of 100]
    D --> E[(document_chunks<br/>vector&lpar;768&rpar;)]
    E --> F[SummaryAgent<br/>3–4 sentences]
    F --> G[status: ready]
```

### Parsing — `App\Services\DocumentParser`

The parser returns an array of `{page_number, text}` entries. Page numbers are
the entire reason it is page-aware: they become the citation.

| Type | Extractor | Page numbers |
| --- | --- | --- |
| PDF | `smalot/pdfparser` | One entry per real page |
| DOCX | `phpoffice/phpword` | Split on explicit page breaks |
| TXT | `file_get_contents` | A single page |

`config('rag.parser_primary')` picks which extractor runs first — `local` (the
default) or `gemini`. The other one is the fallback, used in two situations:

1. the primary threw, or
2. the primary produced fewer than 16 characters in total — the signal that a PDF
   is a scan, an image-only export, or otherwise not extractable locally.

The Gemini fallback hands the raw file to the Files API and asks for verbatim
text. Page numbers cannot be recovered that way, so those chunks carry
`page_number = null` and their citations name the file alone.

Blank pages are dropped and all text is trimmed before it leaves the parser.

### Chunking — `App\Services\Chunker`

A sliding window of roughly `RAG_CHUNK_SIZE` characters (default 1000) advances
across each page, retaining about `RAG_CHUNK_OVERLAP` characters (default 150) of
the previous chunk.

- Chunks are **word-aligned**: a word is never split, unless a single word is
  itself longer than the chunk size, in which case it is hard-split up front.
- The overlap exists so a sentence spanning a boundary is still fully present in
  at least one chunk. Without it, the exact sentence that answers a question can
  end up cut in half across two vectors and match neither.
- The window always advances by at least one word, so the loop cannot stall on a
  pathological input.

Sizing is a trade-off: larger chunks carry more context per vector but dilute the
similarity score; smaller chunks match sharply but arrive without their
surroundings. 1000/150 is a reasonable default for prose documents.

### Embedding — `ParseAndEmbedDocument`

Chunks are embedded through `Laravel\Ai\Embeddings::for($texts)->generate()` in
batches of 100, and the resulting order is preserved so each vector lands on its
own chunk. Two limits protect the quota:

- **`MAX_CHUNKS = 500`** per document. A single enormous upload cannot consume an
  entire day of free-tier embedding calls.
- **Embedding cache** (`AI_EMBEDDINGS_CACHE`, on by default) keyed by provider,
  model, dimensions and input text. Identical text is never re-sent. This is
  purely a cost saver: the vector for a given input is deterministic, so caching
  cannot change a result.

Writes happen inside a transaction that first deletes the document's existing
chunks, which makes re-running the job idempotent rather than additive. The job's
own safety rails:

| Setting | Value | Why |
| --- | --- | --- |
| `tries` | 3 | Transient provider failures deserve a retry |
| `backoff` | 10s, 30s | Give a rate limit time to clear |
| `timeout` | 300s | Large PDFs take a while |
| `uniqueFor` | 600s | Longer than the longest possible attempt, so duplicates cannot slip in |

Deterministic failures — an unsupported type, a missing file, no extractable text
— are caught in-process, reported, and the document is marked `failed` without
burning the remaining attempts. `failed()` marks the document failed if the job
dies outside that catch (a timeout, or a killed worker).

`config('queue.connections.database.retry_after')` must exceed both job
timeouts, or the queue would hand a still-running job to a second worker. A test
asserts that relationship rather than leaving it to a comment.

### Summarizing — `GenerateSummary`

One prompt per document, never retried (`tries = 1`): the summary is a
convenience and is not worth a second call against a free-tier quota. The agent
sees at most `RAG_SUMMARY_INPUT_CHARS` (6000) characters taken from the start of
the document in chunk order, so summarizing a 500-page report still costs one
bounded call.

Whether the summary succeeds, fails, or there was nothing to summarize, the
document transitions to `ready` in a single update — its chunks are already
embedded and queryable without a summary.

---

## Retrieval

```mermaid
flowchart LR
    Q[Question] --> T[QueryTranslator<br/>ID + EN, cached]
    T --> E[Embed every phrasing<br/>one batch call]
    E --> S[whereVectorSimilarTo<br/>scoped by workspace_id]
    S --> M[Merge hits<br/>keep best distance]
    M --> C[Context block<br/>+ candidate citations]
    C --> A[DocumentChatAgent]
    A --> ANS[Streamed answer]
    ANS --> F[Keep only cited sources]
```

### The search itself — `App\Services\Retriever`

Each phrasing runs one query:

```php
DocumentChunk::query()
    ->where('workspace_id', $workspace->id)
    ->whereHas('document', fn ($query) => $query->where('status', Document::STATUS_READY))
    ->select(['id', 'document_id', 'page_number', 'content'])
    ->selectVectorDistance('embedding', $vector, as: 'distance')
    ->with('document:id,filename')
    ->whereVectorSimilarTo('embedding', $vector, (float) config('rag.min_similarity'))
    ->limit($limit)
    ->get();
```

Four things are load-bearing here:

- **`where('workspace_id', …)`** is the isolation boundary. It is a plain SQL
  predicate, not an application-level filter applied afterwards, so there is no
  window in which another workspace's chunk exists in the result set.
- **`whereHas('document', … 'ready')`** stops a half-processed or failed document
  from being quoted as evidence. A document that failed mid-run may still have
  chunks from an earlier attempt; those must not be cited.
- **`selectVectorDistance(…, as: 'distance')`** exposes the cosine distance so
  hits from different query phrasings are comparable to each other.
- The vector is passed as a **PHP array**, never a string. That keeps the search
  pure SQL and is what lets the entire test suite run offline.

Results from every phrasing are merged into one set keyed by chunk id, keeping
the smallest distance seen for each chunk, then sorted by distance and truncated
to `RAG_RETRIEVE_LIMIT` (default 5).

### Relevance threshold

`RAG_MIN_SIMILARITY` (default 0.4, on a 0–1 cosine scale) is the floor a chunk
must clear to be considered relevant at all. It is what makes "I don't know" an
achievable answer: if nothing clears the bar, the context block is empty, the
agent is told so explicitly, and it says it cannot answer instead of
rationalising over whatever happened to rank highest.

- Raise it for a tighter, more literal corpus.
- Lower it if good answers are being missed — but expect more loosely related
  passages in the context.

### Context assembly

Each surviving chunk becomes a labelled block:

```
[handbook.pdf, page 3]
The full onboarding procedure is described here…

[handbook.pdf, page 4]
…
```

The label is always English (`filename, page N`) even when the answer will be
written in Indonesian, because the agent is instructed to reproduce it verbatim.
Keeping it stable across languages is what makes a citation matchable back to the
chunk it came from.

---

## Citations

This is the part that makes the app trustworthy, so it is worth being precise
about what guarantees what.

**Retrieved passages are candidates, not proof.** Getting five chunks back does
not mean the model used all five. Presenting all of them as evidence would put
sources under an answer that never referenced them.

So `RetrievalResult::citationsUsedBy($answer)` keeps a candidate only if:

1. the answer text contains the filename, **and**
2. when the chunk has a page number, the answer names that page near a page word
   — `page 3`, `p. 3`, `hal. 3` or `halaman 3`.

The Indonesian page words are there because answers are written in the language
of the question. An Indonesian answer cites "hal. 3", and without recognising
that, a perfectly good answer would be displayed with no sources at all. There is
a test for exactly that case.

Candidates are de-duplicated per `document_id` + `page_number`, so two chunks
from the same page produce one citation.

The net effect: **the model never writes the citation list.** It writes prose;
the application derives the citations from the chunks it retrieved and keeps only
those the prose named. A model cannot fabricate a source, because fabricating one
would simply fail to match any candidate and be dropped.

---

## Starter questions — `App\Services\Suggester`

Suggested questions are generated from the per-document **summaries**, never the
full text — one bounded prompt for the whole workspace.

The cache key is `workspace:{id}:suggestions:{language}:{fingerprint}`, where the
fingerprint is an MD5 of every ready document's id and summary. Adding,
removing or re-summarizing a document changes the fingerprint and therefore
invalidates the suggestions automatically — there is no cache to flush by hand.

Two deliberate behaviours:

- With no ready, summarized document, the agent is **never prompted**. There is
  nothing to suggest from, and the call would be wasted.
- A failed prompt is **not cached**. Caching a failure would hide the chips for a
  full day over one rate-limited minute.

---

## Tuning summary

| Variable | Default | Raise it to… | Lower it to… |
| --- | --- | --- | --- |
| `RAG_CHUNK_SIZE` | 1000 | Keep more context per vector | Match shorter, sharper passages |
| `RAG_CHUNK_OVERLAP` | 150 | Protect sentences across boundaries | Store fewer, less redundant chunks |
| `RAG_RETRIEVE_LIMIT` | 5 | Give the model more evidence (more tokens) | Keep answers tight and cheap |
| `RAG_MIN_SIMILARITY` | 0.4 | Refuse to answer unless the match is strong | Surface loosely related material |
| `RAG_SUMMARY_INPUT_CHARS` | 6000 | Summarize from more of the document | Spend fewer tokens per document |

See [Configuration](configuration.md) for the full list.
