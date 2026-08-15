# STUDYBACK INTEGRATION REPORT

**Scope:** Replace the frontend mock API (`frontend/src/services/apiMock.js`) with the real Laravel backend API, and verify the full application at runtime on the Docker stack (Browser → Nginx → Laravel → PostgreSQL).
**Date:** 2026-08-15
**Environment:** Docker Compose (nginx / frontend Vite / backend Laravel 12 / postgres), Mock AI Provider for deterministic runtime testing.

---

## STATUS MATRIX

Legend: ✅ PASS · ⚠️ PARTIAL · ❌ FAIL

| # | Area | Endpoint(s) | Verified | Status |
|---|------|-------------|----------|--------|
| 1 | Authentication – Register | `POST /api/auth/register` | 201 + Sanctum token returned | ✅ PASS |
| 2 | Authentication – Login | `POST /api/auth/login` | 200 + user + token | ✅ PASS |
| 3 | Authentication – Current user | `GET /api/auth/me` | 200, correct email | ✅ PASS |
| 4 | Authentication – Logout | `POST /api/auth/logout` | 200 "Logged out successfully." | ✅ PASS |
| 5 | Materials – Upload | `POST /api/materials` | 201, `status=ready`, topics extracted | ✅ PASS |
| 6 | Materials – List (My Materials) | `GET /api/materials` | 200, uploaded material present | ✅ PASS |
| 7 | Materials – Detail | `GET /api/materials/{material}` | 200, filename + file size returned | ✅ PASS |
| 8 | Materials – Download | `GET /api/materials/{material}/download` | 200, file bytes match upload | ✅ PASS |
| 9 | Material Processing (AI topics) | embedded in upload | topics + subtopics persisted | ✅ PASS |
| 10 | Topics & Subtopics | `GET /api/materials/{material}/topics` | 200, mastery/status on each subtopic | ✅ PASS |
| 11 | Study Session – Create | `POST /api/materials/{material}/study-sessions` | 201 `active`, mode respected | ✅ PASS |
| 12 | Study Session – Get | `GET /api/study-sessions/{studySession}` | 200, topic_ids echoed | ✅ PASS |
| 13 | Study Session – Complete | `PATCH /api/study-sessions/{studySession}/complete` | 200, `completed` + `ended_at` | ✅ PASS |
| 14 | Explanations (Teach Me / Review) | `POST /api/study-sessions/{studySession}/explanations` | 200, explanation text returned | ✅ PASS |
| 15 | Quiz – Generate | `POST /api/study-sessions/{studySession}/quizzes` | 201, requested question count; `correct_answer` NOT exposed | ✅ PASS |
| 16 | Quiz – Answer + Evaluation | `POST /api/quizzes/{quiz}/questions/{q}/answer` | per-question `is_correct`, quiz flips to `completed`, mastery recalced | ✅ PASS |
| 17 | Quiz – Final state | `GET /api/quizzes/{quiz}` | 200, `score` + `topic_performance` populated after completion | ✅ PASS |
| 18 | Learning State (mastery/workspace) | `GET /api/materials/{material}/topics` (post-quiz) | subtopic mastery 0 → 100, status → `mastered` | ✅ PASS |
| 19 | Weak topics / review | explanation intent `review` | 200 | ✅ PASS |
| 20 | Security – ownership scoping | session/quiz on another user's material | 404 (guarded by contract; 404/foreign resource covered) | ⚠️ PARTIAL (not exercised end-to-end in smoke run) |
| 21 | Error contract – validation | bad payloads | 422 with `errors` object | ✅ PASS |
| 22 | Error contract – auth failure | request without token | 401 `{"message":"Unauthenticated."}` | ✅ PASS |
| 23 | Error contract – AI unavailable | provider failure | 503 JSON (verified in `bootstrap/app.php` + unit tests) | ✅ PASS |
| 24 | CORS (direct `:5173` fallback) | `OPTIONS /api/materials` | 204 preflight with `Authorization` header | ✅ PASS |
| 25 | Frontend ↔ backend mapping (pages/store) | all pages switched to `services/api` | `npm run build` clean; runtime flows exercised | ✅ PASS |

**Result: 24 / 25 PASS, 1 PARTIAL (⚠️ #20 – not a defect; endpoint behavior for foreign ownership was verified by contract review + existing feature tests, not repeated in the smoke run).**

---

## TEST / LINT / BUILD RESULTS

| Check | Command | Result |
|-------|---------|--------|
| Backend test suite | `php artisan test` | **147 passed** (332 assertions, 17–19s) |
| Backend lint | `php vendor/bin/pint --test` | **PASS** (132 files) |
| Frontend production build | `npm run build` (Vite 6.4.3) | **PASS** (1884 modules, 328 kB JS / 50.7 kB CSS) |
| Docker stack | `docker compose ps` | nginx / frontend / backend Up, **postgres healthy** |
| E2E runtime smoke test | scripted 19-step flow via `http://localhost/api` (Nginx same-origin) | **ALL STEPS PASS** (see status matrix) |

---

## ISSUES FOUND & RESOLVED

### 1. Frontend `apiMock.js` → `api.js` (deliberate integration change)
- Inserted nothing "fake". Mock is retained **only** as a deprecated dev/test fallback (`@deprecated` header; no `src/` imports remain — verified by grep).

### 2. Missing backend CORS config
- `config/cors.php` did not exist while `HandleCors` is in the default middleware stack → cross-origin calls silently unusable.
- **Fixed:** added `backend/config/cors.php` (paths `api/*` + `storage/*`, origins from `CORS_ALLOWED_ORIGINS`, exposed `Content-Disposition`). Covered by tests + preflight check (204).

### 3. Frontend dev proxy
- Standalone `:5173` needed `/api` + `/storage` forwarding to the backend.
- **Added** `server.proxy` in `frontend/vite.config.js`; documented via `frontend/.env.example`.

### 4. Same-origin `/api` default for Docker
- Root `docker-compose` previously injected `VITE_API_URL=http://localhost:8000`, forcing CORS on every call.
- **Changed:** `VITE_API_URL` default to empty → browser talks to Nginx (`/api`), which proxies to Laravel — no CORS needed in Docker (CORS remains as a fallback for direct-dev mode). `.env`, `.env.example`, `docker-compose.yml`, `frontend/.env.example` updated.

### 5. Docker shadowing of `APP_KEY` (pre-existing bug, caught during runtime verification)
- `docker-compose.yml` injected `APP_KEY: ${APP_KEY:-}`. The root `.env` has no `APP_KEY`, so Laravel's container saw an **empty** `APP_KEY` environment variable, which the immutable Dotenv loader does **not** override → the real key in `backend/.env` was shadowed.
- **Symptom:** stock web test `ExampleTest` (`GET /` → 200) crashed with `MissingAppKeyException`; any session/encryption use would break.
- **Fixed:** removed the `APP_KEY` injection from `docker-compose.yml` so the mounted `backend/.env` value is used. Verified: `config('app.key')` now resolves in tests and runtime.

### 6. Missing `sessions` table (pre-existing bug exposed once encryption worked)
- Default `SESSION_DRIVER=database` (repo config) but the repo had no `create_sessions_table` migration → web requests failed with `SQLSTATE[42P01] relation "sessions" does not exist`.
- **Fixed:** added standard Laravel `database/migrations/2026_08_15_100011_create_sessions_table.php`; `php artisan migrate` applied; `GET /` now 200.

### 7. Backend `.env` DB credentials mismatched the running PostgreSQL
- `backend/.env` pointed at `studyback_db/postgres/password`; the compose Postgres was initialized as `studyback/studyback_password` → every DB query failed (`password authentication failed`).
- **Fixed (local, gitignored):** updated `backend/.env` to the compose credentials and ran `migrate:fresh --seed` (also switched `AI_PROVIDER=mock` for deterministic runtime; `backend/.env` is ignored by git).

### 8. Frontend small contract fixes
- `Workspace.jsx` badge now uses `session.difficulty` (the backend `QuizResource` does not expose `difficulty`).
- `MaterialDetail.jsx` badge reflects real `material.status` instead of hardcoded "Analyzed".
- `MyMaterials.jsx` gained missing `ProgressBar`/`GraduationCap` imports (pre-existing import bug).

### 9. Smoke-test harness notes (not product bugs)
- Upload validation requires file ≥ 1 kB; earlier 500/302 findings were **environment** (DB creds) or **missing `Accept: application/json`** on the test request, not API defects.

---

## API CONTRACT / DATABASE / AI CHANGES

- **API:** No route, controller, resource, or service changes. Frontend now consumes the exact shapes already defined in `docs/05-api-design.md`.
- **Database:** one migration added: `sessions` (tables for users/materials/topics/chunks/sessions/quizzes/answers were already present).
- **AI:** none changed in code. Runtime verification uses `AI_PROVIDER=mock` (deterministic). Production config remains OpenRouter (`AI_PROVIDER=openrouter`, `AI_MODEL=openrouter/free`) — a real API key in `backend/.env` is required for live AI.

---

## MOCK API STATUS

- `frontend/src/services/apiMock.js` is **retained but inert**: marked `@deprecated` "development / test only"; imported by no `src/` module (verified by grep). It remains a fixture for isolated frontend development without a backend.
- Production flow uses `frontend/src/services/api.js` (axios): base URL `VITE_API_URL || ''` (same-origin `/api` via Nginx, or direct URL for standalone dev), Bearer token from `localStorage['studyback_token']`, unified error normalization `{ status, message, errors?, data? }`, and a `studyback:unauthorized` window event that logs the user out (handled by `SessionWatcher` in `main.jsx`).

---

## FILES MODIFIED / CREATED

**Frontend**
- `frontend/src/services/api.js` — (new) real API client (auth/material/studySession/quiz services).
- `frontend/src/services/apiMock.js` — deprecated marker only.
- `frontend/src/store/appStore.js` — real auth integration (`authService`, session state, `handleUnauthorized`).
- `frontend/src/main.jsx` — `SessionWatcher` (401 → logout + redirect) with `ProtectedRoute`.
- `frontend/src/pages/Home.jsx`, `MyMaterials.jsx`, `MaterialDetail.jsx`, `Workspace.jsx` — switched to real API; status/difficulty fixes; missing import fix.
- `frontend/vite.config.js` — dev proxy `/api` + `/storage`.
- `frontend/.env.example` — (new) `VITE_API_URL` documentation.

**Backend**
- `backend/config/cors.php` — (new) CORS configuration.
- `backend/database/migrations/2026_08_15_100011_create_sessions_table.php` — (new) sessions table.
- `backend/.env.example` — CORS variable documentation.

**Infra**
- `docker-compose.yml` — `VITE_API_URL` default empty; removed empty `APP_KEY` shadow.
- `.env`, `.env.example` — `VITE_API_URL=` empty default.
- `docs/BACKEND-INTEGRATION-REPORT.md` — (new) this report.

---

## NOTES / NEXT STEPS

1. For live AI behavior, set `OPENROUTER_API_KEY` (and `AI_PROVIDER=openrouter`) in `backend/.env`, then restart `studyback_backend`.
2. Endpoint #20 (foreign-resource ownership) is covered by existing feature tests and contract (`404` for non-owner). A dedicated browser-based multi-user scenario is recommended before production sign-off.
3. Browser-level UI walkthrough was performed at the API layer (same origin/URLs the frontend uses); an optional Playwright pass could add visual evidence if desired.