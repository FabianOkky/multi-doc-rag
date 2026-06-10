# Multi-Doc RAG

> **Upload your documents, ask anything — every answer is grounded in *your* sources, with citations.**

A Retrieval-Augmented Generation (RAG) web app. Create a workspace, upload your
PDF / DOCX / TXT files, and chat across all of them. Every answer is built only
from the content you uploaded, and each one cites exactly where it came from
(filename + page number). This is **not** a general chatbot — if the answer
isn't in your documents, the assistant says so instead of making something up.

<p>
  <img alt="PHP 8.4" src="https://img.shields.io/badge/PHP-8.4-777BB4?logo=php&logoColor=white">
  <img alt="Laravel 13" src="https://img.shields.io/badge/Laravel-13-FF2D20?logo=laravel&logoColor=white">
  <img alt="Livewire 4" src="https://img.shields.io/badge/Livewire-4-4E56A6?logo=livewire&logoColor=white">
  <img alt="Tailwind CSS 4" src="https://img.shields.io/badge/Tailwind_CSS-4-38BDF8?logo=tailwindcss&logoColor=white">
  <img alt="PostgreSQL + pgvector" src="https://img.shields.io/badge/PostgreSQL-pgvector-4169E1?logo=postgresql&logoColor=white">
  <img alt="License: MIT" src="https://img.shields.io/badge/License-MIT-green.svg">
</p>

---

## ✨ Features

- **Multi-document workspaces** — group documents per project or topic; each workspace has its own files and chat.
- **Upload PDF, DOCX, and TXT** — up to 20 MB per file, multiple files at once.
- **Grounded answers with citations** — every response references the source filename and page number; nothing is invented.
- **Streaming chat** — answers stream token-by-token for a responsive feel.
- **Automatic summaries** — each document gets a short AI summary as soon as it's processed.
- **Suggested questions** — starter questions are generated from your documents so you know what to ask.
- **Public read-only sharing** — publish a workspace behind a private share link; anyone with the link can view it, no sign-in required.
- **Insightful dashboard** — at-a-glance stats (workspaces, documents, indexed passages, questions asked) plus your most recent workspaces and documents.
- **Resilient by design** — heavy parsing/embedding runs in background jobs with retries, optional embedding cache, and optional text-model failover.

---

## 🧠 How it works

The app pairs a vector database (PostgreSQL + `pgvector`) with the
[Laravel AI SDK](https://github.com/laravel/ai) and Google Gemini.

```mermaid
flowchart LR
    subgraph Ingest [Ingestion · queued jobs]
        A[Upload<br/>PDF / DOCX / TXT] --> B[Extract text<br/>+ page numbers]
        B --> C[Chunk text]
        C --> D[Embed chunks<br/>Gemini · 768-dim]
        D --> E[(document_chunks<br/>pgvector)]
        A --> S[Summarize doc<br/>Gemini 2.5 Flash]
    end

    subgraph Ask [Question time]
        Q[Your question] --> R[Embed query]
        R --> V[Vector similarity search<br/>scoped to the workspace]
        V --> E
        V --> CTX[Top-k passages = context]
        CTX --> AG[DocumentChatAgent]
        AG --> ANS[Streamed answer<br/>+ citations: filename · page]
    end
```

1. **Ingestion** — on upload, a `ParseAndEmbedDocument` job extracts text (tracking page numbers), splits it into overlapping chunks, generates embeddings, and stores them as `pgvector` rows scoped to the workspace. A follow-up job writes a short summary.
2. **Retrieval** — when you ask a question, it's embedded and compared against the workspace's chunks using native cosine similarity (`whereVectorSimilarTo`). Only the most relevant passages are passed to the model.
3. **Answer** — the `DocumentChatAgent` answers **only** from those passages and streams the response, attaching citations derived from the exact chunks it used.

---

## 🛠 Tech stack

| Layer | Technology |
| --- | --- |
| Language / framework | PHP 8.4, Laravel 13 |
| Frontend | Livewire 4, Flux UI (free), Tailwind CSS 4 |
| AI | Laravel AI SDK (`laravel/ai`) + Google Gemini — `gemini-2.5-flash` (text), `gemini-embedding-001` @ 768 dims (embeddings) |
| Vector store | PostgreSQL + `pgvector` (e.g. Supabase) |
| Parsing | `smalot/pdfparser` (PDF), `phpoffice/phpword` (DOCX), with optional Gemini Files API fallback |
| Background work | Laravel Queue (`database` driver) |
| Auth | Laravel Fortify (Livewire starter kit) |
| Tests | Pest v4 |

---

## ✅ Prerequisites

- **PHP 8.3+** with the usual Laravel extensions
- **Composer 2**
- **Node.js 18+** and npm
- A **PostgreSQL database with the `pgvector` extension**. The free tier of
  [Supabase](https://supabase.com) works well; the migration enables the
  extension for you.
- A **Google Gemini API key** — get a free one at
  [Google AI Studio](https://aistudio.google.com/app/apikey).

---

## 🚀 Installation

```bash
# 1. Clone
git clone https://github.com/FabianOkky/multi-doc-rag.git
cd multi-doc-rag

# 2. Install dependencies
composer install
npm install

# 3. Environment
cp .env.example .env
php artisan key:generate
```

Now open `.env` and fill in your **database** and **Gemini** credentials
(see [Configuration](#-configuration) below). Then:

```bash
# 4. Run migrations (creates tables + enables pgvector)
php artisan migrate

# 5. Build frontend assets
npm run build
```

> 💡 The first three steps can be run in one go with `composer run setup` — just
> make sure your `.env` database/Gemini values are set before it runs `migrate`.

---

## 🔧 Configuration

Set these in your `.env` (the full template is in `.env.example`):

### Database — PostgreSQL + pgvector

For Supabase: create a project, then **Dashboard → Connect → "Session pooler"**
(IPv4-friendly) and copy the values:

```dotenv
DB_CONNECTION=pgsql
DB_HOST=aws-0-<region>.pooler.supabase.com
DB_PORT=5432
DB_DATABASE=postgres
DB_USERNAME=postgres.<your-project-ref>
DB_PASSWORD=<your-database-password>
DB_SSLMODE=require
DB_SEARCH_PATH=public
```

### AI — Google Gemini

```dotenv
GEMINI_API_KEY=<your-gemini-api-key>

AI_TEXT_MODEL=gemini-2.5-flash
AI_EMBEDDINGS_MODEL=gemini-embedding-001
AI_EMBEDDINGS_DIMENSIONS=768   # must match the vector() column in the migration
```

### RAG tuning (optional)

```dotenv
RAG_CHUNK_SIZE=1000
RAG_CHUNK_OVERLAP=150
RAG_RETRIEVE_LIMIT=5
RAG_MIN_SIMILARITY=0.4
RAG_PARSER_PRIMARY=local       # "local" or "gemini"
```

> ⚠️ **Keep your secrets out of git.** `.env` (and `.env.testing`,
> `.env.production`) are git-ignored. Only the secret-free `.env.example`
> template is committed.

---

## ▶️ Running locally

The app does real work in the background (parsing + embedding + summarizing), so
you need a queue worker running alongside the web server.

```bash
composer run dev
```

This runs the web server, the **queue worker**, and Vite together. Then open the
URL it prints (default `http://localhost:8000`), register an account, and start a
workspace.

Prefer separate terminals? Run them yourself:

```bash
php artisan serve
php artisan queue:work
npm run dev
```

---

## 📖 Usage

1. **Register / log in.**
2. **Create a workspace** from the dashboard or the Workspaces page.
3. **Upload documents** (PDF / DOCX / TXT). They show a "Processing" badge while
   the background jobs parse, embed, and summarize them, then flip to "Ready".
4. **Ask questions** in the chat. Tap a suggested question or type your own — the
   answer streams in with citations to the source filename and page.
5. **Share (optional)** — flip the share switch to publish a read-only link.

The assistant answers in the language of your question and refuses to answer
anything that isn't supported by your documents.

---

## 🧪 Testing

The suite runs against PostgreSQL + pgvector in an isolated schema, so AI calls
are faked — no API quota is used and no Gemini key is required for tests.

Create a `.env.testing` pointing at a Postgres database (a separate Supabase
project, or the same one with an isolated schema) and set `DB_SEARCH_PATH=testing`,
then:

```bash
php artisan test --compact      # or: composer test  (also runs Pint)
```

---

## ☁️ Deployment

The repo includes a `Procfile` for platforms like **Railway** or **Render**:

```
web:    php artisan migrate --force && php artisan config:cache && php artisan serve --host=0.0.0.0 --port=$PORT
worker: php artisan queue:work --tries=3 --max-time=3600 --sleep=3 --backoff=10
```

Run **both** a `web` and a `worker` process. In production set:

- `APP_ENV=production`, `APP_DEBUG=false`, and a fresh `APP_KEY`
- your PostgreSQL `DB_*` values (`DB_SSLMODE=require`)
- `GEMINI_API_KEY`
- `QUEUE_CONNECTION=database`

---

## 📂 Project structure

```
app/
├── Agents/        # AI agents: DocumentChatAgent, SummaryAgent, SuggestionAgent
├── Jobs/          # ParseAndEmbedDocument, GenerateSummary (queued)
├── Livewire/      # Dashboard, Workspace, Chat, Shared components
├── Models/        # Workspace, Document, DocumentChunk, ChatSession, ChatMessage
└── Services/      # Chunker, DocumentParser, Retriever, Suggester
resources/views/   # Blade + Livewire templates (Flux UI)
routes/web.php     # Routes (auth-gated app + public share link)
tests/             # Pest feature & unit tests
```

---

## 📜 License

Released under the [MIT License](LICENSE).

## 👤 Author

**Fabian Okky** — built as a portfolio project to demonstrate a production-style
RAG pipeline with Laravel, Livewire, and pgvector.
