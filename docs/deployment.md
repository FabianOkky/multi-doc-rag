# Deployment

The app needs three things in production: a PHP web process, a **queue worker**,
and a PostgreSQL database with `pgvector`. The bundled `Procfile` covers the
first two on platforms like Railway and Render.

---

## The Procfile

```
web:    php artisan migrate --force && php artisan config:cache && PHPRC=$PWD/deploy/php.ini php artisan serve --host=0.0.0.0 --port=$PORT
worker: php artisan queue:work --tries=3 --max-time=3600 --sleep=3 --backoff=10
```

**Both processes are required.** Without the worker, uploads are accepted and
then sit at "Processing" forever, because parsing, embedding and summarizing all
happen in queued jobs.

### About `PHPRC`

`php artisan serve` spawns `php -S` as a *child* process, and `-d` flags passed to
the parent never reach it — so the usual `php -d upload_max_filesize=21M artisan
serve` silently does nothing, and uploads stay capped at PHP's 2 MB default.

`PHPRC` is an environment variable, and the child inherits it, so it is the one
mechanism that actually works here. `deploy/php.ini` raises the limits to match
the 20 MB the UI advertises. (`AppServiceProvider` adds `PHPRC` to Laravel's
environment pass-through list, which otherwise strips it.)

If you deploy behind a real FastCGI server (nginx + PHP-FPM), you do not need any
of this — `public/.user.ini` already carries the same limits, and you should serve
`public/` directly rather than using `artisan serve`.

---

## Environment

```dotenv
APP_NAME="Multi-Doc RAG"
APP_ENV=production
APP_DEBUG=false
APP_KEY=<generate a fresh one>
APP_URL=https://your-domain

DB_CONNECTION=pgsql
DB_HOST=<managed postgres host>
DB_PORT=5432
DB_DATABASE=<database>
DB_USERNAME=<user>
DB_PASSWORD=<password>
DB_SSLMODE=require
DB_SEARCH_PATH=public

QUEUE_CONNECTION=database
DB_QUEUE_RETRY_AFTER=360
CACHE_STORE=database
SESSION_DRIVER=database

GEMINI_API_KEY=<your key>
AI_TEXT_MODEL=gemini-2.5-flash
AI_EMBEDDINGS_MODEL=gemini-embedding-001
AI_EMBEDDINGS_DIMENSIONS=768
AI_EMBEDDINGS_CACHE=true
```

Generate the key locally and paste the value — never reuse a development key:

```bash
php artisan key:generate --show
```

Setting `APP_ENV=production` also switches on two guards in `AppServiceProvider`:
`DB::prohibitDestructiveCommands()` (so a stray `migrate:fresh` cannot wipe
production) and strict password rules for new accounts.

---

## The database

Any managed PostgreSQL that supports `pgvector` works — Neon, Railway, Supabase,
Render, RDS. Enable the extension if the provider requires an explicit toggle;
otherwise the first migration issues `CREATE EXTENSION IF NOT EXISTS vector`
itself.

`DB_SSLMODE=require` for anything not on localhost.

Storage sizing, roughly: one chunk per ~1000 characters, each carrying a
768-dimension `float4` vector — about 3 KB per chunk for the vector, plus the
chunk text. A 100-page document is on the order of a few hundred chunks.

---

## Deploying to Railway

1. Create a project from the repository.
2. Add a **PostgreSQL** database and enable `pgvector`.
3. Set the environment variables above. Point `DB_*` at the database — if the
   provider exposes only a connection URL, split it into the individual `DB_*`
   values.
4. Railway reads the `Procfile` and offers both processes. Deploy **both** the
   `web` and `worker` services from the same repository and environment.
5. First deploy runs `php artisan migrate --force`, which creates the tables,
   enables `pgvector` and builds the HNSW index.

## Deploying to Render

1. **Web Service** — build `composer install --no-dev --optimize-autoloader && npm ci && npm run build`, start with the `web` line from the Procfile.
2. **Background Worker** — same repository, start with the `worker` line.
3. **PostgreSQL** — Render's managed Postgres supports `pgvector`.
4. Share one environment group between the web service and the worker so they
   agree on `APP_KEY`, the database and the API key.

---

## File storage

`FILESYSTEM_DISK=local` keeps uploaded originals on the application server. Most
PaaS filesystems are ephemeral, so those files disappear on redeploy.

What that does and does not affect:

- **Already-embedded documents keep working.** Chunks and embeddings live in
  Postgres, so retrieval, citations and chat are unaffected.
- **Re-parsing a document fails.** The Retry action on a failed document needs
  the original file.

For durable originals, configure an S3-compatible disk and set `FILESYSTEM_DISK`
to it. The app reads `config('filesystems.default')` everywhere it touches
storage, so no code changes are needed.

---

## Cost control on a free tier

The defaults are already tuned for the Gemini free tier:

| Guard | Effect |
| --- | --- |
| `MAX_CHUNKS = 500` per document | Caps embedding calls for one upload |
| Batches of 100 chunks per embedding call | Fewer, larger requests |
| `AI_EMBEDDINGS_CACHE=true` | Identical text is never embedded twice |
| `RAG_SUMMARY_INPUT_CHARS=6000` | One bounded summary call per document |
| `GenerateSummary` never retries | A failed summary costs exactly one call |
| Suggestions cached per set of summaries | One prompt per workspace, not per visit |
| Query translations cached for 7 days | One prompt per distinct question |
| Chat rate limit: 10/minute/user/workspace | Bounds the worst case |

To cut spend further, set `RAG_MULTILINGUAL_QUERY=false` (removes one prompt per
distinct question) and lower `RAG_RETRIEVE_LIMIT` (fewer context tokens per
answer).

### Surviving a rate limit

Set a fallback text provider so an exhausted Gemini quota degrades instead of
failing:

```dotenv
GROQ_API_KEY=<key>
AI_TEXT_FALLBACK_GROQ_MODEL=llama-3.3-70b-versatile
```

This affects text generation only. Embeddings must never fail over to a different
model — a different model means a different vector space, which would silently
corrupt retrieval against everything already stored.

---

## Post-deploy checklist

- [ ] `APP_DEBUG=false`, and a fresh `APP_KEY`
- [ ] The migration ran and `pgvector` is enabled
- [ ] **The worker process is running**, not just the web process
- [ ] `DB_QUEUE_RETRY_AFTER` (360) is still greater than every job timeout (max 300)
- [ ] An upload completes end to end and reaches *Ready*
- [ ] An answer arrives with a correct citation
- [ ] A question outside the documents is refused
- [ ] A share link works signed out, and 404s once disabled
- [ ] `/s/` is disallowed in `public/robots.txt`
- [ ] HTTPS is enforced and `DB_SSLMODE=require`

Work through [the manual test plan](manual-test-plan.md) against the deployed
URL for a fuller pass.

---

## Operating it

```bash
php artisan queue:failed          # jobs that exhausted their retries
php artisan queue:retry all
php artisan pail                  # live log tail
```

A document stuck at *Processing* almost always means the worker is not running.
See [Troubleshooting](troubleshooting.md).
