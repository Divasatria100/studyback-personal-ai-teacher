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


# Studyback — API Specification / API Design Document (Final)

**Status:** Final, implementation-ready
**Source of truth:** Studyback Product Specification · Studyback System Architecture Blueprint · Studyback Database Design Document (Final)
**Backend Framework:** Laravel (modular monolith, single owner of application state)
**API Style:** REST / JSON
**Authentication:** Laravel Sanctum (Bearer token)
**Database:** PostgreSQL (application database, filter-based retrieval, no vector DB)
**Scope:** 48-hour hackathon MVP

---

## 1. API Design Overview

### Purpose

This document defines the final, concrete REST API contract between the Studyback React frontend and the Laravel backend. It translates the Product Specification (features, flows, user actions), the System Architecture Blueprint (module boundaries, AI/application separation, backend state ownership), and the Database Design Document (entities, relationships, ownership) into an implementation-ready set of endpoints. It is meant to be used directly by an AI IDE and developers during implementation — every endpoint, field, and behavior below traces back to one of the three source documents, or is explicitly flagged as a **Design Decision** where the sources required a concrete but previously unstated choice.

### REST/JSON Approach

- All endpoints are REST resources over HTTP, returning `application/json` (except file upload requests, which use `multipart/form-data`, and file download responses, which stream the raw PDF).
- Resources map to **application capabilities** described in the Product Specification, not to a 1:1 CRUD mirror of every database table. Tables such as `study_session_topics` (a pure pivot) and `quiz_answers` (only ever created through the quiz-answer flow, never listed independently) do not get their own top-level endpoints.
- The API is stateless per request; Laravel identifies the user from the Sanctum token on every call. No session state is held in memory between requests — all durable state lives in PostgreSQL, per Architecture Principle 2 and Database Principle 2.

### Frontend ↔ Backend Boundary

- The React SPA never talks to PostgreSQL, the file storage disk, or the Featherless LLM provider directly. Every action goes through this API.
- The frontend renders UI and calls endpoints; Laravel owns all business logic — routing, ownership checks, material processing coordination, quiz validation/scoring, deterministic mastery calculation, and AI orchestration (Architecture §12).
- The frontend never receives raw AI output before Laravel validates it. Every AI-touched response is the *validated, persisted* result, never a passthrough of the LLM's raw text/JSON.

### Laravel as API Owner

Laravel is the single entry point and the single owner of application state (Architecture Principle 2, Database Principle 2). All authorization, ownership scoping, and state mutation happen inside Laravel. `ai_service` is an in-process Laravel service — it is never reachable as a separate HTTP endpoint from the frontend.

### Authentication/Authorization Approach

- Authentication: Laravel Sanctum, token-based (`personal_access_tokens`, confirmed as the Sanctum table in Database Design §4 `users`). The frontend sends `Authorization: Bearer {token}` on every authenticated request.
- Authorization: every user-owned resource (`materials` and everything that hangs off it — `topics`, `subtopics`, `chunks`, `study_sessions`, `quizzes`, `quiz_questions`, `quiz_answers`) is scoped to `materials.user_id` via a Laravel Policy, exactly as defined in Database Design §17. A user can never read or write another user's data, even with a guessed/valid-looking ID.

---

## 2. API Architecture

### General Application Flow

```
React SPA
  ↓ HTTPS / JSON (Authorization: Bearer {token})
Laravel REST API (routes + controllers)
  ↓
Application Modules (Auth, Materials, Processing, Topics, Study Session, Quiz, Learning State)
  ↓
PostgreSQL (single application database)
```

### AI-Involved Flow

```
React SPA
  ↓ HTTPS / JSON
Laravel API (controller)
  ↓
Application Module (Processing / Study Session / Quiz)
  ↓
ai_service (in-process Laravel service — AI Orchestrator)
  ↓
Featherless API (external LLM provider)
  ↓
ai_service (structured output validation)
  ↓
Application Module (deterministic business logic: scoring, mastery, persistence)
  ↓
PostgreSQL
  ↓
Laravel API → React SPA (JSON response)
```

**Hard boundary:** the React frontend never calls Featherless directly, and `ai_service` never writes to PostgreSQL directly. Every AI result flows back through an Application Module before it is persisted or returned to the client (Architecture §6, §12; Database Principle 2).

---

## 3. API Module Mapping

| Module | API Resources | Responsibility |
|---|---|---|
| **Auth** | `/api/auth/*` | Registration, login, logout, current user. Gatekeeper for every other module (Architecture §5). |
| **Materials** | `/api/materials`, `/api/materials/{material}`, `/api/materials/{material}/download` | Material Library CRUD (create via upload, read/list/detail), Download Material. |
| **Processing** | *(embedded in `POST /api/materials`, no separate endpoint)* | Synchronous pipeline: extract → chunk → AI topic identification → persist. Owns the `materials.status` transition. |
| **Topics / Learning State** | `/api/materials/{material}/topics` | Topic/subtopic structure, current mastery/status — powers the sidebar Learning Map, Material Detail, and Review Weak Topics. |
| **Study Session** | `/api/materials/{material}/study-sessions`, `/api/study-sessions/{studySession}` | Study Session Configuration (mode, difficulty, selected topics), session lifecycle. |
| **AI Orchestration (`ai_service`)** | *(no dedicated endpoint — invoked internally by Study Session and Quiz modules)* | Builds prompts from retrieved context, calls Featherless, validates structured output. Never exposed to the frontend directly. |
| **Explanation (Teach Me / Review)** | `/api/study-sessions/{studySession}/explanations` | Conversational explanation generation, grounded in retrieved material context. |
| **Quiz** | `/api/study-sessions/{studySession}/quizzes`, `/api/quizzes/{quiz}`, `/api/quizzes/{quiz}/questions/{quizQuestion}/answer` | Quiz generation, retrieval, answer submission + evaluation. |
| **Learning State** | *(embedded in Topics endpoint — see §10 Design Decision)* | Deterministic mastery/status, read via the same nested resource used for the Learning Map. |

---

## 4. API Conventions

| Aspect | Convention |
|---|---|
| Base path | `/api` — **no version prefix.** *Design Decision:* the Product Specification defines a single MVP surface with no mention of parallel API generations; versioning would add complexity with no MVP requirement to satisfy (per "No Over-Engineering"). If a breaking change is ever needed post-hackathon, introduce `/api/v2` at that time. |
| URL naming | Plural, lowercase, kebab-case for multi-word resources: `/materials`, `/study-sessions`, `/quiz-questions` (only ever nested, never top-level). Nested resources reflect real ownership: `/materials/{material}/topics`. |
| HTTP methods | `GET` read, `POST` create / trigger an action that isn't a pure update (e.g. submit answer, generate quiz), `PATCH` partial update of an existing resource's state (e.g. complete a session), `DELETE` not used in this MVP (no delete endpoints — see §8). |
| Resource naming | Singular path parameter names bound to the resource: `{material}`, `{studySession}`, `{quiz}`, `{quizQuestion}` — matching Laravel route-model-binding convention. |
| Path parameters | Numeric database IDs (`bigint`), e.g. `/api/materials/42`. |
| Query parameters | `snake_case`, e.g. `?search=`, `?status=`, `?sort=`, `?page=`. |
| Pagination | Simple Laravel `paginate()` (`page`, `per_page`, default `per_page=20`) on `GET /api/materials` only. *Design Decision:* the Product Spec doesn't mandate pagination, but "My Materials" is an open-ended, growing library — Laravel's built-in paginator costs nothing extra to wire up and prevents an unbounded response as the library grows. No other list endpoint in this API needs pagination (topics, quiz questions, etc. are bounded per-material/per-quiz sets). |
| Filtering | `GET /api/materials?search=&status=` only — the only filtering the Product Spec requires (My Materials §4: "melihat, mencari, dan memfilter materi"). No filtering added elsewhere without product basis. |
| Sorting | `GET /api/materials?sort=recent` (default) or `sort=title` — supports both "My Materials" default browse and Home's "Recent Materials" (`?sort=recent&per_page=5`). |
| JSON format | `snake_case` keys throughout, matching database column names directly — keeps the contract mechanically predictable for both AI IDE and developers, avoids a translation layer between DB, backend, and API. |
| IDs | Integers (`bigint` from PostgreSQL `BIGSERIAL`), returned as JSON numbers under `id`. |
| Timestamps | ISO 8601 UTC strings, e.g. `"2026-08-14T10:15:00Z"`. |
| Content-Type | `application/json` for all requests/responses except: `POST /api/materials` (`multipart/form-data`, file upload) and `GET /api/materials/{material}/download` (binary `application/pdf` response). |
| Authentication header | `Authorization: Bearer {token}` on every authenticated endpoint. |

---

## 5. Authentication & Authorization

### Endpoints

| Method | Endpoint | Purpose |
|---|---|---|
| `POST` | `/api/auth/register` | Create a new user account. |
| `POST` | `/api/auth/login` | Authenticate and issue a Sanctum token. |
| `POST` | `/api/auth/logout` | Revoke the current token. |
| `GET` | `/api/auth/me` | Return the currently authenticated user (used by the frontend to hydrate Profile / gate the Home hero and Recent Materials per Product Spec §3). |

### How Sanctum Is Used

- Registration and login issue a Sanctum **personal access token** (`personal_access_tokens` table, per Database Design §4) rather than a cookie-based SPA session — this keeps the frontend/backend contract simple and works regardless of whether the SPA is served from the same origin as the API.
- The frontend stores the token and sends it as `Authorization: Bearer {token}` on every subsequent request.
- `POST /api/auth/logout` calls `$request->user()->currentAccessToken()->delete()`, revoking only the token in use.
- Every route other than `register` and `login` runs through the `auth:sanctum` middleware. An expired/missing/invalid token returns `401 Unauthenticated` (see §16).

### Ownership Scoping

- `materials.user_id` is the single root of ownership (Database Design §17). Every other user-owned table (`topics`, `subtopics`, `chunks`, `study_sessions`, `quizzes`, `quiz_questions`, `quiz_answers`) is reachable only through a foreign-key chain back to a `materials` row owned by the authenticated user.
- A single Laravel Policy (`MaterialPolicy`) is checked on every request that touches a material or anything nested under it: `materials.user_id === auth()->id()`. Nested resources (topics, study sessions, quizzes, etc.) are always loaded *through* their owning material's relationship (e.g. `$material->topics()->findOrFail($id)`), never through a bare global lookup by ID — this guarantees that User A requesting `/api/materials/{User B's material id}/topics` gets `404 Not Found` (not `403`, to avoid confirming the resource's existence — see §16), even though the ID itself is syntactically valid.
- Every endpoint below in §7–§15 documents its specific ownership check under **Authorization**.

---

## 6. Endpoint Inventory

| Method | Endpoint | Purpose | Auth | Module |
|---|---|---|---|---|
| `POST` | `/api/auth/register` | Create account | Public | Auth |
| `POST` | `/api/auth/login` | Log in, issue token | Public | Auth |
| `POST` | `/api/auth/logout` | Revoke current token | Authenticated | Auth |
| `GET` | `/api/auth/me` | Current user | Authenticated | Auth |
| `POST` | `/api/materials` | Upload + create material, run processing pipeline | Authenticated | Materials / Processing |
| `GET` | `/api/materials` | List/search/filter/sort user's materials | Authenticated | Materials |
| `GET` | `/api/materials/{material}` | Material Detail (info + progress summary) | Authenticated | Materials |
| `GET` | `/api/materials/{material}/download` | Download original PDF | Authenticated | Materials |
| `GET` | `/api/materials/{material}/topics` | Topic/subtopic tree with mastery/status (Learning Map + Learning State) | Authenticated | Topics / Learning State |
| `POST` | `/api/materials/{material}/study-sessions` | Start a Study Session (mode, difficulty, topics) | Authenticated | Study Session |
| `GET` | `/api/study-sessions/{studySession}` | Retrieve a Study Session | Authenticated | Study Session |
| `PATCH` | `/api/study-sessions/{studySession}/complete` | Mark a Study Session as completed | Authenticated | Study Session |
| `POST` | `/api/study-sessions/{studySession}/explanations` | Teach Me / Review Weak Topics explanation | Authenticated | AI Orchestration |
| `POST` | `/api/study-sessions/{studySession}/quizzes` | Generate a quiz (Quiz Me / Guided / Review re-test) | Authenticated | Quiz |
| `GET` | `/api/quizzes/{quiz}` | Retrieve quiz + questions + progress/result | Authenticated | Quiz |
| `POST` | `/api/quizzes/{quiz}/questions/{quizQuestion}/answer` | Submit + evaluate one answer, update mastery | Authenticated | Quiz / Learning State |

16 endpoints total. No endpoint exists purely because a database table exists (`study_session_topics` and `quiz_answers` are written only as side effects of the endpoints above, never exposed as their own resource).

---

## 7. Detailed Endpoint Specification

Format follows the required structure per endpoint. AI Interaction is documented only for endpoints that invoke `ai_service`.

### `POST /api/auth/register`

**Purpose**
Create a new Studyback account.

**Authentication**
Public.

**Authorization**
N/A.

**Request Body**
```json
{
  "name": "Jane Student",
  "email": "jane@example.com",
  "password": "SecurePass123",
  "password_confirmation": "SecurePass123"
}
```

**Validation Rules**
- `name`: required, string, max 255.
- `email`: required, valid email, unique in `users.email`.
- `password`: required, string, min 8, confirmed.

**Success Response**
`201 Created`
```json
{
  "user": { "id": 1, "name": "Jane Student", "email": "jane@example.com" },
  "token": "1|abcdef1234567890"
}
```

**Error Responses**
- `422 Unprocessable Entity` — validation errors (e.g. email already taken).

**Business Rules**
Password is hashed (`bcrypt`) before persistence. A Sanctum token is issued immediately so the user is logged in right after registering (no separate login step required).

**Database Effects**
Inserts one row into `users`. Inserts one row into `personal_access_tokens` (Sanctum-managed).

---

### `POST /api/auth/login`

**Purpose**
Authenticate an existing user and issue a token.

**Authentication**
Public.

**Authorization**
N/A.

**Request Body**
```json
{ "email": "jane@example.com", "password": "SecurePass123" }
```

**Validation Rules**
- `email`: required, valid email.
- `password`: required, string.

**Success Response**
`200 OK`
```json
{
  "user": { "id": 1, "name": "Jane Student", "email": "jane@example.com" },
  "token": "2|zyxwvutsrqponm"
}
```

**Error Responses**
- `422 Unprocessable Entity` — validation errors.
- `401 Unauthenticated` — email/password mismatch.

**Business Rules**
Credentials are verified with `Hash::check`. A new personal access token is issued per login (multiple tokens/devices are allowed; this is Sanctum default behavior).

**Database Effects**
Inserts one row into `personal_access_tokens`.

---

### `POST /api/auth/logout`

**Purpose**
Revoke the current session's token.

**Authentication**
Authenticated.

**Authorization**
A user can only revoke their own current token (enforced implicitly — Sanctum resolves the token from the request itself).

**Request Body**
None.

**Success Response**
`200 OK`
```json
{ "message": "Logged out successfully." }
```

**Error Responses**
- `401 Unauthenticated` — missing/invalid token.

**Business Rules**
Only the token used to authenticate this request is revoked; other active sessions/devices remain valid.

**Database Effects**
Deletes one row from `personal_access_tokens`.

---

### `GET /api/auth/me`

**Purpose**
Return the currently authenticated user, used to hydrate the Profile UI and to distinguish logged-in vs. logged-out Home/Recent Materials rendering (Product Spec §3).

**Authentication**
Authenticated.

**Authorization**
Returns only the requester's own record — there is no path parameter to spoof.

**Success Response**
`200 OK`
```json
{ "id": 1, "name": "Jane Student", "email": "jane@example.com", "created_at": "2026-01-10T08:00:00Z" }
```

**Error Responses**
- `401 Unauthenticated`.

**Business Rules / Database Effects**
Read-only; no state change.

---

## 8. Material API

Endpoints designed against Product Spec §3–5 (Home upload flow, My Materials, Material Detail) and Database Design (`materials` table + processing status). No `DELETE` endpoint is included — the Product Specification never shows a delete action anywhere in Home, My Materials, or Material Detail, so adding one would be an unrequested endpoint (per instruction, and per Consistency Rule #14). Material creation and file upload are combined into one endpoint because the Product Spec's upload flow is a single unbroken user action ("Upload Material → Uploading… → … → Material Ready"), and processing is synchronous (Database Design §10) — there is nothing for a separate "create" step to do that upload doesn't already do.

### `POST /api/materials`

**Purpose**
Upload a PDF and run the entire synchronous processing pipeline (extract → clean → chunk → AI topic/subtopic identification → persist), returning the finished material in one response. Powers the Home upload flow end-to-end (Product Spec §3).

**Authentication**
Authenticated.

**Authorization**
The created material is automatically owned by `auth()->id()`; no ownership check needed on create.

**Request Body** (`multipart/form-data`)
```
file: <binary PDF>
title: "Object Oriented Programming"        // optional — defaults to filename if omitted
description: "Lecture notes, semester 3"    // optional
```

**Validation Rules**
- `file`: required, mimetype `application/pdf`, max size per hackathon cap (e.g. 20 MB — Design Decision: Architecture §14 requires "reasonable size cap" without a number; 20 MB comfortably covers lecture-slide PDFs while bounding synchronous processing time).
- `title`: optional, string, max 255.
- `description`: optional, string.
- File is additionally confirmed to be a parseable PDF before processing begins (Architecture §14) — checked in the Processing module, not just by MIME sniffing.

**Success Response**
`201 Created`
```json
{
  "id": 12,
  "title": "Object Oriented Programming",
  "description": "Lecture notes, semester 3",
  "original_filename": "oop-notes.pdf",
  "file_size_bytes": 842104,
  "status": "ready",
  "topics_count": 5,
  "overall_mastery": 0,
  "created_at": "2026-08-14T09:00:00Z"
}
```

**Error Responses**
- `422 Unprocessable Entity` — validation failure (wrong file type, missing file, too large).
- `422 Unprocessable Entity` with `{"status": "failed", "failed_reason": "..."}` — pipeline failed (extraction unreadable, AI topic extraction failed after one retry, structured output invalid after one retry). The material row is still created (status `failed`) so it's visible in My Materials for re-upload context, per Architecture §13.
- `503 Service Unavailable` — AI provider (both primary and fallback) unreachable/timed out after retry (see §14).

**Business Rules**
1. Store the uploaded PDF via Laravel Filesystem (`storage/app/private`), generate `file_path`.
2. Insert `materials` row with `status = 'processing'`.
3. Extract text (`spatie/pdf-to-text`), clean it (in-memory, not persisted).
4. Chunk deterministically: fixed-length ~1,000 characters with ~200 character overlap, no heading detection.
5. Call `ai_service` for topic/subtopic identification (see AI Interaction below).
6. On success, in **one database transaction**: insert `topics`, insert `subtopics`, insert `chunks` (tagged with `topic_id`/`subtopic_id`), update `materials.status = 'ready'`.
7. On any failure at any stage, roll back the transaction and update `materials.status = 'failed'` with `failed_reason` as a separate, single update (Database Design §10, §15) — no partial material is ever left with some but not all of its topics/chunks.

**Database Effects**
Creates: 1 `materials` row, N `topics` rows, N `subtopics` rows, N `chunks` rows (all within one transaction on success). On failure: only the `materials` row exists, with `status = 'failed'`.

**AI Interaction**
```
Frontend (multipart upload)
  → Laravel (Materials/Processing controller)
    → validates file, extracts text, chunks deterministically (no AI)
  → ai_service (topic/subtopic identification)
    → constructs prompt: role/instruction ("identify topics and subtopics from this material") + full chunked text
    → Featherless primary model: Qwen3.6-27B
    → on failure/timeout: retry once with Qwen3.6-27B; if still failing, fall back to gpt-oss-20b
    → validates response is well-formed JSON matching the expected topic/subtopic schema
    → on invalid structure: retry generation once; if still invalid, treat as pipeline failure
  → Laravel: receives structured topic/subtopic list, tags each chunk with the topic/subtopic it belongs to
  → PostgreSQL: persists topics, subtopics, chunks, updates materials.status (one transaction)
  → Laravel → Frontend: final material JSON (status ready/failed)
```
AI never writes to PostgreSQL directly; Laravel persists only after receiving and validating the structured result.

---

### `GET /api/materials`

**Purpose**
List the authenticated user's materials — serves both "My Materials" (search + filter + browse) and Home's "Recent Materials" (`sort=recent&per_page=5`), per Product Spec §3–4.

**Authentication**
Authenticated.

**Authorization**
Implicitly scoped: `Material::where('user_id', auth()->id())` — never returns another user's materials.

**Query Parameters**

| Parameter | Type | Required | Description |
|---|---|---|---|
| `search` | string | No | Case-insensitive match against `title`. |
| `status` | string | No | Filter by `processing`, `ready`, or `failed`. |
| `sort` | string | No | `recent` (default, by `created_at` desc) or `title` (A–Z). |
| `page` | integer | No | Default 1. |
| `per_page` | integer | No | Default 20, max 50. |

**Success Response**
`200 OK`
```json
{
  "data": [
    {
      "id": 12,
      "title": "Object Oriented Programming",
      "description": "Lecture notes, semester 3",
      "status": "ready",
      "topics_count": 5,
      "overall_mastery": 72,
      "created_at": "2026-08-14T09:00:00Z"
    }
  ],
  "meta": { "current_page": 1, "per_page": 20, "total": 1 }
}
```

**Error Responses**
- `401 Unauthenticated`.

**Business Rules**
`overall_mastery` is computed on the fly as `AVG(subtopics.mastery_score)` across the material's subtopics (Database Design §8) — not a stored column, so it's always fresh.

**Database Effects**
Read-only.

---

### `GET /api/materials/{material}`

**Purpose**
Material Detail — information, topic count, learning progress, and the actions available (Download, Start Study Session), per Product Spec §5.

**Authentication**
Authenticated.

**Authorization**
`404 Not Found` if `materials.user_id !== auth()->id()`.

**Path Parameters**

| Parameter | Type | Required | Description |
|---|---|---|---|
| `material` | integer | Yes | Material ID. |

**Success Response**
`200 OK`
```json
{
  "id": 12,
  "title": "Object Oriented Programming",
  "description": "Lecture notes, semester 3",
  "original_filename": "oop-notes.pdf",
  "file_size_bytes": 842104,
  "status": "ready",
  "topics_count": 5,
  "overall_mastery": 72,
  "created_at": "2026-08-14T09:00:00Z"
}
```

**Error Responses**
- `401 Unauthenticated`.
- `404 Not Found` — material doesn't exist or isn't owned by the requester.

**Business Rules**
`overall_mastery` follows the same on-the-fly aggregation as the list endpoint. If the material has never been studied, `overall_mastery` is `0` and the frontend renders "Not Started" per Product Spec §5.

**Database Effects**
Read-only.

---

### `GET /api/materials/{material}/download`

**Purpose**
Download the original uploaded PDF (Product Spec §5, Material Detail action).

**Authentication**
Authenticated.

**Authorization**
`404 Not Found` if not owned by requester. There is no public/guessable URL to the file — this endpoint is the only access path (Database Design §11, §17).

**Path Parameters**

| Parameter | Type | Required | Description |
|---|---|---|---|
| `material` | integer | Yes | Material ID. |

**Success Response**
`200 OK` — binary stream, `Content-Type: application/pdf`, `Content-Disposition: attachment; filename="{original_filename}"`.

**Error Responses**
- `401 Unauthenticated`.
- `404 Not Found`.

**Business Rules**
Served via `Storage::download()` after the ownership check — never a redirect to a public/signed URL exposing `file_path` directly.

**Database Effects**
Read-only (reads `materials.file_path`, `materials.original_filename`).

---

## 9. Material Processing API

Processing is **not** a separate set of endpoints. Per Product Spec §3 and Database Design §10, the pipeline (extract → clean → chunk → topic/subtopic identification → persist) runs entirely inside the single `POST /api/materials` request, synchronously. The granular UI stages shown in the spec ("Uploading… → Extracting Content… → Understanding Material… → Identifying Topics…") are ephemeral frontend loading-state copy displayed while that one request is in flight — they are not backed by distinct API calls or persisted states (Database Design §3 Design Decision). There is no polling/status endpoint because there is no background worker to poll (per architecture constraint: synchronous baseline, no queue). If the request succeeds, the response already contains `status: "ready"`; if it fails, the response already contains `status: "failed"` and `failed_reason`. A subsequent `GET /api/materials/{material}` also reflects the same `status` field if the frontend ever needs to re-check it (e.g. after a page refresh mid-upload), but no dedicated processing-status route exists.

---

## 10. Topic & Subtopic API

**Design Decision:** Topics/Subtopics and Learning State are exposed through **one** nested, read-only endpoint rather than two. The `subtopics` table stores structure (`name`, `description`, `order_index`) and current learning state (`mastery_score`, `status`) as columns on the *same row* (Database Design §4, §8) — there is no architectural or data separation between "topic structure" and "learning state" to justify two endpoints. This single endpoint serves three UI needs at once: the sidebar Learning Map (Product Spec §7.3–7.4), the Material Detail topic list (§5), and Review Weak Topics' filtering (§7.2, by `status = needs_review`, done client-side against this same payload).

Chosen shape: **nested resource** under material (`/api/materials/{material}/topics`), not a flat `/api/topics?material_id=` — because topics are never meaningfully listed outside the context of one material, and nesting makes the ownership scope explicit in the route itself. Subtopics are returned inline (embedded array) rather than as their own endpoint, since the sidebar always needs the full topic+subtopic tree at once, never subtopics in isolation.

### `GET /api/materials/{material}/topics`

**Purpose**
Return the full topic/subtopic tree for a material, including current mastery and status — powers the Studyback Workspace sidebar Learning Map and the Material Detail topic list.

**Authentication**
Authenticated.

**Authorization**
`404 Not Found` if the material isn't owned by the requester (topics are only ever reached through their owning material).

**Path Parameters**

| Parameter | Type | Required | Description |
|---|---|---|---|
| `material` | integer | Yes | Material ID. |

**Success Response**
`200 OK`
```json
{
  "material_id": 12,
  "overall_mastery": 72,
  "topics": [
    {
      "id": 101,
      "name": "Inheritance",
      "description": "How classes derive behavior from other classes.",
      "order_index": 2,
      "subtopics": [
        {
          "id": 1042,
          "name": "Polymorphism",
          "description": "Same interface, different implementations.",
          "order_index": 0,
          "mastery_score": 42,
          "status": "needs_review"
        }
      ]
    }
  ]
}
```

**Error Responses**
- `401 Unauthenticated`.
- `404 Not Found`.

**Business Rules**
- `status` values map directly to sidebar symbols: `not_started` → `○`, `in_progress` → `◐`, `needs_review` → `⚠`, `mastered` → `✓` (Product Spec §7.4, Database Design §7).
- Topics/subtopics are ordered by `order_index` ascending, matching the order the AI identified them in.
- `overall_mastery` is the same on-the-fly `AVG(subtopics.mastery_score)` used elsewhere.

**Database Effects**
Read-only.

---

## 11. Study Session API

Designed against Product Spec §6–7 (Study Session Configuration, Studyback Workspace, all four Learning Modes living in one workspace) and Database Design §9 (`study_sessions`, `study_session_topics`).

### `POST /api/materials/{material}/study-sessions`

**Purpose**
Start a Study Session — created both from Home's "Start Learning" (after upload) and from Material Detail's "Start Study Session" → Study Session Configuration modal (Product Spec §3, §6). This single endpoint covers both entry paths; "Start Learning" simply calls it with default configuration (all topics, `guided_study_session`, no difficulty override).

**Authentication**
Authenticated.

**Authorization**
`404 Not Found` if the material isn't owned by the requester. The created session is owned by `auth()->id()`.

**Path Parameters**

| Parameter | Type | Required | Description |
|---|---|---|---|
| `material` | integer | Yes | Material to study. |

**Request Body**
```json
{
  "mode": "guided_study_session",
  "difficulty": "medium",
  "topic_ids": [101, 102, 105]
}
```

**Validation Rules**
- `mode`: required, one of `teach_me`, `quiz_me`, `review_weak_topics`, `guided_study_session`.
- `difficulty`: nullable, one of `easy`, `medium`, `hard` (nullable because Teach Me alone doesn't require it, per Database Design §4).
- `topic_ids`: required, array of integers, each must belong to `{material}` (validated against `materials.id`'s own topics — prevents attaching another material's topic).

**Success Response**
`201 Created`
```json
{
  "id": 77,
  "material_id": 12,
  "mode": "guided_study_session",
  "difficulty": "medium",
  "status": "active",
  "topic_ids": [101, 102, 105],
  "started_at": "2026-08-14T09:10:00Z"
}
```

**Error Responses**
- `422 Unprocessable Entity` — invalid mode/difficulty, or a `topic_id` that doesn't belong to `{material}`.
- `404 Not Found` — material not owned by requester.

**Business Rules**
Inserts one `study_sessions` row (`status = 'active'`, `started_at = now()`) and one `study_session_topics` row per selected topic ID, in a single transaction.

**Database Effects**
Creates: 1 `study_sessions` row, N `study_session_topics` rows.

---

### `GET /api/study-sessions/{studySession}`

**Purpose**
Retrieve a Study Session's current configuration/state — used by the Workspace to restore context (mode, difficulty, selected topics) on load/refresh.

**Authentication**
Authenticated.

**Authorization**
`404 Not Found` if `study_sessions.user_id !== auth()->id()`.

**Path Parameters**

| Parameter | Type | Required | Description |
|---|---|---|---|
| `studySession` | integer | Yes | Study Session ID. |

**Success Response**
`200 OK`
```json
{
  "id": 77,
  "material_id": 12,
  "mode": "guided_study_session",
  "difficulty": "medium",
  "status": "active",
  "topic_ids": [101, 102, 105],
  "started_at": "2026-08-14T09:10:00Z",
  "ended_at": null
}
```

**Error Responses**
- `401 Unauthenticated`.
- `404 Not Found`.

**Database Effects**
Read-only.

---

### `PATCH /api/study-sessions/{studySession}/complete`

**Purpose**
Mark a Study Session as finished when the user leaves the Studyback Workspace back to My Materials (Product Spec §8.1 end-of-loop: "Kembali ke My Materials").

**Authentication**
Authenticated.

**Authorization**
`404 Not Found` if not owned by requester.

**Path Parameters**

| Parameter | Type | Required | Description |
|---|---|---|---|
| `studySession` | integer | Yes | Study Session ID. |

**Request Body**
None.

**Success Response**
`200 OK`
```json
{ "id": 77, "status": "completed", "ended_at": "2026-08-14T09:40:00Z" }
```

**Error Responses**
- `401 Unauthenticated`.
- `404 Not Found`.
- `409 Conflict` — session is already `completed`.

**Business Rules**
Sets `status = 'completed'`, `ended_at = now()`. Idempotent guard: cannot re-complete an already-completed session.

**Database Effects**
Updates 1 `study_sessions` row.

---

## 12. Quiz API

Designed against Product Spec §7.2 (Quiz Me, structured interface, Review Weak Topics re-test, Guided Study Session's Test/Evaluate stages) and Database Design §4/§9 (`quizzes`, `quiz_questions`, `quiz_answers`).

### `POST /api/study-sessions/{studySession}/quizzes`

**Purpose**
Generate a new quiz for the active session — used by Quiz Me, the Test stage of Guided Study Session, and Review Weak Topics' re-test (Product Spec §7.2), as well as "Try Quiz Again".

**Authentication**
Authenticated.

**Authorization**
`404 Not Found` if the study session isn't owned by the requester.

**Path Parameters**

| Parameter | Type | Required | Description |
|---|---|---|---|
| `studySession` | integer | Yes | The active study session this quiz belongs to. |

**Request Body**
```json
{
  "topic_id": 101,
  "subtopic_id": 1042,
  "difficulty": "medium",
  "question_count": 5
}
```

**Validation Rules**
- `topic_id`: required, must belong to the session's material.
- `subtopic_id`: nullable, must belong to `topic_id` if provided — set when scope is narrowed to a single subtopic (used by Review Weak Topics per Database Design §4).
- `difficulty`: nullable, one of `easy`, `medium`, `hard` (defaults to the session's `difficulty` if omitted).
- `question_count`: nullable integer, 3–10, default 5 (Design Decision: Product Spec's example shows a 5-question quiz; a small configurable range keeps generation fast within the synchronous request while allowing Review Weak Topics to request a shorter "mini-question" set, per Product Spec §7.2).

**Success Response**
`201 Created`
```json
{
  "id": 501,
  "study_session_id": 77,
  "topic_id": 101,
  "subtopic_id": 1042,
  "difficulty": "medium",
  "status": "in_progress",
  "total_questions": 5,
  "questions": [
    {
      "id": 9001,
      "subtopic_id": 1042,
      "question_type": "multiple_choice",
      "question_text": "Which statement best explains polymorphism?",
      "options": ["...", "...", "...", "..."],
      "order_index": 0
    }
  ]
}
```
Note: `correct_answer` is **never** included in this response — only exposed internally to Laravel for evaluation.

**Error Responses**
- `422 Unprocessable Entity` — invalid topic/subtopic/difficulty, or insufficient material context to generate questions (see AI Interaction retrieval failure below).
- `404 Not Found` — study session not owned by requester.
- `503 Service Unavailable` — AI provider failure after retry + fallback.

**Business Rules**
Laravel validates the AI-generated question set's JSON shape (type, options for multiple-choice, a correct answer reference, target subtopic) before accepting it — invalid structure triggers one retry, then a hard failure per Architecture §13.

**Database Effects**
Creates: 1 `quizzes` row (`status = 'in_progress'`), N `quiz_questions` rows.

**AI Interaction**
```
Frontend → Laravel (Quiz controller)
  → Laravel validates topic_id/subtopic_id belong to session's material
  → Retrieval: SELECT content FROM chunks WHERE material_id = ? AND (topic_id = ? OR subtopic_id = ?) ORDER BY chunk_index
  → ai_service builds prompt: role/instruction ("generate {question_count} {difficulty} questions") + retrieved chunks + task input (difficulty)
  → Featherless primary: Qwen3.6-27B (fallback: gpt-oss-20b on failure/timeout)
  → ai_service validates JSON shape (question array, types, options, correct_answer, subtopic reference)
  → invalid → retry generation once → still invalid → 422/503 (no partial quiz persisted)
  → Laravel: persists quizzes + quiz_questions (one transaction)
  → Laravel → Frontend: quiz + questions (correct_answer stripped)
```
If retrieval finds no chunks for the requested topic/subtopic, Laravel returns `422 Unprocessable Entity` before ever calling the LLM (Architecture §13 — insufficient context is an application-level failure, not something to paper over with an LLM guess).

---

### `GET /api/quizzes/{quiz}`

**Purpose**
Retrieve a quiz's current state — questions, which have been answered so far, and (once completed) the score/result summary shown on the "Quiz Complete" screen (Product Spec §7.2).

**Authentication**
Authenticated.

**Authorization**
`404 Not Found` if the quiz's study session isn't owned by the requester.

**Path Parameters**

| Parameter | Type | Required | Description |
|---|---|---|---|
| `quiz` | integer | Yes | Quiz ID. |

**Success Response**
`200 OK` (in progress)
```json
{
  "id": 501,
  "status": "in_progress",
  "total_questions": 5,
  "correct_count": 2,
  "score": null,
  "questions": [
    { "id": 9001, "question_text": "...", "options": ["..."], "answered": true, "is_correct": true },
    { "id": 9002, "question_text": "...", "options": ["..."], "answered": false }
  ]
}
```
`200 OK` (completed)
```json
{
  "id": 501,
  "status": "completed",
  "total_questions": 5,
  "correct_count": 3,
  "score": 60,
  "completed_at": "2026-08-14T09:25:00Z",
  "topic_performance": [
    { "subtopic_id": 1042, "subtopic_name": "Polymorphism", "mastery_score": 42, "status": "needs_review" }
  ]
}
```

**Error Responses**
- `401 Unauthenticated`.
- `404 Not Found`.

**Business Rules**
`topic_performance` (used for the "Topic Performance" breakdown on the Quiz Complete screen, Product Spec §7.2) reflects the *current* `subtopics.mastery_score`/`status` for every subtopic targeted by this quiz's questions — the same live values used by the sidebar, not a frozen snapshot, per Database Design §8.

**Database Effects**
Read-only.

---

### `POST /api/quizzes/{quiz}/questions/{quizQuestion}/answer`

**Purpose**
Submit and evaluate the answer to one quiz question — the "[Submit Answer]" action (Product Spec §7.2). Triggers AI evaluation, persists the result, and deterministically updates the target subtopic's mastery/status. If this is the quiz's last unanswered question, also completes the quiz.

**Authentication**
Authenticated.

**Authorization**
`404 Not Found` if the quiz isn't owned by the requester (via its study session's `user_id`).

**Path Parameters**

| Parameter | Type | Required | Description |
|---|---|---|---|
| `quiz` | integer | Yes | Quiz ID. |
| `quizQuestion` | integer | Yes | Must belong to `{quiz}`. |

**Request Body**
```json
{ "submitted_answer": "B" }
```

**Validation Rules**
- `submitted_answer`: required, string.
- The question must not already have an answer (`quiz_answers.quiz_question_id` is `UNIQUE` — Database Design §4); re-submitting an already-answered question returns `409 Conflict`.
- The quiz must not already be `completed`.

**Success Response**
`200 OK`
```json
{
  "quiz_question_id": 9001,
  "submitted_answer": "B",
  "is_correct": true,
  "ai_feedback": "Correct — polymorphism lets a single interface represent different underlying forms.",
  "quiz_status": "in_progress",
  "subtopic": { "id": 1042, "mastery_score": 55, "status": "in_progress" }
}
```
If this was the final question:
```json
{
  "quiz_question_id": 9005,
  "submitted_answer": "True",
  "is_correct": false,
  "ai_feedback": "Not quite — encapsulation restricts direct access, it doesn't prevent inheritance.",
  "quiz_status": "completed",
  "quiz_result": { "correct_count": 3, "total_questions": 5, "score": 60 },
  "subtopic": { "id": 1043, "mastery_score": 91, "status": "mastered" }
}
```

**Error Responses**
- `422 Unprocessable Entity` — missing `submitted_answer`.
- `404 Not Found` — quiz/question not owned by requester, or question doesn't belong to `{quiz}`.
- `409 Conflict` — question already answered, or quiz already completed.
- `503 Service Unavailable` — AI evaluation fails after retry (Learning State is **not** touched in this case — see Business Rules).

**Business Rules**
1. Laravel sends the question, `correct_answer` (internal reference), and `submitted_answer` to `ai_service` for evaluation.
2. On success, within **one database transaction** (Database Design §15):
   - Insert `quiz_answers` (`is_correct`, `ai_feedback` from AI, `submitted_answer`, `answered_at`).
   - Recompute `subtopics.mastery_score` for this question's `subtopic_id` as `AVG(is_correct ? 100 : 0)` across **all** `quiz_answers` ever recorded for that subtopic (cumulative, per Database Design §8 — not just this quiz), then derive `status` from the fixed thresholds (`<60%` → `needs_review`, `60–79%` → `in_progress`, `≥80%` → `mastered`).
   - If every question on `{quiz}` now has an answer: update `quizzes.correct_count`, `quizzes.score = correct_count / total_questions * 100`, `quizzes.status = 'completed'`, `quizzes.completed_at = now()`.
3. If AI evaluation fails (after one retry + fallback model), the request fails with `503` and **no** database write occurs at all — prior Learning State is left untouched, per Architecture §13's guiding principle ("never let an AI failure silently corrupt Learning State").

**Database Effects**
Creates: 1 `quiz_answers` row. Updates: 1 `subtopics` row (mastery/status). Conditionally updates: 1 `quizzes` row (only when the quiz becomes `completed`).

**AI Interaction**
```
Frontend → Laravel (Quiz controller)
  → Laravel loads quiz_question.correct_answer (never sent to frontend)
  → ai_service builds prompt: role/instruction ("evaluate this answer against the correct answer, return correct/incorrect + feedback") + question + correct_answer + submitted_answer
  → Featherless primary: Qwen3.6-27B (fallback: gpt-oss-20b)
  → ai_service validates structured verdict: { is_correct: bool, feedback: string }
  → invalid/failed after retry → 503, no persistence
  → Laravel: DB::transaction() → insert quiz_answers, recompute subtopics.mastery_score/status, conditionally complete quiz
  → Laravel → Frontend: evaluation result + updated subtopic state (+ quiz result if final question)
```
The LLM's verdict is treated as *input* to Laravel's deterministic scoring, not as the final authority on state — Laravel is what actually computes and persists `mastery_score`/`status` (Architecture §5, §6).

---

## 13. Learning State API

Learning State has **no standalone endpoint** — it is read through `GET /api/materials/{material}/topics` (§10) and updated only as a side effect of `POST /api/quizzes/{quiz}/questions/{quizQuestion}/answer` (§12). This mirrors the Database Design directly: `mastery_score` and `status` are columns on `subtopics`, not a separate table (Database Design §8), so there is nothing for a dedicated Learning State endpoint to serve that the Topics endpoint doesn't already provide.

- **User ownership:** enforced transitively — every subtopic is reached only through a topic, which is reached only through a material owned by `auth()->id()`.
- **Topic/subtopic scope:** `mastery_score`/`status` live at the subtopic level (Product Spec §8.2); `overall_mastery` at the material level is derived on read, never stored (Database Design §8).
- **Score/attempt data:** the full history of attempts is the `quiz_answers` table itself (immutable, insert-only) — retrievable in aggregate via the mastery formula in §12, with no separate history endpoint required by the product (no screen in the spec shows a raw attempt-by-attempt log).
- **State transitions:** `not_started` (no `quiz_answers` yet for that subtopic) → `needs_review` / `in_progress` / `mastered`, recomputed after every answered question via the fixed threshold table (§7 below), always by Laravel, never by the LLM.

---

## 14. AI-Powered API Operations

Two endpoints invoke `ai_service`, plus the embedded material-processing step. Primary model: **`Qwen3.6-27B`**. Fallback (on primary failure/timeout): **`gpt-oss-20b`**. The frontend never calls Featherless directly in any of these flows.

### 14.1 Topic/Subtopic Identification (embedded in `POST /api/materials`)

1. **Trigger:** frontend uploads a PDF via `POST /api/materials`.
2. **Laravel validation:** file type/size/parseability checked before any AI call.
3. **Data retrieval:** none from the database (the material is brand new) — input is the freshly extracted and chunked text itself.
4. **Prompt construction:** role/instruction ("identify the topics and subtopics covered by this material") + the full chunked text.
5. **`ai_service`:** builds and sends the prompt.
6. **Primary model:** `Qwen3.6-27B`.
7. **Fallback model:** `gpt-oss-20b`, used if the primary fails or times out (one retry on the primary first, then fallback).
8. **Structured output validation:** JSON array of `{ name, description, subtopics: [{ name, description }] }`; invalid shape triggers one regeneration retry, then pipeline failure.
9. **Laravel business logic:** tags each chunk with the `topic_id`/`subtopic_id` it belongs to (based on the same AI response).
10. **Database persistence:** `topics`, `subtopics`, `chunks`, `materials.status = 'ready'` — one transaction.
11. **Response to frontend:** finished material JSON (`status: "ready"` or `"failed"`).

### 14.2 Explanation (Teach Me / Review Weak Topics)

1. **Trigger:** frontend calls `POST /api/study-sessions/{studySession}/explanations`.
2. **Laravel validation:** `subtopic_id` (or `topic_id`) must belong to the session's material; session must be `active`.
3. **Data retrieval:** `SELECT content FROM chunks WHERE material_id = ? AND (topic_id = ? OR subtopic_id = ?) ORDER BY chunk_index` — filter-based, per Architecture §8.
4. **Prompt construction:** role/instruction (explain / simplify / give example / review-with-different-approach, based on `intent`) + retrieved chunks + the user's optional follow-up `message`.
5. **`ai_service`:** builds and sends the prompt.
6. **Primary model:** `Qwen3.6-27B`.
7. **Fallback model:** `gpt-oss-20b` on failure/timeout.
8. **Structured output validation:** not applicable — explanation is conversational free text (Product Spec §9.1 lists only topic extraction, quiz generation, answer evaluation, and learning-state-related output as needing structured JSON; explanation is not in that list).
9. **Laravel business logic:** none beyond passing the response through — explanations are not scored or used to mutate Learning State directly.
10. **Database persistence:** none — Teach Me/Review conversations are not persisted (Database Design §3, §9: no chat-log table exists; each explanation is generated fresh from retrieval every time).
11. **Response to frontend:** the explanation text.

### `POST /api/study-sessions/{studySession}/explanations`

**Purpose**
Generate a grounded explanation for a topic/subtopic — powers Teach Me and, when triggered from a `⚠ Needs Review` sidebar click, Review Weak Topics (Product Spec §7.2, §7.4).

**Authentication**
Authenticated.

**Authorization**
`404 Not Found` if the study session isn't owned by the requester.

**Path Parameters**

| Parameter | Type | Required | Description |
|---|---|---|---|
| `studySession` | integer | Yes | Active study session. |

**Request Body**
```json
{
  "subtopic_id": 1042,
  "intent": "explain",
  "message": "Can you give me a real-world example?"
}
```

**Validation Rules**
- `subtopic_id`: required, must belong to the session's material.
- `intent`: required, one of `explain`, `simplify`, `example`, `review` (Design Decision: the Product Spec shows these as user-facing follow-up actions — "Explain simpler", "Give example" §7.2 — modeled here as an `intent` enum so the frontend doesn't need free-text prompt engineering; `review` is used when the workspace focuses the AI Teacher on a `⚠` subtopic per §7.4).
- `message`: nullable string, max 2000 — an optional free-form follow-up question, layered on top of `intent`.

**Success Response**
`200 OK`
```json
{
  "subtopic_id": 1042,
  "explanation": "Polymorphism lets objects of different classes be treated through a common interface..."
}
```

**Error Responses**
- `422 Unprocessable Entity` — invalid `subtopic_id`/`intent`, or no chunks found for that subtopic (insufficient context — Laravel tells the AI to say so rather than answering from general knowledge, per Architecture §13; the frontend receives this as a normal `200` explanation stating the material doesn't cover it, not an error, since it's a valid AI answer).
- `404 Not Found` — session not owned by requester.
- `503 Service Unavailable` — AI provider failure after retry + fallback.

**Business Rules**
Purely conversational — no scoring, no Learning State mutation. Nothing is persisted beyond the standard request log.

**Database Effects**
Read-only (chunk retrieval).

**AI Interaction**
See 14.2 above.

### 14.3 Answer Evaluation

Covered in full under §12, `POST /api/quizzes/{quiz}/questions/{quizQuestion}/answer` — AI Interaction subsection.

---

## 15. Retrieval API

There is **no retrieval endpoint exposed to the frontend.** The Product Specification and Architecture describe retrieval strictly as an internal step between the Application layer and the database (`Material + Topic/Subtopic → PostgreSQL filtering → Relevant Chunks → AI Response`, Architecture §8) — the frontend never asks for "chunks," it asks for an explanation or a quiz, and retrieval happens invisibly inside those two endpoints (§12, §14.2). Exposing chunk content directly would leak an internal implementation detail the frontend never needs (per instruction: "Pastikan endpoint tidak mengekspos internal retrieval implementation secara tidak perlu").

Internal retrieval query (used inside `ai_service`, not a route):
```sql
SELECT content FROM chunks
WHERE material_id = :material_id
  AND (topic_id = :topic_id OR subtopic_id = :subtopic_id)
ORDER BY chunk_index ASC;
```
Supported by `idx_chunks_material_topic` and `idx_chunks_material_subtopic` (Database Design §6). No vector search, no embeddings, no similarity ranking — pure relational filter, exactly per architecture constraint.

---

## 16. Error Handling

All error responses share one JSON shape:

```json
{ "message": "Human-readable summary.", "errors": { "field": ["Specific validation message."] } }
```
`errors` is present only for `422` validation failures; other statuses return just `message` (and, where relevant, extra fields documented per-endpoint, e.g. `failed_reason` on material processing failure).

| Case | HTTP Status | Notes |
|---|---|---|
| Validation error | `422 Unprocessable Entity` | Laravel's default `FormRequest`/validator response shape, used as-is. |
| Unauthenticated | `401 Unauthenticated` | Missing, invalid, or expired Sanctum token. |
| Unauthorized / not owned | `404 Not Found` | Per §5: a resource that exists but isn't owned by the requester returns `404`, not `403` — this avoids confirming to an attacker that a given ID exists at all. |
| Not found (resource genuinely doesn't exist) | `404 Not Found` | Same response shape as the ownership case above — indistinguishable by design. |
| Conflict | `409 Conflict` | E.g. re-completing a session, re-answering an already-answered question. |
| Material processing failure | `422 Unprocessable Entity` | Includes `{"status": "failed", "failed_reason": "..."}`; the material row still exists so it remains visible/re-uploadable. |
| AI provider failure (primary + fallback both fail) | `503 Service Unavailable` | Used for explanation generation, quiz generation, and answer evaluation after retry + fallback are exhausted. No partial state is ever persisted from a failed AI call. |
| Unexpected server error | `500 Internal Server Error` | Laravel's default exception handler; logged server-side, generic message returned to the client. |

---

## 17. API Security

- **Authentication:** Sanctum Bearer tokens on every route except `register`/`login` (§5).
- **Authorization:** every material-owned resource scoped via `MaterialPolicy`, enforced on every read/write, never trusting a client-supplied ID alone (§5, and per-endpoint **Authorization** sections above).
- **Ownership scoping:** all nested resources (`topics`, `subtopics`, `study_sessions`, `quizzes`, `quiz_questions`, `quiz_answers`) are queried only through their owning material's relationship chain — never a bare global lookup by primary key.
- **Request validation:** every write endpoint validates its payload with Laravel `FormRequest` classes before touching the database (see each endpoint's **Validation Rules**).
- **File upload validation:** `POST /api/materials` restricts MIME type to `application/pdf`, enforces a size cap, and confirms the file actually parses as a PDF before the pipeline runs (Architecture §14).
- **Uploaded file access:** never served from a public/guessable URL — always `GET /api/materials/{material}/download`, authenticated and ownership-checked (Database Design §11, §17).
- **Rate limiting:** Laravel's default `throttle:api` middleware (60 requests/minute per authenticated user) is sufficient for MVP. *Design Decision:* no stricter or custom limiting is added — none of the three source documents calls for it, and adding bespoke rate-limit tiers would be unrequested infrastructure per "No Over-Engineering."
- **AI input/output validation:** only the material content relevant to the current user's selected material/topic is ever sent to Featherless — no cross-user data enters a single prompt (Architecture §14). All structured AI output (topics, quiz questions, evaluation verdicts) is schema-validated by Laravel before persistence; a `correct_answer` is never returned to the frontend ahead of grading.
- **Protection against unauthorized resource access:** demonstrated concretely — User A calling any endpoint with User B's `material`/`studySession`/`quiz`/`quizQuestion` ID receives `404 Not Found`, identical to a genuinely nonexistent ID.

No enterprise-grade measures (SSO, audit logging, WAF, encryption-at-rest key management) are in scope for MVP, matching Architecture §14.

---

## 18. Transaction & Consistency

Directly mirrors Database Design §15:

| Operation | Endpoint | Transaction Scope | Reason |
|---|---|---|---|
| Material processing persistence | `POST /api/materials` | `INSERT topics` + `INSERT subtopics` + `INSERT chunks` + `UPDATE materials.status = 'ready'` in one `DB::transaction()` | Prevents a material from ever being "Ready" with incomplete topic/subtopic/chunk data. Failure → full rollback, then a separate single `UPDATE materials.status = 'failed'`. |
| Quiz session creation | `POST /api/study-sessions/{studySession}/quizzes` | `INSERT quizzes` + `INSERT quiz_questions` (all) in one transaction | A quiz is never persisted with only some of its questions. |
| Answer submission + score + Learning State update | `POST /api/quizzes/{quiz}/questions/{quizQuestion}/answer` | `INSERT quiz_answers` + `UPDATE subtopics` (mastery/status) + conditional `UPDATE quizzes` (on final question) in one transaction | Per Architecture §13's guiding principle: an AI evaluation failure must never leave a partially-updated mastery score. If evaluation fails, nothing in this transaction runs at all. |
| Study Session creation | `POST /api/materials/{material}/study-sessions` | `INSERT study_sessions` + `INSERT study_session_topics` (all selected topics) in one transaction | A session is never left with only some of its selected topics attached. |

---

## 19. API → Database Mapping

| Endpoint | Read | Create | Update | Delete | Main Tables |
|---|---|---|---|---|---|
| `POST /api/auth/register` | — | ✓ | — | — | `users`, `personal_access_tokens` |
| `POST /api/auth/login` | ✓ | ✓ | — | — | `users`, `personal_access_tokens` |
| `POST /api/auth/logout` | — | — | — | ✓ | `personal_access_tokens` |
| `GET /api/auth/me` | ✓ | — | — | — | `users` |
| `POST /api/materials` | — | ✓ | ✓ (status) | — | `materials`, `topics`, `subtopics`, `chunks` |
| `GET /api/materials` | ✓ | — | — | — | `materials`, `subtopics` (aggregate) |
| `GET /api/materials/{material}` | ✓ | — | — | — | `materials`, `subtopics` (aggregate) |
| `GET /api/materials/{material}/download` | ✓ | — | — | — | `materials` |
| `GET /api/materials/{material}/topics` | ✓ | — | — | — | `topics`, `subtopics` |
| `POST /api/materials/{material}/study-sessions` | ✓ | ✓ | — | — | `study_sessions`, `study_session_topics` |
| `GET /api/study-sessions/{studySession}` | ✓ | — | — | — | `study_sessions`, `study_session_topics` |
| `PATCH /api/study-sessions/{studySession}/complete` | ✓ | — | ✓ | — | `study_sessions` |
| `POST /api/study-sessions/{studySession}/explanations` | ✓ | — | — | — | `chunks` |
| `POST /api/study-sessions/{studySession}/quizzes` | ✓ | ✓ | — | — | `quizzes`, `quiz_questions`, `chunks` |
| `GET /api/quizzes/{quiz}` | ✓ | — | — | — | `quizzes`, `quiz_questions`, `quiz_answers`, `subtopics` |
| `POST /api/quizzes/{quiz}/questions/{quizQuestion}/answer` | ✓ | ✓ | ✓ | — | `quiz_answers`, `subtopics`, `quizzes` |

---

## 20. API → Frontend Flow

### New Material Flow (Upload)

```
Frontend: user selects PDF in Home hero
  → POST /api/materials (multipart)
  → Laravel stores file, runs full synchronous pipeline
  → Response: material { status: "ready", topics_count, ... }
  → Frontend shows "Material Ready ✓" + [Start Learning]
  → POST /api/materials/{material}/study-sessions { mode: "guided_study_session", topic_ids: [all] }
  → Frontend routes to Studyback Workspace with the new study_session_id
```

### Existing Material Flow

```
Frontend: user opens My Materials
  → GET /api/materials?search=&sort=recent
  → user selects a material card
  → GET /api/materials/{material}            (Material Detail info)
  → GET /api/materials/{material}/topics      (Topics + progress)
  → user taps [Start Study Session] → Study Session Configuration modal (client-side)
  → POST /api/materials/{material}/study-sessions { mode, difficulty, topic_ids }
  → Frontend routes to Studyback Workspace with the new study_session_id
```

### Studyback Workspace — Teach Me

```
Frontend: user selects a subtopic in the sidebar
  → GET /api/materials/{material}/topics   (already loaded / refreshed for sidebar state)
  → POST /api/study-sessions/{studySession}/explanations { subtopic_id, intent: "explain" }
  → Frontend renders the explanation in the conversational interface
  → user taps "Give example" → POST .../explanations { subtopic_id, intent: "example" }
```

### Studyback Workspace — Quiz Me / Guided Study Session (Test → Evaluate)

```
Frontend: user starts a quiz
  → POST /api/study-sessions/{studySession}/quizzes { topic_id, difficulty, question_count }
  → Frontend renders "Question 1 of 5" from the returned questions[]
  → user answers each question:
      POST /api/quizzes/{quiz}/questions/{quizQuestion}/answer { submitted_answer }
      → Frontend shows immediate feedback (is_correct, ai_feedback) and updated subtopic mastery
  → on the final question's response: quiz_status === "completed", quiz_result present
  → Frontend renders "Quiz Complete ✓" with score + topic performance
  → GET /api/quizzes/{quiz}   (optional refresh, e.g. after navigating back to the result)
```

### Studyback Workspace — Review Weak Topics

```
Frontend: sidebar shows a subtopic with status "needs_review" (⚠)
  → user clicks it
  → POST /api/study-sessions/{studySession}/explanations { subtopic_id, intent: "review" }
  → Frontend renders the re-explanation
  → POST /api/study-sessions/{studySession}/quizzes { subtopic_id, question_count: 2 }   (mini re-test)
  → user answers → POST .../questions/{quizQuestion}/answer for each
  → sidebar re-fetches GET /api/materials/{material}/topics to reflect updated mastery/status
```

### End of Session

```
Frontend: user navigates back to My Materials
  → PATCH /api/study-sessions/{studySession}/complete
  → GET /api/materials/{material}   (updated overall_mastery reflected in My Materials / Material Detail)
```

---

## 21. Final API Endpoint Summary

| Method | Endpoint | Auth | Purpose | Module |
|---|---|---|---|---|
| `POST` | `/api/auth/register` | Public | Create account | Auth |
| `POST` | `/api/auth/login` | Public | Log in, issue token | Auth |
| `POST` | `/api/auth/logout` | Authenticated | Revoke current token | Auth |
| `GET` | `/api/auth/me` | Authenticated | Current user | Auth |
| `POST` | `/api/materials` | Authenticated | Upload + process material (sync pipeline) | Materials / Processing |
| `GET` | `/api/materials` | Authenticated | List/search/filter/sort materials | Materials |
| `GET` | `/api/materials/{material}` | Authenticated | Material Detail | Materials |
| `GET` | `/api/materials/{material}/download` | Authenticated | Download original PDF | Materials |
| `GET` | `/api/materials/{material}/topics` | Authenticated | Topic/subtopic tree + mastery/status | Topics / Learning State |
| `POST` | `/api/materials/{material}/study-sessions` | Authenticated | Start a Study Session | Study Session |
| `GET` | `/api/study-sessions/{studySession}` | Authenticated | Retrieve a Study Session | Study Session |
| `PATCH` | `/api/study-sessions/{studySession}/complete` | Authenticated | Complete a Study Session | Study Session |
| `POST` | `/api/study-sessions/{studySession}/explanations` | Authenticated | Teach Me / Review explanation | AI Orchestration |
| `POST` | `/api/study-sessions/{studySession}/quizzes` | Authenticated | Generate a quiz | Quiz |
| `GET` | `/api/quizzes/{quiz}` | Authenticated | Retrieve quiz + questions/result | Quiz |
| `POST` | `/api/quizzes/{quiz}/questions/{quizQuestion}/answer` | Authenticated | Submit + evaluate an answer | Quiz / Learning State |

---

## 22. Implementation Checklist

- [ ] Authentication endpoints (`register`, `login`, `logout`, `me`)
- [ ] Sanctum token authentication wired via `auth:sanctum` middleware on all non-public routes
- [ ] `MaterialPolicy` enforcing ownership on every material and nested-resource route
- [ ] Material endpoints (`POST/GET /materials`, `GET /materials/{material}`, `GET /materials/{material}/download`)
- [ ] Synchronous processing flow embedded in `POST /materials` (extract → clean → chunk → AI topic ID → transaction persist)
- [ ] Topic/Subtopic endpoint (`GET /materials/{material}/topics`) serving both sidebar and Learning State
- [ ] Study Session endpoints (`POST /materials/{material}/study-sessions`, `GET /study-sessions/{studySession}`, `PATCH .../complete`)
- [ ] Quiz endpoints (`POST /study-sessions/{studySession}/quizzes`, `GET /quizzes/{quiz}`, `POST .../questions/{quizQuestion}/answer`)
- [ ] Explanation endpoint (`POST /study-sessions/{studySession}/explanations`) for Teach Me / Review
- [ ] Learning State updates embedded in answer submission (deterministic mastery formula, no separate endpoint)
- [ ] AI operations wired through `ai_service` only, with `Qwen3.6-27B` primary / `gpt-oss-20b` fallback and structured-output validation on all four AI areas (topic extraction, explanation, quiz generation, answer evaluation)
- [ ] Retrieval implemented as an internal filter query only, never exposed as a route
- [ ] Validation (`FormRequest` classes) on every write endpoint per §7–§14
- [ ] Error handling matching the shape and status codes in §16 (including `404`-for-unauthorized)
- [ ] Transactions wrapping the three multi-write operations in §18
- [ ] API → database mapping verified against §19 during implementation review
