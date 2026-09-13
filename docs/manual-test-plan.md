# Manual test plan

The automated suite covers behaviour. This covers the things a suite cannot: that
the real models return sensible answers, that the streaming UI feels right, and
that the install instructions in these docs actually work on a clean machine.

Run it before a release, or after changing a prompt, a model, or a RAG parameter.

**Prerequisites:** the Docker database running, a queue worker running, a valid
`GEMINI_API_KEY`, and assets built.

---

## Setup

```bash
docker compose up -d
composer run setup
php artisan db:seed        # creates the demo account below
composer run dev
```

Sign in as the seeded demo account:

| Field | Value |
| --- | --- |
| Name | Fabian Okky |
| Email | `fabian@example.com` |
| Password | `password` |

Prepare a test document with facts you can check by eye — a report with figures
works well. Most of the checks below assume an **English** document, because that
is what makes the cross-language cases meaningful.

---

## 1. Authentication and shell

| # | Step | Expected |
| --- | --- | --- |
| 1.1 | Visit `/` signed out | The public landing page, with Log in / Sign up |
| 1.2 | Sign in as Fabian Okky | Redirected to `/dashboard` |
| 1.3 | Visit `/` while signed in | Redirected to `/dashboard` |
| 1.4 | Check the dashboard with no workspaces | "Get started in three steps", a *Create your first workspace* button, and all four stat tiles at 0 |
| 1.5 | Open the user menu | Shows "Fabian Okky", with settings and log out |

## 2. Workspaces

| # | Step | Expected |
| --- | --- | --- |
| 2.1 | Create a workspace named *Documentation Walkthrough* | It appears in the list; the empty state is gone |
| 2.2 | Return to the dashboard | Workspaces reads 1, and the workspace is listed under recent |
| 2.3 | Rename it | The new name shows everywhere without a reload |
| 2.4 | Create a second workspace, then delete it | A confirmation is required first; afterwards it is gone |

## 3. Upload and ingestion

| # | Step | Expected |
| --- | --- | --- |
| 3.1 | Upload your test document | The card appears immediately showing *Processing* and "Generating summary" |
| 3.2 | Watch the queue worker | `ParseAndEmbedDocument` runs, then `GenerateSummary` |
| 3.3 | Wait for the card to settle | It flips to *Ready* and shows a 3–4 sentence summary |
| 3.4 | Read the summary critically | Every claim in it is actually in the document. Nothing invented |
| 3.5 | Check the dashboard | Documents reads 1 / 1 ready, and "Indexed passages" is greater than 0 |
| 3.6 | Try to upload a `.exe`, or a PDF renamed to `.txt` | Rejected with a validation error; no document row is created |
| 3.7 | Try to upload 11 files at once | Rejected — the limit is 10 |

## 4. Chat and citations — the core promise

| # | Step | Expected |
| --- | --- | --- |
| 4.1 | Open the chat and wait a moment | Suggested question chips appear, each one answerable from your document |
| 4.2 | Click a chip | It is sent exactly as if typed; the answer streams in |
| 4.3 | Ask a question whose answer is a specific figure | The figure is **correct**, and a SOURCES row shows the filename and page |
| 4.4 | Open the document and check that page | The answer really is supported there |
| 4.5 | **Ask something not in the document** (e.g. "Who is the current president of France?") | It says the uploaded documents do not cover it. **No citations.** It must not answer from general knowledge, and must not mention "the CONTEXT", "passages" or any other internal wording |
| 4.6 | Ask a follow-up that depends on the previous turn | The conversation history is used correctly |
| 4.7 | Send a second question while one is still streaming | Refused with "Please wait for the current answer to finish." |
| 4.8 | Send 11 questions inside a minute | The 11th is refused with a countdown, and no message is stored |
| 4.9 | Submit an empty question | A validation error; nothing reaches the agent |

## 5. Bilingual behaviour

This is the headline feature. With an **English** document loaded:

| # | Step | Expected |
| --- | --- | --- |
| 5.1 | Ask a question in **Indonesian** | The English passage is found and the answer is correct |
| 5.2 | Check the reply language | Indonesian — it mirrors the question |
| 5.3 | Check the citation | Still names the original English file and page. The document itself was never translated |
| 5.4 | Now ask a question in **English**, in the same conversation | The reply comes back in **English**, not dragged into Indonesian by the previous turns |
| 5.5 | Set *Reply in* to **English** and ask in Indonesian | The answer is in English |
| 5.6 | Set *Reply in* to **Bahasa Indonesia** and ask in English | The answer is in Indonesian |
| 5.7 | Reload the page | The picker keeps your choice — it is stored on the workspace |
| 5.8 | Upload another document after changing the language | Its summary and the suggestion chips follow the chosen language |

> 5.4 is the one that regresses most easily. Replayed conversation history pulls
> hard toward the language it was written in, which is why the language reminder
> is repeated on every turn, `auto` included.

## 6. Sharing

| # | Step | Expected |
| --- | --- | --- |
| 6.1 | Turn the share switch on | A `/s/{token}` link is produced |
| 6.2 | Open it in a private window (signed out) | A read-only view: documents, summaries, and the full transcript with SOURCES |
| 6.3 | Look for owner controls | No upload form, no share toggle, no delete buttons, no message composer |
| 6.4 | Turn sharing off and reload the link | 404 |
| 6.5 | Turn sharing back on | **The same** URL works again |
| 6.6 | Visit `/s/some-invented-token` | 404 |

## 7. Failure handling

| # | Step | Expected |
| --- | --- | --- |
| 7.1 | Stop the queue worker, upload a document | It stays *Processing* indefinitely — this is the single most common support question |
| 7.2 | Restart the worker | The queued job runs and the document completes |
| 7.3 | Upload a scanned / image-only PDF | Local extraction yields little, so the Gemini Files API fallback runs. Chunks have no page number, and citations name the file alone |
| 7.4 | Temporarily set an invalid `GEMINI_API_KEY` and ask a question | The question is still saved, and the reply is a plain "temporarily unavailable" message **in the language of the question**, with no citations. The page does not break |
| 7.5 | With the invalid key, open a workspace with ready documents | The suggestion chips are simply absent. Nothing else is affected |
| 7.6 | Restore the key and reload | Chips come back — the failure was not cached |
| 7.7 | Force a document to `failed` in the database, reload the workspace | The card shows *Failed* with a Retry button, and Retry re-queues it |

## 8. Cross-user isolation

Create a second account with its own workspace and document.

| # | Step | Expected |
| --- | --- | --- |
| 8.1 | As user A, visit user B's workspace URL | 403 |
| 8.2 | As user A, ask a question whose answer only exists in B's document | Not found, not cited, not leaked |
| 8.3 | Check user A's dashboard | B's workspaces and documents do not appear in any count or list |

## 9. Responsiveness and polish

| # | Step | Expected |
| --- | --- | --- |
| 9.1 | Narrow the window to phone width | Layout stacks; nothing overflows horizontally |
| 9.2 | Watch a long answer stream | The transcript stays pinned to the newest text |
| 9.3 | Scroll up mid-stream | It stops auto-scrolling and lets you read |
| 9.4 | Scroll back to the bottom | It re-pins and follows again |
| 9.5 | Toggle dark mode in settings | Both themes are legible throughout |

---

## Recording a run

| Field | |
| --- | --- |
| Date | |
| Commit | |
| Text model / embeddings model | |
| Failures | |
| Notes | |

Anything that fails here and can be pinned to deterministic behaviour should
become a Pest test before it is fixed — that is where several of the existing
tests came from.
