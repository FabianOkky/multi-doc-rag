# Configuration

Every setting lives in `.env`. The committed `.env.example` is the template and
contains no secrets. `config/rag.php` documents the RAG-specific values inline.

---

## Application

```dotenv
APP_NAME="Multi-Doc RAG"
APP_ENV=local              # local | testing | production
APP_KEY=                   # php artisan key:generate
APP_DEBUG=true             # ALWAYS false in production
APP_URL=http://localhost:8000

APP_LOCALE=en
APP_FALLBACK_LOCALE=en
```

`APP_LOCALE` affects interface strings and is also the tie-breaker when the
offline language check in `AnswerLanguage` cannot tell Indonesian from English.

---

## Database — PostgreSQL + pgvector

```dotenv
DB_CONNECTION=pgsql
DB_HOST=127.0.0.1
DB_PORT=5432               # 5433 if something already owns 5432
DB_DATABASE=multi_doc_rag
DB_USERNAME=postgres
DB_PASSWORD=secret
DB_SSLMODE=prefer          # require for a managed/hosted database
DB_SEARCH_PATH=public
```

The database must support `pgvector`. Locally that is the bundled
`compose.yaml`, which runs `pgvector/pgvector:pg17` and reads these same values —
Compose maps the host port from `DB_PORT` while the container always listens on
5432 internally.

There is no SQLite fallback anywhere, by design: vector search is the app.

**`DB_SEARCH_PATH`** is the Postgres schema the app works in. Development and
production use `public`. The test suite pins it to `testing` in `phpunit.xml`,
which is what lets the suite share a database with development and still never
touch development data — see [Testing](testing.md).

For a hosted database (Neon, Railway, Supabase, …), point `DB_*` at it and set
`DB_SSLMODE=require`.

---

## AI — Google Gemini

```dotenv
GEMINI_API_KEY=

AI_TEXT_MODEL=gemini-2.5-flash
AI_EMBEDDINGS_MODEL=gemini-embedding-001
AI_EMBEDDINGS_DIMENSIONS=768
AI_EMBEDDINGS_CACHE=true
```

A free key comes from [Google AI Studio](https://aistudio.google.com/app/apikey).

> ### `AI_EMBEDDINGS_DIMENSIONS` is locked in three places
>
> The number must be identical in all of them:
>
> 1. `config/ai.php` → `providers.gemini.models.embeddings.dimensions`
> 2. the migration → `$table->vector('embedding', dimensions: 768)`
> 3. `config/rag.php` → `embedding_dimensions`
>
> Changing the embedding model means changing all three **and** re-embedding
> every existing document. Vectors of a different width simply will not fit the
> column; vectors from a different model of the same width fit but are
> meaningless, which is worse — retrieval silently degrades instead of failing.

`AI_EMBEDDINGS_CACHE` caches embeddings by provider, model, dimensions and input
text. It is a pure quota saver and is safe to leave on: the vector for a given
input is deterministic, so caching cannot change a result.

---

## Text provider failover

```dotenv
GROQ_API_KEY=
AI_TEXT_FALLBACK_GROQ_MODEL=          # e.g. llama-3.3-70b-versatile

OLLAMA_URL=http://localhost:11434
AI_TEXT_FALLBACK_OLLAMA_MODEL=
```

`config('rag.text_failover')` builds an ordered provider chain from these. The
AI SDK advances to the next entry when it hits a `FailoverableException` — a rate
limit (429), insufficient credits (402), or an overloaded provider (503). That is
exactly the shape of an exhausted Gemini free tier.

Both fallbacks are blank by default, so the shipped behaviour is Gemini only and
there is no change until you opt in. Groq has a free tier and is
OpenAI-compatible; Ollama is only realistic if you self-host, since PaaS free
tiers cannot run it.

**The chain covers text generation only** — chat answers, summaries, suggested
questions. Embeddings are deliberately excluded: vectors from a different model
live in a different space and dimension, and mixing them would corrupt cosine
retrieval against everything already stored.

One consequence worth knowing: because a fallback model may not support
`json_schema`, no agent on the chat hot path uses structured output. See
[Bilingual retrieval](bilingual-retrieval.md).

---

## RAG parameters

```dotenv
RAG_CHUNK_SIZE=1000
RAG_CHUNK_OVERLAP=150
RAG_RETRIEVE_LIMIT=5
RAG_MIN_SIMILARITY=0.4
RAG_MULTILINGUAL_QUERY=true
RAG_PARSER_PRIMARY=local
RAG_SUMMARY_INPUT_CHARS=6000
```

| Variable | Default | What it does |
| --- | --- | --- |
| `RAG_CHUNK_SIZE` | 1000 | Target characters per chunk. Larger keeps more context per vector but dilutes similarity. |
| `RAG_CHUNK_OVERLAP` | 150 | Characters carried over between consecutive chunks, so a sentence is never lost at a boundary. |
| `RAG_RETRIEVE_LIMIT` | 5 | Chunks pulled per question. More evidence, more tokens. |
| `RAG_MIN_SIMILARITY` | 0.4 | Cosine similarity floor (0–1). This is what makes "I don't know" reachable. |
| `RAG_MULTILINGUAL_QUERY` | true | Rewrite every question into Indonesian **and** English before searching. Costs one small cached prompt per distinct question. |
| `RAG_PARSER_PRIMARY` | `local` | Which extractor runs first: `local` (pdfparser + phpword) or `gemini` (Files API). The other is the fallback. |
| `RAG_SUMMARY_INPUT_CHARS` | 6000 | Cap on the text sent to the summary agent, so one document costs one bounded call. |

Full reasoning for each in [RAG pipeline](rag-pipeline.md).

---

## Queue, cache, session, storage

```dotenv
QUEUE_CONNECTION=database
DB_QUEUE_RETRY_AFTER=360

CACHE_STORE=database
SESSION_DRIVER=database
FILESYSTEM_DISK=local
```

**A queue worker is not optional.** Parsing, embedding and summarizing all run in
queued jobs. Without a worker, every upload stays at "Processing" forever.

`DB_QUEUE_RETRY_AFTER` must be **greater than the longest job timeout**
(`ParseAndEmbedDocument::$timeout` is 300s), or the queue will hand a
still-running job to a second worker and the same document will be embedded
twice. The default of 360 leaves a margin. A test asserts this relationship
rather than trusting a comment.

`FILESYSTEM_DISK=local` keeps uploads on the application server. On a platform
with an ephemeral filesystem, point this at S3-compatible storage or accept that
uploaded originals vanish on redeploy — the embedded chunks survive in Postgres
either way, so existing documents stay searchable; only re-parsing would fail.

---

## Upload limits

The app accepts up to **10 files per upload, 20 MB each**, restricted to PDF,
DOCX and TXT. That limit is enforced in three layers, and all three have to agree:

| Layer | Where |
| --- | --- |
| Livewire validation | `App\Livewire\Workspace\Show::rules()` — `max:20480` KB, MIME + extension, and a contents-match check |
| Livewire temporary uploads | `config/livewire.php` → `temporary_file_upload.rules` |
| PHP itself | `public/.user.ini` (FastCGI) and `deploy/php.ini` (`artisan serve`) — `upload_max_filesize=21M`, `post_max_size=210M` |

Which file applies depends on how the app is served:

- **FastCGI** (Laravel Herd, Valet, nginx + PHP-FPM) reads `public/.user.ini`
  automatically. Nothing to configure.
- **`php artisan serve`** reads neither. It spawns `php -S` as a *child* process,
  so `.user.ini` does not apply and `-d` flags passed to the parent command never
  reach the process handling requests. Set `PHPRC` instead — it is an environment
  variable, so the child inherits it:

  ```bash
  PHPRC=$PWD/deploy/php.ini php artisan serve
  ```

  `AppServiceProvider` adds `PHPRC` (along with `TMP`, `TEMP` and `USERPROFILE`)
  to Laravel's environment pass-through list, which otherwise strips them. On
  Windows those temp variables are not optional: without them PHP cannot create
  the temporary file an upload needs, and every upload fails at request startup.

A feature test asserts that the advertised 20 MB matches what is actually
configured, so the three layers cannot drift apart silently.

---

## Production checklist

```dotenv
APP_ENV=production
APP_DEBUG=false
APP_KEY=<a fresh key>
DB_SSLMODE=require
QUEUE_CONNECTION=database
GEMINI_API_KEY=<your key>
```

`AppServiceProvider` reacts to `APP_ENV=production` by enabling
`DB::prohibitDestructiveCommands()` and enforcing strong password rules
(12 characters, mixed case, numbers, symbols, checked against known breaches).
Neither is active locally, so local test accounts stay easy to create.

Full walkthrough in [Deployment](deployment.md).

---

## Never commit

`.env`, `.env.testing` and `.env.production` are all git-ignored. Only the
secret-free `.env.example` is committed. If a key is ever exposed, rotate it at
the provider — removing it from a later commit does not remove it from history.
