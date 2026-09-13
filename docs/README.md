# Multi-Doc RAG — Documentation

Multi-Doc RAG is a Retrieval-Augmented Generation web application. You create a
workspace, upload PDF / DOCX / TXT files, and ask questions across all of them.
Every answer is assembled **only** from the passages retrieved from your own
documents, and each answer cites the filename and page it came from.

If the answer is not in your documents, the assistant says so rather than
inventing one. That constraint is the product.

---

## Start here

| If you want to… | Read |
| --- | --- |
| Get it running on your machine | [Getting started](getting-started.md) |
| Understand how the whole thing fits together | [Architecture](architecture.md) |
| Understand retrieval, chunking and citations | [RAG pipeline](rag-pipeline.md) |
| Understand cross-language search and reply language | [Bilingual retrieval](bilingual-retrieval.md) |
| Know what every environment variable does | [Configuration](configuration.md) |
| Read the database schema | [Data model](data-model.md) |
| Run or extend the test suite | [Testing](testing.md) |
| Verify a release by hand | [Manual test plan](manual-test-plan.md) |
| Ship it | [Deployment](deployment.md) |
| Fix something that broke | [Troubleshooting](troubleshooting.md) |

---

## The short version

```
Upload  →  parse (page-aware)  →  chunk  →  embed  →  store as pgvector rows
Ask     →  translate query     →  embed  →  vector search (scoped to workspace)
        →  ground the agent in the retrieved passages  →  stream the answer + citations
```

Two properties hold everywhere in the codebase, and most of the design follows
from them:

1. **Retrieval is always scoped to a single workspace.** A vector search never
   crosses a workspace boundary, so one user's documents can never become
   another user's evidence. Tests assert this directly.
2. **Citations are derived, never generated.** They come from the
   `document_id` and `page_number` of the chunks that were actually retrieved,
   and only the sources the answer explicitly names are kept. The model cannot
   invent a source, because it never writes the citation list.

---

## Technology

| Layer | Choice |
| --- | --- |
| Language / framework | PHP 8.4, Laravel 13 |
| UI | Livewire 4, Flux UI (free), Tailwind CSS 4 |
| AI | Laravel AI SDK (`laravel/ai`) with Google Gemini |
| Text model | `gemini-2.5-flash` |
| Embeddings | `gemini-embedding-001`, 768 dimensions |
| Vector store | PostgreSQL with the `pgvector` extension (HNSW index, cosine distance) |
| Parsing | `smalot/pdfparser`, `phpoffice/phpword`, with a Gemini Files API fallback |
| Background work | Laravel queues (`database` driver) |
| Auth | Laravel Fortify |
| Tests | Pest 4, running against real PostgreSQL + pgvector |

---

## Repository layout

```
app/
├── Actions/       DeleteWorkspace — deletes a workspace and its stored files
├── Agents/        DocumentChatAgent, SummaryAgent, SuggestionAgent, QueryTranslationAgent
├── Jobs/          ParseAndEmbedDocument, GenerateSummary (both queued)
├── Livewire/      Dashboard, Workspace\Index, Workspace\Show, Chat\Window, Shared\Show, Settings\*
├── Models/        Workspace, Document, DocumentChunk, ChatSession, ChatMessage, User
├── Policies/      WorkspacePolicy
└── Services/      Chunker, DocumentParser, Retriever, QueryTranslator, Suggester,
                   AnswerLanguage, RetrievalResult, TranslatedQuery

config/rag.php     Every tunable of the RAG pipeline, documented inline
database/          Migrations (schema + indexes), factories, seeder
resources/views/   Blade and Livewire templates
routes/web.php     Authenticated app plus the public share link
tests/             Pest feature and unit tests
docs/              You are here
```
