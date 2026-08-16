> **Implementation Language Directive**
>
> This document is written in Indonesian for documentation and planning purposes. However, all implementation based on this document must use **English** as the project's primary language.
>
> When implementing the requirements described in this document:
>
> * All user-facing UI text, labels, buttons, messages, notifications, and content must be written in **English**.
> * All source code, variable names, function names, class names, component names, and comments should use **English**.
> * All database names, table names, column names, enum values, and database-related identifiers must use **English**.
> * All API endpoints, request/response fields, validation messages, and API-related identifiers must use **English**.
> * All routes, URLs, configuration keys, and other technical identifiers must use **English**.
> * Do not directly copy Indonesian wording from this document into the application.
> * When this document contains Indonesian descriptions, interpret them as **functional and design requirements**, not as the literal language to be used in the implementation.
>
> **Important:** The language of this document does not determine the language of the application. The application must remain fully English unless another language requirement is explicitly specified.


# Studyback — Tech Stack Specification

**Status:** Post-blueprint, pre-implementation
**Source of truth:** Studyback Product Specification + Studyback System Architecture Blueprint
**Constraint:** 48-hour MVP hackathon, must not be over-engineered
**Base preference:** React (frontend), Laravel (backend), PostgreSQL (database), `ai_service` as a provider-agnostic AI abstraction (default provider: OpenRouter with the `openrouter/free` route; Featherless.ai as an optional hackathon provider; Mock AI Provider for development/testing)

---

## 1. Executive Summary

**Is the current stack sufficient? Yes. The tech stack for the Studyback MVP has been finalized and is ready to become the baseline implementation.**

React, Laravel, PostgreSQL, and `ai_service` remain the core stack. No changes to the main architecture components are required. All supporting technology decisions needed for the MVP have also been explicitly finalized:

* **Modular monolith** → Laravel is used as a single application backend with service classes and folders per module, without separate microservices.
* **AI Orchestrator** → implemented as `ai_service`, a thin and stateless **in-process Laravel service**. This service is the only caller to the external LLM provider — through a **LLM Provider Abstraction** within it — and has no separate database, public API, authentication, or deployment.
* **AI Provider** → `ai_service` is not tied to a single provider. **OpenRouter** with the `openrouter/free` route is set as the default provider/route for the MVP. **Featherless.ai** remains supported as an **optional provider**, mainly because it is a hackathon partner and may provide inference credits. **Mock AI Provider** is available for local development, testing, and situations where no real AI API is accessible.
* **AI Model** → no primary/fallback model is permanently hardcoded into the architecture. Specific models such as `gpt-oss-20b` or `Nemotron 3 Nano 30B A3B` can optionally be used when pinned/deterministic model selection is needed and the model is available on the chosen provider/plan. The MVP baseline continues to use `openrouter/free` as the default route.
* **RAG / Retrieval** → uses a PostgreSQL filter query based on `material_id` and `topic/subtopic_id`. No vector database is used in the MVP.
* **Chunking** → uses fixed-length chunking targeting **~1,000 characters and ~200 characters of overlap**. Heading-based or heading-regex chunking is not used.
* **PDF Text Extraction** → `spatie/pdf-to-text` with Poppler is set as the primary extractor, with `smalot/pdfparser` as an optional fallback.
* **File Storage** → Laravel Filesystem with the local disk is used to store PDFs privately and provide authenticated backend-proxied downloads.
* **Background Processing** → synchronous processing is used as the MVP baseline. Laravel Queue is only a backup option if processing is too slow for the UX.
* **Redis and Vector Database** → not used in the MVP because there is no architectural need that justifies adding either of them.
* **Containerization** → Docker + docker-compose is used for the frontend, backend, and PostgreSQL. There is no separate container for `ai_service`.

With these decisions, no major component remains in an *undecided* or *needs benchmark* state. This document serves as the **final tech stack baseline** before moving into the Database Design, API Design, AI Architecture, and UI/UX implementation phases.

**One-sentence conclusion:** The Studyback MVP has a finalized tech stack and supporting architecture — including business logic that is independent of any specific AI provider — so implementation can begin without adding new infrastructure components beyond the decisions listed in this document.

---

## 2. Architecture → Tech Stack Mapping

| Architecture Component                                                       | Technology                                                                                      | Status                                           |
| ---------------------------------------------------------------------------- | ------------------------------------------------------------------------------------------------ | ------------------------------------------------ |
| Frontend (SPA)                                                               | React                                                                                            | KEEP                                             |
| API / Application Backend                                                    | Laravel (Controllers + Form Requests)                                                            | KEEP                                             |
| Application Modules (Materials, Topics, Study Session, Quiz, Learning State) | Laravel Service classes / Actions, one per module, within a single Laravel app (modular monolith)  | KEEP + implement as a folder per module         |
| Authentication                                                               | Laravel Sanctum (session/token)                                                                  | ADD (part of Laravel, not a new component)   |
| AI Orchestrator                                                              | `ai_service` — in-process Laravel service, thin and stateless                                    | KEEP + establish as an internal Laravel service |
| LLM Provider Abstraction                                                     | Provider-agnostic interface within `ai_service` (OpenRouter / Featherless.ai / Mock)           | ADD (part of `ai_service`, not a new component) |
| Retrieval / RAG                                                              | PostgreSQL query filter (material_id + topic_id)                                                 | KEEP                                             |
| LLM Interface                                                                | HTTP client from `ai_service`, through the LLM Provider Abstraction, to the external LLM provider (OpenAI-compatible) | KEEP + generalize from Featherless-only to provider-agnostic |
| Database                                                                     | PostgreSQL                                                                                       | KEEP                                             |
| File Storage                                                                 | Laravel Filesystem (local disk driver)                                                           | ADD (configuration, not a new tool)               |
| Material Processing Pipeline                                                 | Laravel job/controller action (sync) + PDF extraction library                                    | ADD library                                      |
| Background Processing                                                        | None (synchronous), Laravel Queue `sync` driver if needed                              | NOT REQUIRED (Redis), OPTIONAL (Queue)           |
| Containerization                                                             | Docker (docker-compose: frontend, app, db)                                                       | KEEP                                             |

**Note on the position of `ai_service`:** `ai_service` is an **in-process service inside the Laravel application**, not a Python/Node service, microservice, or separate container. `ai_service` has no public API, separate authentication, database, or independent deployment.

`ai_service` acts as a thin, stateless abstraction layer responsible for building prompts, selecting/configuring providers, selecting/configuring models, calling the external LLM provider through the LLM Provider Abstraction, handling retry/fallback, validating structured output, and normalizing provider responses into a consistent internal format. Laravel remains the sole owner of business state and database state. `ai_service` never writes directly to the database.

AI communication uses HTTP only at the external boundary:

**Laravel `ai_service` → LLM Provider Abstraction → Configured External LLM Provider (OpenRouter / Featherless.ai / Mock)**

There is no HTTP communication between Laravel and `ai_service` because `ai_service` runs within the same Laravel application process. Provider-specific details (base URL, API key, request format) are isolated inside the implementation/configuration layer of the LLM Provider Abstraction, so application modules (Materials, Topics, Quiz, etc.) never depend directly on OpenRouter, Featherless, or a specific model.

This decision establishes the **Modular Monolith** architecture for the MVP and rejects a hybrid model or standalone AI service for now.


---

# 3. Recommended Tech Stack

| Area                              | Technology                                            | Purpose                                                                                                                  | Status       |
| ---------------------------------- | ------------------------------------------------------ | -------------------------------------------------------------------------------------------------------------------------- | ------------ |
| Frontend                          | React                                                  | SPA for Home / My Materials / Studyback Workspace                                                                      | FINAL        |
| Backend/API                       | Laravel                                                | Modular monolith, all business logic & state mutation                                                                  | FINAL        |
| Database                          | PostgreSQL                                             | Materials, topics, subtopics, chunks, sessions, quizzes, learning state                                                  | FINAL        |
| Auth                               | Laravel Sanctum                                        | Session/token authentication and user ownership scoping                                                                  | FINAL        |
| AI Integration                    | `ai_service` (Laravel in-process service)              | Thin wrapper for prompt construction, provider-agnostic LLM calls, retry handling, and structured output validation    | FINAL        |
| LLM Provider Abstraction          | Interface inside `ai_service`                        | Hides provider-specific details from application modules; allows swapping providers without changing business logic | FINAL        |
| Default AI Provider               | OpenRouter                                             | Default provider for inference — topic extraction, quiz generation, Teach Me, and answer evaluation                    | FINAL        |
| Default AI Route/Model            | `openrouter/free`                                      | Free-model router on OpenRouter; dynamically selects available free models                                       | FINAL        |
| Optional AI Provider              | Featherless.ai                                         | Hackathon partner; used when configured and inference credits are successfully claimed                               | OPTIONAL     |
| Development/Test AI Provider      | Mock AI Provider                                       | Used for local development and automated testing without calling a real AI API                                      | OPTIONAL     |
| Pinned Model Strategy (optional)  | e.g. `gpt-oss-20b`, `Nemotron 3 Nano 30B A3B`          | Used only when deterministic model selection is needed and the model is available on the chosen provider/plan        | OPTIONAL     |
| PDF Text Extraction               | `spatie/pdf-to-text` (wraps `pdftotext` from Poppler)   | PDF extraction → raw text                                                                                             | FINAL        |
| PDF Extraction Fallback           | `smalot/pdfparser`                                     | Fallback if the Poppler binary is not available in the deployment environment                                                        | OPTIONAL     |
| File Storage                      | Laravel Filesystem, `local` driver                     | Stores the original PDF for Download Material                                                                               | FINAL        |
| RAG / Retrieval                   | PostgreSQL `WHERE`-filter query                        | Filters chunks by `material_id` + `topic/subtopic_id`                                                             | FINAL        |
| Chunking                          | PHP native fixed-length chunking                       | Splits text deterministically before topic identification                                                          | FINAL        |
| Chunk Size                        | ~1,000 characters                                       | Target size for each chunk                                                                                               | FINAL        |
| Chunk Overlap                     | ~200 characters                                         | Preserves context between chunks                                                                                       | FINAL        |
| Background Processing             | Synchronous (inline)                                    | Processing is performed within the request lifecycle for the MVP                                                                   | FINAL        |
| Background Processing (optional)  | Laravel Queue, `sync` or `database` driver           | Only used if upload processing is too slow for the UX                                                           | OPTIONAL     |
| Redis                              | —                                                       | No use case requires Redis in the MVP                                                                       | NOT REQUIRED |
| Vector Database                   | —                                                       | Retrieval is limited to a single material/topic; PostgreSQL filtering is sufficient                                                | NOT REQUIRED |
| Containerization                  | Docker + docker-compose                                | Containers for Laravel, React, and PostgreSQL                                                                           | FINAL        |
| API Communication                 | REST (JSON)                                             | Frontend ↔ Laravel                                                                                                       | FINAL        |
| External AI Communication         | HTTPS REST API (OpenAI-compatible)                      | Laravel `ai_service` ↔ configured LLM provider (OpenRouter default, Featherless optional)                                | FINAL        |

### AI Provider & Model Configuration

Studyback no longer depends strictly on a single AI provider or a single model. `ai_service` exposes a **LLM Provider Abstraction** in which the provider and model can be configured rather than hardcoded into business logic.

**Default Provider — OpenRouter**

OpenRouter was chosen as the default provider for the MVP because:

* It provides an OpenAI-compatible API, keeping the integration on the `ai_service` side simple (the same HTTP client previously used for Featherless).
* It provides the `openrouter/free` route, a free-model router that dynamically selects the free models currently available on OpenRouter — not a single model like `gpt-oss-20b`.
* It does not lock Studyback into a specific model; the availability of free models on OpenRouter can change over time, and the router handles that selection at the provider level, not at the application architecture level.

**Default Route — `openrouter/free`**

`openrouter/free` is a router, not an individual model. Several important points should be documented correctly:

* `openrouter/free` dynamically selects from the various free model variants available on OpenRouter at the time the request is sent.
* The router can consider the capabilities required by the request, including structured outputs, tool calling, image understanding, and other supported capabilities.
* Because the pool of available free models can change, Studyback does **not** make its core architecture depend on any single model behind it.
* The specific model ultimately selected behind `openrouter/free` is an **implementation/runtime detail**, not a permanent application architecture decision.

**Optional Provider — Featherless.ai**

Featherless.ai remains supported as an optional provider, mainly because:

* Featherless.ai is a hackathon partner for this event.
* Participants may obtain inference credits if they successfully claim credits from Featherless.
* Featherless.ai provides an OpenAI-compatible endpoint, so it can be integrated through the same LLM Provider Abstraction without changing the application's business logic.

Featherless.ai is **not** a required provider. If it is not configured or credits are not successfully claimed, the application can still run entirely on OpenRouter (or the Mock AI Provider for development).

**Development/Test Provider — Mock AI Provider**

The Mock AI Provider is used for:

* Local development without needing a real API key.
* Automated testing that requires deterministic output and does not depend on external services.
* Situations where no real AI API is accessible (e.g., rate limits, downtime, or exhausted credits).

**Pinned Model Strategy (Optional)**

Specific models such as `gpt-oss-20b` or `Nemotron 3 Nano 30B A3B` **may** be used as pinned models when:

* Deterministic model selection is needed (for example, for consistent results during a demo), and
* The model is available on the configured provider/plan.

These models are **model options**, not routers — unlike `openrouter/free`, which is a router. These models are **not** set as required primary/fallback models in the Tech Stack; that designation, if needed, is documented as an optional strategy in the AI Architecture document, not as a Tech Stack decision that locks the implementation.

### AI Service Architecture

`ai_service` is an **in-process Laravel service**, not a separate container or backend service.

Therefore:

* `ai_service` runs inside the Laravel application.
* There is no REST communication between Laravel and `ai_service`.
* `ai_service` acts as the abstraction layer between Laravel business logic and the configured external LLM provider.
* `ai_service` handles prompt construction, provider selection, model selection, API requests, retry/fallback, structured-output validation, and response normalization.
* The external LLM provider (OpenRouter by default, Featherless.ai optionally, or the Mock AI Provider for development/testing) is accessed through the LLM Provider Abstraction within `ai_service`.
* No dedicated container is required for `ai_service`.

AI communication architecture:

**React → Laravel API → `ai_service` → LLM Provider Abstraction → Configured External LLM Provider**

The provider and model used are determined through configuration (see Section 7.3 — Environment Configuration), not hardcoded into business logic.

## 4. PDF Processing

The required pipeline:

```
PDF → Text Extraction → Cleaning → Chunking → Topic/Subtopic Identification → Storage
```

Text Extraction — spatie/pdf-to-text (PRIMARY)

A Composer package wrapper around pdftotext (part of Poppler-utils), invoked as a binary.
Chosen as the primary extractor because pdftotext generally produces good extraction results for text-based PDFs, including many documents with relatively complex layouts such as slides and lecture notes.
Since Docker is already part of the stack, adding poppler-utils to the Dockerfile remains simple and does not add significant infrastructure for the MVP.

Text Extraction — smalot/pdfparser (FALLBACK/OPTIONAL)

Pure-PHP, requires no external binary.
Useful as a fallback if there are issues with installing or running Poppler, or if the deployment target does not allow external binaries.
Extraction quality can be more limited on PDFs with complex layouts, but it is sufficient as a fallback for the MVP.

Cleaning

Deterministic, using native PHP: removes excessive whitespace, normalizes line breaks, and, where possible, discards repeated headers/footers or page-number patterns.
Requires no additional libraries.

Chunking

Deterministic, using fixed-length chunking as the main strategy for the MVP.
Text is split into chunks of roughly 1,000 characters with roughly 200 characters of overlap between chunks.
This approach does not depend on heading structure or PDF format, making it more robust to variations in course documents such as slides, lecture notes, and PDFs with inconsistent heading structures.
The implementation uses native PHP and requires no additional libraries.

Topic/Subtopic Identification

This is the main AI stage in the pipeline.
The process runs through `ai_service`, which forwards the request to the configured LLM provider (default: OpenRouter with the `openrouter/free` route; optional: Featherless.ai or the Mock AI Provider).
The resulting structured JSON is validated by Laravel before being stored to ensure the format and data received match the system's requirements.

Storage

The final result — topics, subtopics, and chunks linked to the material and its topics/subtopics — is stored in PostgreSQL.
No additional libraries or databases are needed for the MVP.

Not recommended: OCR library (Tesseract)

OCR is out of the MVP scope because the Studyback specification assumes text-based PDFs, not image-scanned PDFs.

---

## 5. File Storage

**Recommendation: Local Storage, via the Laravel Filesystem `local` driver.**

Reasons:

* Architecture Section 15 explicitly places file storage scaling as a "future evolution", not an MVP requirement.
* The deployment target is a "single deployable unit" (Section 17) — a single backend instance, so local persistent storage is sufficient for MVP needs.
* The Laravel Filesystem API is already abstracted (`Storage::disk('local')`), so if a move to S3-compatible object storage is ever needed, the change can be made through disk configuration without significantly altering the application architecture.
* Original PDF files are stored in `storage/app/private`, outside `public/`, so they cannot be accessed directly through a public URL.
* **Download Material is part of Material Detail and remains implemented as an MVP feature.** Files are served through a backend-proxied download after the system authenticates and verifies that the material is owned by the currently logged-in user.
* The Laravel Filesystem provides `Storage::download()`, keeping the authenticated file download implementation simple and removing the need for an additional file-serving system.

**File Storage & Download Flow:**

```text
Upload PDF
    ↓
Private Local Storage
    ↓
Save file_path + original_name + user_id
    ↓
Material Detail
    ↓
Download PDF
    ↓
Authenticated Backend Route
    ↓
Ownership Check
    ↓
Storage::download()
```

**Object/Cloud Storage: NOT REQUIRED for the MVP.** Consider it only if:

* The chosen deployment platform uses an ephemeral filesystem, so files can be lost when the container or instance is restarted.
* The system later grows into a multi-instance deployment that requires shared object storage.

If the deployment uses VPS/Docker with a persistent volume, local storage remains suitable for the MVP. If the deployment platform uses an ephemeral filesystem, object storage such as S3-compatible storage or Cloudflare R2 can be used as a deployment adjustment without changing the core application flow.

**Security Requirement:**

* Original PDFs are not stored in `public/`.
* The original file URL is not exposed directly to the client.
* Downloads must go through an authenticated backend route.
* The backend must verify material ownership before serving the file.
* The filename shown during download can use `original_name`, while `file_path` uses an internal filename that is hard to guess.


## 6. RAG / Retrieval

* Validation: Metadata-based retrieval using PostgreSQL is ALREADY SUFFICIENT for the MVP. No vector database is used.

* Studyback uses metadata/filtering-based retrieval to fetch the relevant context from the material being studied. This approach was chosen because it matches the product scope and keeps the implementation simple during the 48-hour hackathon.

Reasons for the decision:

- Architecture Blueprint Sections 8 & 17 choose filter-based retrieval and place vector databases as a future evolution, not an MVP requirement.
- Product Spec Section 9.2 uses a simple context boundary: Material → Chunking → Retrieval → Relevant Context → AI Response, with no semantic search requirement.
- The product scope is single-material, topic-scoped interaction, so in a given study session the user only interacts with a specific material and its topics/subtopics.
- The retrieval needed is essentially fetching chunks by metadata, for example: "get all chunks from material X related to topic/subtopic Y".
- PostgreSQL can handle this with ordinary filter queries, so no embedding or similarity search is needed.
- Adding a vector database such as pgvector or Pinecone would introduce complexity in the form of embedding generation, vector storage, similarity tuning, and a retrieval pipeline, with no significant benefit for the MVP.

Retrieval Flow

Study Session
     ↓
Material + Topic/Subtopic
     ↓
PostgreSQL Filtering
     ↓
Relevant Chunks
     ↓
Relevant Context
     ↓
`ai_service` → Configured LLM Provider (OpenRouter default / Featherless optional / Mock)
     ↓
AI Response

Technical implementation (conceptual, not schema):

Chunks have relations to the material, topic, and, if needed, subtopic.
PostgreSQL uses indexes on the relevant filtering columns to keep queries fast.
Retrieval is done by filtering based on the material and topic/subtopic.
No vector embeddings, vector database, or additional PostgreSQL extensions are needed for the MVP.

---

## 7. AI Provider & Model Selection and Structured Output Flow

### 7.1 Recommendation

Studyback uses a **provider-agnostic** approach: the application's business logic depends on the `ai_service` abstraction, not on any single provider or model.

**DEFAULT PROVIDER: OpenRouter, route `openrouter/free`**

- OpenRouter was chosen as the default provider for the MVP because it provides an OpenAI-compatible API and a free-model router (`openrouter/free`) that dynamically selects available free models.
- `openrouter/free` is **not** an individual model — it is a router that can consider the capabilities required by the request (structured outputs, tool calling, image understanding, etc.) when selecting the free models currently available.
- Because the free-model pool can change at any time, the specific model behind `openrouter/free` is treated as a runtime detail, not a permanent architecture decision.

**OPTIONAL PROVIDER: Featherless.ai**

- Used when configured (`FEATHERLESS_API_KEY` available) and inference credits are successfully claimed, given that Featherless.ai is a hackathon partner for this event.
- Accessed through the same LLM Provider Abstraction, so no changes to the application's business logic are needed.

**DEVELOPMENT/TEST PROVIDER: Mock AI Provider**

- Used for local development and automated testing so that no dependency on external services or real API keys is needed.

**OPTIONAL PINNED MODEL STRATEGY**

If deterministic model selection is needed (for example, for consistent results during a demo), `ai_service` can be configured to use an explicitly pinned model, as long as that model is available on the configured provider/plan. Example model options that can be considered (not routers, and not permanent free models):

- `gpt-oss-20b` — large context window (128K), supports tool-use/structured output.
- `Nemotron 3 Nano 30B A3B` — an alternative model option for conversational tasks such as Teach Me.

As an **optional optimization** (documented further in the AI Architecture document), task-specific model mapping can be:

| Use Case            | Model (optional, if available)                          |
| -------------------- | ----------------------------------------------------------- |
| Topic Identification | `gpt-oss-20b` (if available)                                |
| Teach Me              | `Nemotron 3 Nano 30B A3B` or `gpt-oss-20b` (if available) |
| Quiz Generation       | `gpt-oss-20b` (if available)                                |
| Answer Evaluation     | `gpt-oss-20b` (if available)                                |

The MVP baseline remains **`openrouter/free`** for all the use cases above; task-specific pinned models are merely an optional optimization and do not change the Tech Stack baseline.

### 7.2 Fallback Strategy

Because provider and model availability can change, the fallback logic is **not** defined as a single fixed primary-model → fallback-model pair. Instead, fallback is distinguished into three levels:

1. **Provider fallback** — if the default provider (OpenRouter) is unreachable or fails, `ai_service` can be configured to try the optional provider (Featherless.ai) when available and configured.
2. **Model fallback** — if the implementation uses a pinned model, the model-level fallback can use another compatible model on the same provider (e.g., `gpt-oss-20b` ↔ `Nemotron 3 Nano 30B A3B`), according to configuration.
3. **Development fallback** — if no real provider is accessible (e.g., during local development or automated testing), `ai_service` uses the Mock AI Provider.

The principle: fallback logic is **configurable**, not hardcoded into Laravel business logic. Application modules call `ai_service` without knowing which provider/model is currently active; `ai_service` handles retry and fallback based on the applicable configuration.

### 7.3 Environment Configuration

Provider and model are configured through environment variables, not hardcoded into business logic. Example conceptual configuration:

```
AI_PROVIDER=openrouter
AI_MODEL=openrouter/free

OPENROUTER_API_KEY=your_openrouter_api_key

# Optional — hanya diperlukan jika Featherless.ai digunakan sebagai provider fallback/opsional
FEATHERLESS_API_KEY=your_featherless_api_key
```

Provider-specific details (base URL, authentication headers, request/response format) are isolated inside the implementation/configuration layer of the LLM Provider Abstraction (e.g., per-provider adapter classes within `ai_service`), so replacing or adding a provider does not require changes to the application modules (Materials, Topics, Quiz, Learning State, etc.).

### 7.4 AI Service Responsibilities

`ai_service` is responsible for:

- building prompts;
- selecting/configuring the provider (OpenRouter default, Featherless.ai optional, Mock for dev/test);
- selecting/configuring the model (default `openrouter/free`, or a pinned model when configured);
- sending AI requests to the active provider;
- handling errors from the provider;
- handling retry/fallback according to configuration (Section 7.2);
- validating structured output;
- normalizing provider responses into a consistent internal format; and
- hiding provider-specific implementation details from the application modules.

`ai_service` does **not**:

- hold business state;
- write directly to the database;
- be a separate microservice;
- have a public API; and
- contain learning-state calculations that are application-specific.

The Laravel application modules remain responsible for:

- persisting data;
- calculating quiz scores;
- updating mastery;
- determining learning state; and
- applying deterministic business rules.

### 7.5 Structured Output Flow

```
LLM (Configured Provider — OpenRouter default / Featherless optional / Mock)
  ↓ raw output
Structured JSON (schema: topics[], quiz_questions[], evaluation{verdict, feedback, subtopic})
  ↓ validated
ai_service (parse + validate JSON shape; retry or fallback to another provider/model per configuration if invalid)
  ↓ clean result (data, not opinions about state)
Laravel (Application Modules: Processing, Quiz, Learning State)
  ↓ applies deterministic rules
Application Logic (persist topics, store quiz, calculate score, update mastery/status)
```

Structured-output validation works **independently of the provider/model** in use — the schema contract (`topics[]`, `quiz_questions[]`, `evaluation{}`) stays the same regardless of the provider/model behind it. If a provider/model cannot reliably satisfy that structured-output contract, `ai_service` can retry or use another configured provider/model, following the fallback strategy in Section 7.2.

Per Principles 8–10 of the Architecture Blueprint: **AI never writes directly to the database or determines the Learning State.** `ai_service` only returns structured data (e.g., "this answer is correct", "this maps to Subtopic X"); Laravel is the one that calculates the score, determines the status (Needs Review/In Progress/Mastered) using the fixed deterministic formula (<60% / 60–79% / ≥80%), and persists the results.

---

## 8. Background Processing

**Evaluation: Synchronous processing IS ALREADY SUFFICIENT. Laravel Queue OPTIONAL, Redis NOT REQUIRED.**

*   Architecture Blueprint Sections 15 & 17 explicitly set "Synchronous processing (acceptable for hackathon file sizes)" as the MVP decision, with background workers/queues listed as _future evolution_, not a current requirement.
    
*   Course material PDFs (a few dozen pages) and a pipeline dominated by deterministic operations (extraction, cleaning, chunking) plus one AI call (topic/subtopic identification) realistically finish within seconds to tens of seconds — enough to be handled inline within a single Laravel request, with **clear loading states in the frontend** ("Uploading Material... → Extracting Content... → Understanding Material... → Identifying Topics... → Preparing Study Material...").
    
*   Loading states are mandatory so that the synchronous process does not look like a frozen page or an error when extraction and AI processing take longer during a demo.
    
*   **Laravel Queue (OPTIONAL, not required):** if testing shows that upload+processing feels too slow for the UX, a queue can be considered as a further optimization. For the MVP, a queue is not part of the baseline implementation.
    
*   **Redis: NOT REQUIRED.** There is no caching, complex rate-limiting, or high queue-throughput use case that justifies adding Redis in these 48 hours. Adding it would only introduce one more Docker container with no measurable benefit for the MVP.

---

## 9. Project Structure

```t
studyback/
├── frontend/                 # React SPA (Home, My Materials, Workspace)
├── backend/                  # Laravel — modular monolith
│   ├── app/
│   │   ├── Modules/          # one folder per architecture module
│   │   │   ├── Materials/
│   │   │   ├── Processing/
│   │   │   ├── Topics/
│   │   │   ├── StudySession/
│   │   │   ├── Quiz/
│   │   │   └── LearningState/
│   │   └── Services/
│   │       └── AiOrchestrator.php   # in-process ai_service; the only caller to the configured LLM provider through the provider abstraction
│   └── ... (standard Laravel structure)
└── docs/                     # this document + Product Spec + System Architecture
```

`ai_service` has no separate folder at the project root. `ai_service` is implemented as an **in-process Laravel service** through `AiOrchestrator.php` inside `backend/app/Services/`.

`AiOrchestrator.php` is a thin, stateless service responsible for:

* building prompts;
* selecting/configuring the provider and model through the LLM Provider Abstraction;
* calling the configured external LLM provider (default: OpenRouter `openrouter/free`; optional: Featherless.ai; dev/test: Mock AI Provider);
* handling retry/fallback according to configuration; and
* validating structured output.

`AiOrchestrator.php` is the **single caller** to the external LLM provider — not to a specific provider, but to whichever provider is currently configured through the provider abstraction.

The only additional folder at the root is `docs/`. There are no separate `ai_service/`, `workers/`, `queue/`, or `services/` folders because the MVP does not use background workers or separate microservices.

Modularity is realized through the folder structure inside `backend/`, not as a separate deployable unit. This structure is consistent with the **Modular Monolith** decision in Architecture Section 2.


---

## 10. Additional Technologies to Learn

### MUST LEARN
- **`spatie/pdf-to-text` + Poppler-utils** — how to install in the Dockerfile, how to handle binary failures/corrupt PDFs (for failure handling Section 13: "PDF extraction fails").
- **Laravel Filesystem API** (`Storage::disk()`) — especially how to serve files through an authenticated route (not public files), to satisfy Security Section 14.
- **OpenRouter API (OpenAI-compatible endpoint)** — request/response format, how to use the `openrouter/free` route, how to force/encourage structured JSON output (system prompt + schema instruction), and rate limits on the free tier.
- **Laravel Sanctum** — if you have never used it, this is the lightest auth option for an SPA + API tokens, matching the Section 14 requirements.
- **Environment-based configuration for the AI provider** — how to isolate provider configuration (`AI_PROVIDER`, `AI_MODEL`, per-provider API keys) outside business logic, so swapping providers does not require code changes in the application modules.

### SHOULD LEARN
- **Featherless API (OpenAI-compatible endpoint)** — studied as an optional provider, especially if the hackathon inference credits are successfully claimed.
- **Laravel Queue with the `database` driver** — useful as a safety net if processing time turns out to be a UX problem during the demo, without needing to learn Redis.
- **JSON Schema validation in PHP** (e.g., `justinrainbow/json-schema` or manual array-shape validation) — to validate structured output from the LLM before persisting, per failure handling Section 13.
- **Basic PostgreSQL full-text search** (`tsvector`) — optional for speeding up/simplifying filter-based retrieval queries when the chunk volume per material is large; not a replacement for a vector DB, only an index optimization.

### NOT NEEDED
- **Vector database (pgvector, Pinecone, Weaviate, etc.)** — out of scope, already validated in Section 6.
- **Redis** — already validated in Section 8.
- **Message broker (RabbitMQ, Kafka, SQS)** — not relevant for a single-instance modular monolith within 48 hours.
- **Object storage SDK (AWS S3, GCS)** — only needed if the hosting decision changes (see Section 5); do not study them before that decision is made.
- **OCR (Tesseract, etc.)** — out of the MVP scope (text-based PDFs, not scans).
- **Fine-tuning / training your own model** — explicitly cut in Product Spec Section 12 ("Custom/fine-tuned AI model").

---

## 11. Final Tech Stack

| Layer                  | Technology                                                                                                        |
| ----------------------- | -------------------------------------------------------------------------------------------------------------------- |
| Frontend               | React (SPA)                                                                                                        |
| Backend/API            | Laravel (modular monolith, module-per-folder)                                                                     |
| Auth                    | Laravel Sanctum                                                                                                    |
| Database                | PostgreSQL                                                                                                          |
| File Storage           | Laravel Filesystem — local disk, backend-proxied download                                                          |
| PDF Extraction          | `spatie/pdf-to-text` (Poppler), fallback `smalot/pdfparser`                                                        |
| Chunking                | PHP native, deterministic fixed-length (~1,000 characters + ~200 characters overlap)                               |
| RAG/Retrieval           | PostgreSQL filter query (material_id + topic_id)                                                                   |
| AI Integration Layer   | `ai_service` — thin, stateless, in-process Laravel service; the only caller to the configured LLM provider through the LLM Provider Abstraction |
| Default AI Provider    | OpenRouter                                                                                                          |
| Default AI Route/Model | `openrouter/free` — free-model router, dynamically selects available free models                             |
| Optional AI Provider   | Featherless.ai — hackathon partner, used if configured and credits are available                              |
| Dev/Test AI Provider   | Mock AI Provider                                                                                                    |
| Pinned Model (optional)| e.g. `gpt-oss-20b`, `Nemotron 3 Nano 30B A3B` — only if deterministic selection is needed and available          |
| Background Processing  | Synchronous (inline); Laravel Queue `database` driver as a backup option                                        |
| Redis                   | Not used                                                                                                     |
| Vector Database        | Not used                                                                                                     |
| Containerization        | Docker + docker-compose (frontend, backend, db)                                                                    |
| API Communication       | REST/JSON                                                                                                           |
| AI Communication        | HTTPS REST/JSON, OpenAI-compatible (`ai_service` → LLM Provider Abstraction → configured provider)                 |

### Chunking Strategy

Chunking uses **fixed-length chunking** with the following targets:

* Chunk length: **~1,000 characters**
* Chunk overlap: **~200 characters**

Heading-based or heading-regex chunking is **not used**.

Chunking is deterministic and is performed before topic identification.

### AI Provider & Model Strategy

Studyback uses a **provider-agnostic** AI configuration, not fixed to a single provider/model:

* **Default provider:** OpenRouter
* **Default route:** `openrouter/free`
* **Optional provider:** Featherless.ai (hackathon partner, if configured and credits are available)
* **Dev/test provider:** Mock AI Provider
* **Optional pinned model:** e.g. `gpt-oss-20b` or `Nemotron 3 Nano 30B A3B`, only if deterministic model selection is needed and the model is available on the configured provider/plan

`openrouter/free` is used as the default route for all AI use cases in the MVP (topic extraction, quiz generation, Teach Me, answer evaluation). Task-specific pinned model mapping, if used, is optional and documented as an optimization in the AI Architecture document — not as a mandatory Tech Stack baseline.

Fallback follows the layered strategy (provider fallback → model fallback → development fallback) as defined in Section 7.2, and is configurable rather than hardcoded into business logic.

### `ai_service` Architecture

`ai_service` is an **in-process Laravel service**, not a separate service or container.

`ai_service` is responsible for:

* building prompts;
* selecting/configuring the provider and model through the LLM Provider Abstraction;
* sending requests to the configured external LLM provider;
* handling retry/fallback according to configuration;
* validating structured output; and
* providing an abstraction layer between Laravel business logic and the AI provider — so business logic never depends directly on OpenRouter, Featherless, or a specific model.

There is no REST communication between Laravel and `ai_service` because both are within the same Laravel application process.

AI architecture:

**React → Laravel API → `ai_service` → LLM Provider Abstraction → Configured External LLM Provider**

If the default provider/route fails or times out:

**`ai_service` → provider/model fallback per configuration (e.g., retry on `openrouter/free`, then Featherless.ai if configured, then the Mock AI Provider in a development environment)**


This stack is ready to serve as the foundation for the next phase:

```
Tech Stack (this document)
  ↓
Database Design       — schema for materials, topics, subtopics, chunks, sessions, quizzes, learning_state
  ↓
API Design            — route/contract per module (Materials, Processing, StudySession, Quiz, LearningState)
  ↓
AI Architecture        — prompt template per capability (explain, quiz, evaluate, extract), JSON schema per capability, and task-specific model mapping details (optional)
  ↓
UI/UX Design           — Home, My Materials, Material Detail, Study Session Config (modal), Studyback Workspace
```
