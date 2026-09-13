# Testing

```bash
php artisan test --compact          # the suite
composer test                       # Pint check + the suite
php artisan test --filter=Bilingual # one file
```

Current state: **127 passed, 1 skipped, 954 assertions.** The skip is the
two-factor authentication test, which skips itself because the Fortify feature is
not enabled in `config/fortify.php`.

---

## What the suite runs against

Two rules shape every test in this project.

### 1. Real PostgreSQL with pgvector. Never SQLite.

Vector search *is* the application. A suite that swapped in SQLite would be
testing a different program — `whereVectorSimilarTo`, the HNSW index, cosine
distance and the `vector` column type would all be absent. So the suite talks to
the same kind of database production does.

Start it before running tests:

```bash
docker compose up -d
```

### 2. No test ever reaches an AI provider.

Every agent and the embeddings gateway are faked, so the suite needs no Gemini
key, spends no quota, and produces identical results offline:

```php
Embeddings::fake([[unitVector(0)]]);
DocumentChatAgent::fake(['According to handbook.pdf, page 3, …']);
SummaryAgent::fake(['A factual summary…']);
SuggestionAgent::fake([['questions' => ['Question A?', 'Question B?', 'Question C?']]]);
QueryTranslationAgent::fake(["LANG: id\nID: …\nEN: …"]);
```

`Tests\TestCase::setUp()` also calls `Http::preventStrayRequests()`, so any HTTP
call that slipped past a fake fails the test loudly instead of silently going out
to the network.

Several tests go further with `->preventStrayPrompts()`, which turns "the agent
was called more times than we faked" into a failure. That is how the caching
tests prove caching actually works: the second call would have no faked response
waiting, so if it reached the agent the test would fail.

---

## Database isolation

The suite uses `RefreshDatabase`, which drops every table in the connection's
`search_path`. That is normally a reason to keep a separate test database —
here it is handled with a Postgres schema instead.

`phpunit.xml` pins the search path:

```xml
<env name="DB_CONNECTION" value="pgsql"/>
<env name="DB_SEARCH_PATH" value="testing"/>
```

So the suite reuses your `.env` connection details — host, port, credentials —
but works entirely inside a schema called `testing`. `RefreshDatabase` can only
drop what it can see there, so it can never touch the `public` schema where your
development data lives.

`AppServiceProvider::keepIsolatedSchemaUsableForVectors()` handles the two things
that need to happen on every connect, before any query runs:

1. `create schema if not exists "testing"` — the schema has to exist before
   `migrate:fresh` creates its own `migrations` table in it.
2. `set search_path to "testing", public` — on the **session** only. The pgvector
   `vector` type and the `<=>` operator live in `public`, so they have to be
   resolvable. The *config* search path stays `testing` alone, and that is what
   bounds what `RefreshDatabase` will drop.

It is a complete no-op for an ordinary `public` connection.

> This is verified, not assumed: the suite was run with `.env.testing` removed
> entirely and the development schema checked afterwards — 3 users and 4
> workspaces still in `public`, 14 tables created in `testing`.

Because the pinning lives in the committed `phpunit.xml`, a fresh clone is safe
by default. You do not need a `.env.testing` file at all; if you keep one, it
still works, and the two agree.

---

## Test helpers

### `unitVector(int $index, int $dimensions = 768): array`

Builds a vector of zeros with a single `1.0` at `$index`.

- A chunk stored at `unitVector(0)` matched against a query embedded to
  `unitVector(0)` scores cosine similarity **1.0** — a guaranteed hit.
- A chunk at `unitVector(1)` against a query at `unitVector(0)` scores exactly
  **0** — below the 0.4 threshold, a guaranteed miss.

That turns "relevant" and "irrelevant" into exact, reproducible facts, so the
retrieval tests assert real pgvector behaviour with no model in the loop.

### `captureStreamedOutput(callable $callback): mixed`

Livewire streams a chat answer by echoing each delta straight to output — right
in a browser, but it floods the test report with raw stream directives. This
wraps the call in an output buffer whose callback discards everything, including
the `ob_flush()` Livewire performs after each delta. Use it around any
`->call('streamAnswer')`.

### `expect(...)->toBeCitation($documentId, $filename, $page)`

Asserts one entry of a citations array in a single readable line.

---

## Coverage by area

| File | Covers |
| --- | --- |
| `SetupSmokeTest` | pgsql is the driver, pgvector is installed, every table exists, a 768-dim vector round-trips through a scoped similarity search |
| `ChunkerTest` | Chunk sizing, overlap, word alignment, over-long single words, empty input |
| `AnswerLanguageTest` | Enum fallbacks, the picker's options, the per-turn language reminder |
| `ParseAndEmbedDocumentTest` | TXT parse → chunk → embed, idempotent re-runs, missing file → failed, the Gemini extraction fallback, DOCX page breaks, job uniqueness, `retry_after` > job timeouts |
| `GenerateSummaryTest` | Summary written and status flipped, chunkless document never prompts, a failed prompt still leaves the document ready |
| `DocumentUploadTest` | Upload creates a processing record and queues the job, type and size validation, extension/contents mismatch, the 10-file cap, ownership, and that the advertised 20 MB matches every layer that enforces it |
| `DocumentCardTest` | Ready / processing / failed states, delete, retry, and the authorization around both |
| `ChatRetrievalTest` | Scoped retrieval, cross-workspace isolation, the similarity threshold, excluding non-ready documents, citation de-duplication |
| `ChatMessageCitationTest` | Persisting both roles, citations derived from the chunks used, Indonesian page words, unnamed passages not cited, the offline fallback message, rate limiting, locked properties, blank questions |
| `BilingualRetrievalTest` | Cross-lingual retrieval, merging phrasings, translation caching, degrading when translation fails, the disabled flag, answer-language persistence |
| `TextFailoverTest` | Advancing to a backup provider on a rate limit, and the single-provider default |
| `SuggestedQuestionsTest` | Chips from summaries, clicking one reuses the send path, no ready document means no prompt, caching, failures degrade and are not cached |
| `ShareWorkspaceTest` | Enabling and disabling sharing, token stability, 404s, owner-only controls hidden, read-only transcript |
| `DashboardTest`, `WorkspaceTest`, `LandingPageTest` | Stats, empty states, CRUD, no cross-user leakage, guest redirects |
| `Auth/*`, `Settings/*` | Fortify flows, profile and security screens |

---

## Conventions

- **Feature tests by default.** Only genuinely pure logic — the chunker, the
  language enum — lives in `tests/Unit`.
- **Factories over hand-built models,** and prefer an existing state
  (`->ready()`, `->failed()`) over setting the attribute inline.
- **One behaviour per test,** with a name that reads as a sentence about the
  application rather than about the method being called.
- **Comment the "why", not the "what".** Several tests explain the trap they are
  guarding — why a chunk is orthogonal, why the second call must hit a cache, why
  an Indonesian fixture is deliberate.
- **Indonesian test data appears only where Indonesian is the subject** — the
  bilingual retrieval tests, the Indonesian page-word citation test, and the
  offline fallback message. Everything else is English.

Create new tests with Artisan so they land in the right place:

```bash
php artisan make:test --pest SomeFeatureTest
php artisan make:test --pest --unit SomeUnitTest
```

---

## Continuous integration

`.github/workflows/tests.yml` runs the suite on push and pull request against a
`pgvector/pgvector:pg17` service container. Because that container is disposable,
CI uses the `public` schema directly.

`.github/workflows/lint.yml` runs Laravel Pint. Before committing:

```bash
vendor/bin/pint --dirty
```
