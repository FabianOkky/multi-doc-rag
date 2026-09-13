# Troubleshooting

---

## A document is stuck at "Processing"

**This is the most common problem, and it is almost always the queue worker.**

Parsing, embedding and summarizing all run in queued jobs. With no worker
consuming the queue, the upload succeeds, the row is created, and nothing else
ever happens.

```bash
php artisan queue:work
```

`composer run dev` starts one for you. If you run the server by hand, you have to
start the worker by hand too.

Still stuck with a worker running:

```bash
php artisan queue:failed     # did the job fail and get parked?
php artisan queue:retry all
php artisan pail             # watch the logs live
```

Check `storage/logs/laravel.log` for the underlying exception. A missing or
invalid `GEMINI_API_KEY` will surface as a failure from the embeddings call.

---

## Uploads fail with "The files failed to upload"

Check the server output for this line:

```
PHP Warning: PHP Request Startup: File upload error - unable to create a temporary file
```

If it is there, PHP could not find a writable temporary directory. `php artisan
serve` runs `php -S` as a **child process** and blanks out every environment
variable outside a small allow-list. On Windows, PHP resolves its temporary
directory from `TMP` / `TEMP` / `USERPROFILE` — with those stripped, uploads fail
at request startup, before any application code runs.

The app fixes this in `AppServiceProvider::keepFileUploadsWorkingUnderArtisanServe()`,
which adds those variables back to `ServeCommand::$passthroughVariables`. If you
see the error, check that method is still in place; there is a test guarding it.

---

## Large uploads are rejected even though the UI says 20 MB

PHP enforces `upload_max_filesize` at request startup, before Laravel's
validation runs, so the limit has to be raised in PHP itself. The default is
usually 2 MB.

There are two supported setups:

**FastCGI (Laravel Herd, Valet, nginx + PHP-FPM)** — nothing to do.
`public/.user.ini` already sets `upload_max_filesize=21M` and
`post_max_size=210M`, and FastCGI reads it automatically.

**`php artisan serve`** — `.user.ini` is *not* read, and `-d` flags do not reach
the child process that handles requests. Point `PHPRC` at the bundled ini file
instead:

```bash
# macOS / Linux
PHPRC=$PWD/deploy/php.ini php artisan serve

# Windows PowerShell
$env:PHPRC = "$PWD\deploy\php.ini"; php artisan serve
```

Verify it took effect — this reports what the *request-handling* process sees:

```bash
php artisan tinker --execute "echo ini_get('upload_max_filesize');"
```

---

## `could not find driver` / `SQLSTATE[08006]`

The `pdo_pgsql` extension is missing or the database is unreachable.

```bash
php -m | grep pdo_pgsql       # must print pdo_pgsql
docker compose ps             # is the container up and healthy?
```

If the container is healthy but the app cannot connect, the host port is probably
different from `DB_PORT`. Compose maps the host side from `DB_PORT`, so the two
must agree.

---

## Port 5432 is already in use

Another PostgreSQL — a system service, or the one bundled with Laravel Herd —
already owns it.

```dotenv
DB_PORT=5433
```

Then `docker compose up -d` again. The container always listens on 5432
internally; only the host side moves.

---

## `type "vector" does not exist`

The `pgvector` extension is not enabled on the database the app is connected to.

- Locally, use the bundled `compose.yaml` (`pgvector/pgvector:pg17`), which ships
  it pre-built. A plain `postgres` image does **not** have it.
- On a managed database, enable the extension in the provider's dashboard, then
  re-run `php artisan migrate`.

If it only fails under the test suite, the session `search_path` is the likely
cause: the `vector` type lives in `public`, so a connection scoped to another
schema must still have `public` on its session search path. That is what
`AppServiceProvider::keepIsolatedSchemaUsableForVectors()` does — see
[Testing](testing.md).

---

## Every answer says it cannot find anything

The documents are not being retrieved. In order of likelihood:

1. **The documents are not `ready`.** Only ready documents are searched. Check
   the cards, and check the worker.
2. **`RAG_MIN_SIMILARITY` is too high.** The default is 0.4. Lower it to 0.3 and
   see whether answers appear.
3. **The embedding model changed.** Vectors from a different model live in a
   different space. Existing chunks become meaningless and must be re-embedded —
   delete the documents and upload them again.
4. **`AI_EMBEDDINGS_DIMENSIONS` no longer matches the column.** It must equal the
   `vector(768)` width in the migration and the value in `config/ai.php`.

---

## Answers arrive but with no sources

Citations are kept only when the answer explicitly names the source — filename,
plus the page when the chunk has one. This is deliberate: retrieved passages are
candidates, not proof that the model used them.

So an answer with no citations usually means the model did not name its source.
If that happens often, check that the agent's instruction to reproduce the source
label verbatim is intact, and that the retrieved chunks actually have
`page_number` values (the Gemini extraction fallback cannot recover pages, so
those cite the filename alone).

---

## An English question gets an Indonesian answer, or the reverse

The conversation history out-weighs the system prompt: after a few turns in one
language, the model keeps going in that language.

The fix already in place is `DocumentChatAgent::turn()`, which repeats the
language requirement on the user's turn — for `auto` as well as the forced
languages. If you are editing prompts, do not remove that; there is a unit test
asserting every case produces a reminder.

To take the guesswork out entirely, set *Reply in* to a specific language in the
chat header. It is stored on the workspace and also governs summaries and
suggested questions.

---

## An answer mentions "the CONTEXT" or "the passages"

The agent is leaking its own prompt scaffolding into user-facing text. The
instructions tell it to word refusals for a reader who cannot see any of that.
Check that rule is still present in `DocumentChatAgent::instructions()`.

---

## Suggested question chips never appear

They need at least one document that is both `ready` **and** has a summary — with
nothing to suggest from, the agent is deliberately never prompted.

If documents are ready and summarized but the chips are still missing, the
suggestion prompt is failing (commonly a rate limit). That failure is intentional
and is not cached, so the chips return on the next visit once the provider
recovers. Check the log for the reported exception.

---

## `429` or "quota exceeded" from Gemini

The free tier is exhausted. Either wait for the quota window, or configure a
fallback text provider:

```dotenv
GROQ_API_KEY=<key>
AI_TEXT_FALLBACK_GROQ_MODEL=llama-3.3-70b-versatile
```

Text generation then advances to the backup on a 429, 402 or 503. Embeddings
never fail over — a different model means a different vector space — so embedding
work relies on the cache and job retries instead.

---

## `Unable to locate file in Vite manifest`

The front-end assets have not been built.

```bash
npm run build      # or: npm run dev
```

---

## Tests fail, or seem to touch development data

The suite pins `DB_SEARCH_PATH=testing` in `phpunit.xml`, so `RefreshDatabase`
can only drop tables inside that isolated schema — development data in `public`
is out of its reach. If you have overridden `DB_SEARCH_PATH` as a real
environment variable, it wins over `phpunit.xml`; unset it.

Other causes:

- The Docker database is not running (`docker compose up -d`).
- A stale config cache: `php artisan config:clear`.
- Stray HTTP: the suite calls `Http::preventStrayRequests()`, so a test that
  reaches the network without a fake fails loudly. That is the intended
  behaviour — add the missing `::fake()`.

---

## Still stuck

```bash
php artisan about                  # environment, drivers, cache state
php artisan config:show rag        # the RAG settings actually in effect
php artisan queue:failed
php artisan pail
```

`storage/logs/laravel.log` has the full stack traces. Every failure path in the
app calls `report()` before degrading, so the cause is recorded even when the UI
shows a friendly message.
