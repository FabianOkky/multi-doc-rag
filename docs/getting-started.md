# Getting started

This gets Multi-Doc RAG running locally: a PostgreSQL + pgvector database in
Docker, the Laravel app, and a queue worker to do the parsing and embedding.

---

## 1. Prerequisites

| Requirement | Why |
| --- | --- |
| **PHP 8.3+** with `pdo_pgsql` | The app. Vector columns are read and written over the Postgres driver. |
| **Composer 2** | PHP dependencies. |
| **Node.js 18+ and npm** | Building the Tailwind / Livewire front-end assets. |
| **Docker Desktop** | Runs PostgreSQL with `pgvector` from the bundled `compose.yaml`. No local Postgres install needed. |
| **A Google Gemini API key** | Embeddings and answers. A free key works: <https://aistudio.google.com/app/apikey> |

> You do **not** need a Gemini key to run the test suite — every AI call is faked
> there. You need one to actually upload a document and chat with it.

Check what you have:

```bash
php -v                      # 8.3 or newer
php -m | grep pdo_pgsql     # must print pdo_pgsql
composer -V
node -v
docker -v
```

---

## 2. Clone and configure

```bash
git clone https://github.com/FabianOkky/multi-doc-rag.git
cd multi-doc-rag

cp .env.example .env
```

Open `.env` and set your key:

```dotenv
GEMINI_API_KEY=your-key-here
```

Every other default in `.env.example` already matches the bundled
`compose.yaml`, so there is nothing else to change for local development. See
[Configuration](configuration.md) for what each value does.

---

## 3. Start the database

```bash
docker compose up -d
docker compose ps        # wait until the container reports "healthy"
```

This runs `pgvector/pgvector:pg17`, which ships the extension pre-built. The
migration enables it with `Schema::ensureVectorExtensionExists()` and creates the
HNSW index, so there is nothing to install by hand.

> **Port 5432 already taken?** Another Postgres (a system service, or the one
> bundled with Laravel Herd) may already own it. Set `DB_PORT=5433` in `.env` and
> re-run `docker compose up -d` — Compose reads the host port from `DB_PORT`, and
> the container always listens on 5432 internally.

---

## 4. Install and migrate

```bash
composer run setup
```

That one command runs:

1. `composer install`
2. copies `.env` from `.env.example` if it is still missing
3. `php artisan key:generate`
4. `php artisan migrate --force` — creates the tables, enables `pgvector`, builds the HNSW index
5. `npm install && npm run build`

Optionally seed a local account to sign in with:

```bash
php artisan db:seed
```

That creates a single demo user — **Fabian Okky**, `fabian@example.com`,
password `password`. It is a local convenience only; register your own account
instead if you prefer.

---

## 5. Run it

The app does real work in the background — parsing, embedding and summarizing
all happen in queued jobs — so a queue worker has to be running alongside the web
server, or uploaded documents will sit at "Processing" forever.

```bash
composer run dev
```

That starts three processes together: the web server, `queue:listen`, and Vite.
Open the URL it prints (`http://localhost:8000` by default).

Prefer separate terminals? The equivalent is:

```bash
php artisan serve
php artisan queue:work
npm run dev
```

### A note on upload limits

The app accepts files up to 20 MB, but PHP enforces its own `upload_max_filesize`
(usually 2 MB) at request startup, before Laravel sees anything. How you raise it
depends on how you serve the app:

- **FastCGI — Laravel Herd, Valet, nginx + PHP-FPM.** Nothing to do.
  `public/.user.ini` already sets the limits and FastCGI reads it automatically.
  This is the smoother option, and the one to prefer if you have it.
- **`php artisan serve`.** `.user.ini` is not read here, and `-d` flags do not
  work either — `artisan serve` spawns `php -S` as a child process, and the flags
  never reach it. Point `PHPRC` at the bundled ini file instead:

  ```bash
  PHPRC=$PWD/deploy/php.ini php artisan serve              # macOS / Linux
  $env:PHPRC = "$PWD\deploy\php.ini"; php artisan serve    # Windows PowerShell
  ```

Small files work fine either way, so you can skip this until you actually need a
large upload.

---

## 6. First run through the app

1. **Register** an account, or sign in with the seeded one.
2. **Create a workspace** from the dashboard or the Workspaces page — one per
   project or topic. Documents and chat are scoped to it.
3. **Upload documents** (PDF, DOCX or TXT; up to 10 files at a time, 20 MB each).
   Each card shows *Processing* while the background jobs extract text, embed the
   chunks and write a summary, then flips to *Ready*.
4. **Ask a question.** Tap one of the suggested starter questions or type your
   own. The answer streams in with citations to the source filename and page.
5. **Share (optional).** Flip the share switch to publish a read-only link at
   `/s/{token}`. Turning it off makes the link 404 immediately; turning it back
   on restores the same link.

If a document is stuck at *Processing*, the queue worker is almost certainly not
running. See [Troubleshooting](troubleshooting.md).

---

## 7. Run the tests

```bash
php artisan test --compact
```

The suite needs the Docker database running, but no Gemini key: every AI call is
faked. It runs inside an isolated `testing` schema, so it will never touch your
development data. Details in [Testing](testing.md).

---

## Where to go next

- [Architecture](architecture.md) — how the pieces fit together
- [RAG pipeline](rag-pipeline.md) — what happens between an upload and a citation
- [Configuration](configuration.md) — every environment variable
