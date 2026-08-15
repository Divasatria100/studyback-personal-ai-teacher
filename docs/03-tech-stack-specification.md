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
**Constraint:** MVP hackathon 48 jam, tidak boleh over-engineered
**Base preference:** React (frontend), Laravel (backend), PostgreSQL (database), ai_service + Featherless (AI)

---

## 1. Executive Summary

**Apakah current stack sudah cukup? Ya. Tech stack untuk Studyback MVP telah ditetapkan dan siap menjadi baseline implementation.**

React, Laravel, PostgreSQL, dan Featherless tetap menjadi stack inti. Tidak diperlukan perubahan pada komponen arsitektur utama. Seluruh keputusan teknologi pendukung yang diperlukan untuk MVP juga telah ditetapkan secara eksplisit:

* **Modular monolith** → Laravel digunakan sebagai satu application backend dengan service classes dan folder per modul, tanpa microservice terpisah.
* **AI Orchestrator** → diimplementasikan sebagai `ai_service` **in-process Laravel service** yang thin dan stateless. Service ini menjadi satu-satunya caller ke Featherless API dan tidak memiliki database, API publik, authentication, atau deployment terpisah.
* **AI Model** → `Qwen3.6-27B` ditetapkan sebagai **Primary Model**, sedangkan `gpt-oss-20b` ditetapkan sebagai **Fallback Model** dengan strategi retry-once.
* **RAG / Retrieval** → menggunakan PostgreSQL filter query berbasis `material_id` dan `topic/subtopic_id`. Vector database tidak digunakan pada MVP.
* **Chunking** → menggunakan fixed-length chunking dengan target **~1.000 karakter dan ~200 karakter overlap**. Heading-based atau heading-regex chunking tidak digunakan.
* **PDF Text Extraction** → `spatie/pdf-to-text` dengan Poppler ditetapkan sebagai primary extractor, dengan `smalot/pdfparser` sebagai optional fallback.
* **File Storage** → Laravel Filesystem dengan local disk digunakan untuk menyimpan PDF secara private dan menyediakan authenticated backend-proxied download.
* **Background Processing** → synchronous processing digunakan sebagai baseline MVP. Laravel Queue hanya menjadi opsi cadangan apabila processing terlalu lambat untuk UX.
* **Redis dan Vector Database** → tidak digunakan pada MVP karena tidak terdapat kebutuhan arsitektur yang membenarkan penambahan keduanya.
* **Containerization** → Docker + docker-compose digunakan untuk frontend, backend, dan PostgreSQL. Tidak ada container terpisah untuk `ai_service`.

Dengan keputusan tersebut, tidak ada lagi komponen utama yang berada dalam status *undecided* atau *needs benchmark*. Dokumen ini berfungsi sebagai **final tech stack baseline** sebelum masuk ke tahap Database Design, API Design, AI Architecture, dan UI/UX implementation.

**Kesimpulan satu kalimat:** Studyback MVP memiliki tech stack dan arsitektur pendukung yang sudah ditetapkan secara final, sehingga implementasi dapat dimulai tanpa menambahkan komponen infrastruktur baru di luar keputusan yang tercantum dalam dokumen ini.

---

## 2. Architecture → Tech Stack Mapping

| Architecture Component                                                       | Technology                                                                                      | Status                                           |
| ---------------------------------------------------------------------------- | ----------------------------------------------------------------------------------------------- | ------------------------------------------------ |
| Frontend (SPA)                                                               | React                                                                                           | KEEP                                             |
| API / Application Backend                                                    | Laravel (Controllers + Form Requests)                                                           | KEEP                                             |
| Application Modules (Materials, Topics, Study Session, Quiz, Learning State) | Laravel Service classes / Actions, satu per modul, di dalam satu Laravel app (modular monolith) | KEEP + terapkan sebagai folder per modul         |
| Authentication                                                               | Laravel Sanctum (session/token)                                                                 | ADD (bagian dari Laravel, bukan komponen baru)   |
| AI Orchestrator                                                              | `ai_service` — in-process Laravel service, thin dan stateless                                   | KEEP + tetapkan sebagai internal Laravel service |
| Retrieval / RAG                                                              | PostgreSQL query filter (material_id + topic_id)                                                | KEEP                                             |
| LLM Interface                                                                | HTTP client dari `ai_service` ke Featherless API (OpenAI-compatible)                            | KEEP                                             |
| Database                                                                     | PostgreSQL                                                                                      | KEEP                                             |
| File Storage                                                                 | Laravel Filesystem (local disk driver)                                                          | ADD (konfigurasi, bukan tool baru)               |
| Material Processing Pipeline                                                 | Laravel job/controller action (sync) + library PDF extraction                                   | ADD library                                      |
| Background Processing                                                        | Tidak ada (synchronous), Laravel Queue `sync` driver jika diperlukan                            | NOT REQUIRED (Redis), OPTIONAL (Queue)           |
| Containerization                                                             | Docker (docker-compose: frontend, app, db)                                                      | KEEP                                             |

**Catatan tentang posisi `ai_service`:** `ai_service` merupakan **in-process service di dalam aplikasi Laravel**, bukan Python/Node service, microservice, atau container terpisah. `ai_service` tidak memiliki API publik, authentication terpisah, database, atau deployment independen.

`ai_service` berfungsi sebagai thin, stateless abstraction layer yang bertanggung jawab untuk membangun prompt, memilih model, memanggil Featherless API, menangani retry/fallback, dan memvalidasi structured output. Laravel tetap menjadi satu-satunya pemilik business state dan database state. `ai_service` tidak pernah menulis langsung ke database.

Komunikasi AI menggunakan HTTP hanya pada boundary eksternal:

**Laravel `ai_service` → Featherless API**

Tidak ada HTTP communication antara Laravel dengan `ai_service` karena `ai_service` berjalan di dalam proses aplikasi Laravel yang sama.

Keputusan ini menetapkan arsitektur **Modular Monolith** untuk MVP dan menolak model hybrid atau standalone AI service untuk saat ini.


---

# 3. Recommended Tech Stack

| Area                             | Technology                                           | Purpose                                                                                                         | Status       |
| -------------------------------- | ---------------------------------------------------- | --------------------------------------------------------------------------------------------------------------- | ------------ |
| Frontend                         | React                                                | SPA untuk Home / My Materials / Studyback Workspace                                                             | FINAL        |
| Backend/API                      | Laravel                                              | Modular monolith, seluruh business logic & state mutation                                                       | FINAL        |
| Database                         | PostgreSQL                                           | Materials, topics, subtopics, chunks, sessions, quizzes, learning state                                         | FINAL        |
| Auth                             | Laravel Sanctum                                      | Session/token authentication dan user ownership scoping                                                         | FINAL        |
| AI Integration                   | `ai_service` (Laravel in-process service)            | Thin wrapper untuk prompt construction, Featherless API calls, retry handling, dan structured output validation | FINAL        |
| AI Provider                      | Featherless API                                      | AI inference untuk topic extraction, quiz generation, Teach Me, dan answer evaluation                           | FINAL        |
| Primary AI Model                 | `Qwen3.6-27B`                                        | Model utama untuk seluruh use case AI pada MVP                                                                  | FINAL        |
| Fallback AI Model                | `gpt-oss-20b`                                        | Retry target jika primary model gagal atau timeout                                                             | FINAL        |
| PDF Text Extraction              | `spatie/pdf-to-text` (wrap `pdftotext` dari Poppler) | Extraction PDF → teks mentah                                                                                    | FINAL        |
| PDF Extraction Fallback          | `smalot/pdfparser`                                   | Fallback jika binary Poppler tidak tersedia di environment deploy                                               | OPTIONAL     |
| File Storage                     | Laravel Filesystem, driver `local`                   | Menyimpan PDF asli untuk Download Material                                                                      | FINAL        |
| RAG / Retrieval                  | PostgreSQL `WHERE`-filter query                      | Filter chunk berdasarkan `material_id` + `topic/subtopic_id`                                                    | FINAL        |
| Chunking                         | PHP native fixed-length chunking                     | Membagi teks secara deterministik sebelum topic identification                                                  | FINAL        |
| Chunk Size                       | ~1,000 characters                                    | Target ukuran setiap chunk                                                                                      | FINAL        |
| Chunk Overlap                    | ~200 characters                                      | Mempertahankan konteks antar chunk                                                                              | FINAL        |
| Background Processing            | Synchronous (inline)                                 | Processing dilakukan dalam request lifecycle untuk MVP                                                          | FINAL        |
| Background Processing (opsional) | Laravel Queue, driver `sync` atau `database`         | Hanya digunakan jika processing upload terlalu lambat untuk UX                                                  | OPTIONAL     |
| Redis                            | —                                                    | Tidak ada use case yang membutuhkan Redis pada MVP                                                              | NOT REQUIRED |
| Vector Database                  | —                                                    | Retrieval dibatasi pada single-material/topic; PostgreSQL filtering cukup                                       | NOT REQUIRED |
| Containerization                 | Docker + docker-compose                              | Container untuk Laravel, React, dan PostgreSQL                                                                  | FINAL        |
| API Communication                | REST (JSON)                                          | Frontend ↔ Laravel                                                                                              | FINAL        |
| External AI Communication        | HTTPS REST API                                       | Laravel `ai_service` ↔ Featherless API                                                                          | FINAL        |

### AI Model Configuration

Studyback menggunakan satu primary model dan satu fallback model yang ditetapkan secara fixed untuk seluruh AI use case pada MVP.

**Primary Model — `Qwen3.6-27B`**

Digunakan sebagai model utama untuk seluruh AI use case:

* Topic extraction
* Quiz generation
* Teach Me
* Answer evaluation

Model ini digunakan sebagai primary model karena menjadi pilihan utama inference pada MVP. Context window 32K dianggap mencukupi karena retrieval di-scope secara ketat pada satu topic/subtopic dan hanya chunk yang relevan yang diberikan kepada model.

**Fallback Model — `gpt-oss-20b`**

Digunakan sebagai retry target apabila primary model mengalami failure atau timeout.

Model ini memiliki context window 128K sehingga memberikan ruang yang lebih besar untuk retrieved chunks, conversation history, dan structured-output instructions. Ukuran 20B juga ditujukan untuk menjaga latency tetap wajar untuk interaksi conversational pada Studyback Workspace.

Fallback berasal dari keluarga model yang berbeda dari primary sehingga memberikan jalur alternatif apabila terjadi masalah availability atau failure yang spesifik pada keluarga model primary.

Fallback dijalankan melalui strategi **retry-once** yang ditetapkan pada Architecture Section 13.

### AI Service Architecture

`ai_service` merupakan **in-process Laravel service**, bukan container atau backend service terpisah.

Dengan demikian:

* `ai_service` berjalan di dalam aplikasi Laravel.
* Tidak terdapat komunikasi REST antara Laravel dan `ai_service`.
* `ai_service` menjadi abstraction layer antara business logic Laravel dan Featherless API.
* `ai_service` menangani prompt construction, model selection, API request, retry/fallback, dan structured-output validation.
* Featherless API merupakan external AI provider yang digunakan oleh aplikasi.
* Tidak diperlukan container khusus untuk `ai_service`.

Arsitektur komunikasi AI:

**React → Laravel API → `ai_service` → Featherless API**

Jika primary model gagal atau timeout:

**`ai_service` → retry-once → `gpt-oss-20b`**

## 4. PDF Processing

Pipeline yang dibutuhkan:

```
PDF → Text Extraction → Cleaning → Chunking → Topic/Subtopic Identification → Storage
```

Text Extraction — spatie/pdf-to-text (PRIMARY)

Wrapper Composer package di sekitar pdftotext (bagian dari Poppler-utils), dipanggil sebagai binary.
Dipilih sebagai primary extractor karena pdftotext umumnya memberikan hasil extraction yang baik untuk PDF berbasis teks, termasuk banyak dokumen dengan layout yang relatif kompleks seperti slide dan lecture notes.
Karena Docker sudah menjadi bagian dari stack, penambahan poppler-utils ke Dockerfile tetap sederhana dan tidak menambah infrastruktur besar untuk MVP.

Text Extraction — smalot/pdfparser (FALLBACK/OPTIONAL)

Pure-PHP, tidak membutuhkan binary eksternal.
Berguna sebagai fallback apabila terjadi kendala pada instalasi atau eksekusi Poppler, atau jika deployment target tidak mengizinkan binary eksternal.
Kualitas extraction dapat lebih terbatas pada PDF dengan layout kompleks, tetapi cukup sebagai fallback untuk MVP.

Cleaning

Deterministic, menggunakan PHP native: menghapus whitespace berlebih, menormalisasi line break, dan jika memungkinkan membuang header/footer yang berulang atau pola nomor halaman.
Tidak membutuhkan library tambahan.

Chunking

Deterministic dan menggunakan fixed-length chunking sebagai strategi utama untuk MVP.
Teks dipotong menjadi chunk dengan ukuran sekitar 1.000 karakter dan menggunakan overlap sekitar 200 karakter antar-chunk.
Pendekatan ini tidak bergantung pada struktur heading atau format PDF, sehingga lebih robust terhadap variasi dokumen kuliah seperti slide, lecture notes, dan PDF dengan struktur heading yang tidak konsisten.
Implementasi menggunakan PHP native dan tidak membutuhkan library tambahan.

Topic/Subtopic Identification

Ini merupakan tahap AI utama dalam pipeline.
Proses dilakukan melalui ai_service → Featherless.
Hasil berupa structured JSON divalidasi oleh Laravel sebelum disimpan untuk memastikan format dan data yang diterima sesuai dengan kebutuhan sistem.

Storage

Hasil akhir berupa topics, subtopics, dan chunks yang terkait dengan material serta topic/subtopic disimpan di PostgreSQL.
Tidak membutuhkan library atau database tambahan untuk MVP.

Tidak direkomendasikan: OCR library (Tesseract)

OCR berada di luar scope MVP karena spesifikasi Studyback mengasumsikan PDF berbasis teks, bukan PDF hasil scan gambar.

---

## 5. File Storage

**Rekomendasi: Local Storage, via Laravel Filesystem `local`.**

Alasan:

* Architecture Section 15 secara eksplisit menaruh scaling file storage sebagai "future evolution", bukan kebutuhan MVP.
* Deployment target adalah "single deployable unit" (Section 17) — satu instance backend, sehingga local persistent storage cukup untuk kebutuhan MVP.
* Laravel Filesystem API sudah abstract (`Storage::disk('local')`), sehingga jika suatu saat dibutuhkan pindah ke S3-compatible object storage, perubahan dapat dilakukan melalui konfigurasi disk tanpa mengubah arsitektur aplikasi secara signifikan.
* File PDF original disimpan di `storage/app/private`, di luar `public/`, sehingga tidak dapat diakses langsung melalui URL publik.
* **Download Material merupakan bagian dari Material Detail dan tetap diimplementasikan sebagai MVP feature.** File diberikan melalui backend-proxied download setelah sistem melakukan autentikasi dan memverifikasi bahwa material dimiliki oleh user yang sedang login.
* Laravel Filesystem menyediakan `Storage::download()` sehingga implementasi authenticated file download tetap sederhana dan tidak membutuhkan sistem file-serving tambahan.

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

**Object/Cloud Storage: NOT REQUIRED untuk MVP.** Pertimbangkan hanya jika:

* Platform deploy pilihan menggunakan ephemeral filesystem sehingga file dapat hilang ketika container atau instance direstart.
* Sistem nantinya berkembang menjadi multi-instance deployment yang membutuhkan shared object storage.

Jika deployment menggunakan VPS/Docker dengan persistent volume, local storage tetap sesuai untuk MVP. Jika platform deployment menggunakan filesystem ephemeral, object storage seperti S3-compatible storage atau Cloudflare R2 dapat digunakan sebagai penyesuaian deployment tanpa mengubah alur aplikasi utama.

**Security Requirement:**

* PDF original tidak disimpan di `public/`.
* URL file asli tidak diekspos langsung ke client.
* Download harus melalui authenticated backend route.
* Backend harus memverifikasi ownership material sebelum mengirimkan file.
* Nama file yang ditampilkan saat download dapat menggunakan `original_name`, sementara `file_path` menggunakan nama file internal yang tidak mudah ditebak.


## 6. RAG / Retrieval

* Validasi: Metadata-based retrieval menggunakan PostgreSQL SUDAH CUKUP untuk MVP. Tidak menggunakan vector database.

* Studyback menggunakan retrieval berbasis metadata/filtering untuk mengambil context yang relevan dari material yang sedang dipelajari. Pendekatan ini dipilih karena sesuai dengan scope produk dan menjaga implementasi tetap sederhana selama hackathon 48 jam.

Alasan keputusan:

- Architecture Blueprint Section 8 & 17 memilih filter-based retrieval dan menempatkan vector database sebagai future evolution, bukan kebutuhan MVP.
- Product Spec Section 9.2 menggunakan context boundary sederhana: Material → Chunking → Retrieval → Relevant Context → AI Response, tanpa kebutuhan semantic search.
- Scope produk adalah single-material, topic-scoped interaction, sehingga user pada suatu sesi belajar hanya berinteraksi dengan material dan topic/subtopic tertentu.
- Retrieval yang dibutuhkan pada dasarnya adalah mengambil chunk berdasarkan metadata, misalnya: "ambil semua chunk dari material X yang terkait dengan topic/subtopic Y".
- PostgreSQL dapat menangani kebutuhan tersebut menggunakan query filtering biasa sehingga tidak membutuhkan embedding atau similarity search.
- Menambahkan vector database seperti pgvector atau Pinecone akan menambah kompleksitas berupa embedding generation, vector storage, similarity tuning, dan retrieval pipeline tanpa manfaat yang signifikan untuk MVP.

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
AI Service → Featherless
     ↓
AI Response

Implementasi teknis (konsep, bukan schema):

Chunks memiliki relasi terhadap material, topic, dan jika diperlukan subtopic.
PostgreSQL menggunakan index pada kolom filtering yang relevan untuk menjaga query tetap cepat.
Retrieval dilakukan menggunakan filtering berdasarkan material dan topic/subtopic.
Tidak membutuhkan vector embedding, vector database, atau ekstensi PostgreSQL tambahan untuk MVP.

---

## 7. AI Model Selection (Featherless) & Structured Output Flow

### 7.1 Rekomendasi

**PRIMARY MODEL: `Qwen3.6-27B`**
- Keluarga model berbeda dari primary (mengurangi risiko jika Featherless mengalami masalah ketersediaan spesifik pada satu keluarga model), context 32K tetap cukup karena retrieval sudah di-scope ketat ke satu topic/subtopic (chunk yang relevan biasanya kecil).
- Digunakan sebagai retry target ketika primary model gagal/timeout (sesuai strategi retry-once di Architecture Section 13), bukan sebagai model utama.

**FALLBACK MODEL: `gpt-oss-20b`**
- Context window 128K memberi ruang aman untuk retrieved chunks + histori percakapan + instruksi structured-output tanpa harus memotong konteks secara agresif — penting karena semua empat use case (topic extraction, quiz generation, Teach Me, answer evaluation) berbagi model yang sama di MVP ini.
- Ukuran 20B menjaga latency tetap wajar untuk interaksi conversational real-time di Studyback Workspace.
- Mendukung tool-use/structured output, yang dibutuhkan untuk tiga dari empat use case AI (topic extraction, quiz generation, answer evaluation).

### 7.2 Structured Output Flow

```
LLM (Featherless)
  ↓ raw output
Structured JSON (schema: topics[], quiz_questions[], evaluation{verdict, feedback, subtopic})
  ↓ divalidasi
ai_service (parse + validate JSON shape, retry-once jika invalid)
  ↓ hasil bersih (data, bukan opini tentang state)
Laravel (Application Modules: Processing, Quiz, Learning State)
  ↓ menerapkan aturan deterministic
Application Logic (persist topics, simpan quiz, hitung skor, update mastery/status)
```

Sesuai Principle 8–10 Architecture Blueprint: **AI tidak pernah langsung menulis ke database atau menentukan Learning State.** `ai_service` hanya mengembalikan data terstruktur (mis. "jawaban ini benar", "ini masuk Subtopic X"); Laravel-lah yang menghitung skor, menentukan status (Needs Review/In Progress/Mastered) menggunakan formula deterministic tetap (<60% / 60–79% / ≥80%), dan mempersist hasilnya.

---

## 8. Background Processing

**Evaluasi: Synchronous processing SUDAH CUKUP. Laravel Queue OPTIONAL, Redis NOT REQUIRED.**

*   Architecture Blueprint Section 15 & 17 secara eksplisit menetapkan "Synchronous processing (acceptable for hackathon file sizes)" sebagai keputusan MVP, dengan background worker/queue didaftarkan sebagai _future evolution_, bukan kebutuhan sekarang.
    
*   Ukuran PDF materi kuliah (beberapa puluh halaman) dan pipeline yang didominasi operasi deterministic (extraction, cleaning, chunking) + satu panggilan AI (topic/subtopic identification) realistis selesai dalam hitungan detik hingga puluhan detik — cukup ditangani inline dalam satu request Laravel dengan **loading state yang jelas di frontend** ("Uploading Material... → Extracting Content... → Understanding Material... → Identifying Topics... → Preparing Study Material...").
    
*   Loading state wajib digunakan agar proses synchronous tidak terlihat seperti halaman freeze atau error ketika extraction dan AI processing membutuhkan waktu lebih lama selama demo.
    
*   **Laravel Queue (OPTIONAL, bukan wajib):** jika saat testing ternyata upload+processing terasa terlalu lama untuk UX, queue dapat dipertimbangkan sebagai optimasi lanjutan. Untuk MVP, queue tidak menjadi bagian dari baseline implementation.
    
*   **Redis: NOT REQUIRED.** Tidak ada use case caching, rate-limiting kompleks, atau queue-throughput tinggi yang membenarkan penambahan Redis di 48 jam ini. Menambahkannya hanya menambah satu container Docker lagi tanpa manfaat terukur untuk MVP.

---

## 9. Project Structure

```t
studyback/
├── frontend/                 # React SPA (Home, My Materials, Workspace)
├── backend/                  # Laravel — modular monolith
│   ├── app/
│   │   ├── Modules/          # satu folder per modul arsitektur
│   │   │   ├── Materials/
│   │   │   ├── Processing/
│   │   │   ├── Topics/
│   │   │   ├── StudySession/
│   │   │   ├── Quiz/
│   │   │   └── LearningState/
│   │   └── Services/
│   │       └── AiOrchestrator.php   # in-process ai_service; satu-satunya caller Featherless
│   └── ... (struktur Laravel standar)
└── docs/                     # dokumen ini + Product Spec + System Architecture
```

`ai_service` tidak memiliki folder terpisah di root project. `ai_service` diimplementasikan sebagai **in-process Laravel service** melalui `AiOrchestrator.php` di dalam `backend/app/Services/`.

`AiOrchestrator.php` merupakan thin, stateless service yang bertanggung jawab untuk:

* membangun prompt;
* memilih primary/fallback AI model;
* memanggil Featherless API;
* menangani retry/fallback; dan
* memvalidasi structured output.

Folder tambahan di root hanya `docs/`. Tidak ada folder `ai_service/`, `workers/`, `queue/`, atau `services/` terpisah karena MVP tidak menggunakan background worker atau microservice terpisah.

Modularitas diwujudkan melalui struktur folder di dalam `backend/`, bukan sebagai deployable unit terpisah. Struktur ini konsisten dengan keputusan **Modular Monolith** pada Architecture Section 2.


---

## 10. Additional Technologies to Learn

### MUST LEARN
- **`spatie/pdf-to-text` + Poppler-utils** — cara install di Dockerfile, cara handle jika binary gagal/PDF corrupt (untuk failure handling Section 13: "PDF extraction fails").
- **Laravel Filesystem API** (`Storage::disk()`) — khususnya cara serve file lewat route terautentikasi (bukan file public), untuk memenuhi Security Section 14.
- **Featherless API (OpenAI-compatible endpoint)** — format request/response, cara memaksa/mendorong structured JSON output (system prompt + schema instruction), rate limit per plan.
- **Laravel Sanctum** — jika belum pernah pakai, ini auth paling ringan untuk SPA + API token, sesuai kebutuhan Section 14.

### SHOULD LEARN
- **Laravel Queue dengan driver `database`** — berguna sebagai jaring pengaman jika processing time ternyata jadi masalah UX saat demo, tanpa perlu belajar Redis.
- **Validasi JSON Schema di PHP** (mis. `justinrainbow/json-schema` atau validasi manual array shape) — untuk memvalidasi structured output dari LLM sebelum dipersist, sesuai failure handling Section 13.
- **PostgreSQL full-text search dasar** (`tsvector`) — opsional untuk mempercepat/mempermudah query filter-based retrieval jika volume chunk per material besar; bukan pengganti vector DB, hanya optimisasi index.

### NOT NEEDED
- **Vector database (pgvector, Pinecone, Weaviate, dll.)** — di luar scope, sudah divalidasi di Section 6.
- **Redis** — sudah divalidasi di Section 8.
- **Message broker (RabbitMQ, Kafka, SQS)** — tidak relevan untuk single-instance modular monolith 48 jam.
- **Object storage SDK (AWS S3, GCS)** — hanya diperlukan jika keputusan hosting berubah (lihat Section 5); jangan dipelajari lebih dulu sebelum keputusan itu dibuat.
- **OCR (Tesseract, dsb.)** — di luar scope MVP (PDF berbasis teks, bukan scan).
- **Fine-tuning / training model sendiri** — eksplisit di-cut di Product Spec Section 12 ("Custom/fine-tuned AI model").

---

## 11. Final Tech Stack

| Layer                 | Technology                                                                                     |
| --------------------- | ---------------------------------------------------------------------------------------------- |
| Frontend              | React (SPA)                                                                                    |
| Backend/API           | Laravel (modular monolith, module-per-folder)                                                  |
| Auth                  | Laravel Sanctum                                                                                |
| Database              | PostgreSQL                                                                                     |
| File Storage          | Laravel Filesystem — local disk, backend-proxied download                                      |
| PDF Extraction        | `spatie/pdf-to-text` (Poppler), fallback `smalot/pdfparser`                                    |
| Chunking              | PHP native, deterministic fixed-length (~1,000 characters + ~200 characters overlap)           |
| RAG/Retrieval         | PostgreSQL filter query (material_id + topic_id)                                               |
| AI Integration Layer  | `ai_service` — thin, stateless, in-process Laravel service dan satu-satunya caller Featherless |
| AI Provider           | Featherless API                                                                                |
| Primary AI Model      | `Qwen3.6-27B` — 32K context                                                                    |
| Fallback AI Model     | `gpt-oss-20b` — 128K context, tool-use/structured output                                       |
| Background Processing | Synchronous (inline); Laravel Queue `database` driver sebagai opsi cadangan                    |
| Redis                 | Tidak digunakan                                                                                |
| Vector Database       | Tidak digunakan                                                                                |
| Containerization      | Docker + docker-compose (frontend, backend, db)                                                |
| API Communication     | REST/JSON                                                                                      |
| AI Communication      | HTTPS REST/JSON (`ai_service` → Featherless API)                                               |

### Chunking Strategy

Chunking menggunakan **fixed-length chunking** dengan target:

* Chunk length: **~1,000 characters**
* Chunk overlap: **~200 characters**

Heading-based atau heading-regex chunking **tidak digunakan**.

Chunking bersifat deterministic dan dilakukan sebelum topic identification.

### AI Model Strategy

Studyback menggunakan konfigurasi model yang fixed:

* **Primary:** `Qwen3.6-27B`
* **Fallback:** `gpt-oss-20b`

`Qwen3.6-27B` digunakan sebagai model utama untuk seluruh AI use case pada MVP. `gpt-oss-20b` digunakan sebagai fallback ketika primary model mengalami failure atau timeout.

Fallback mengikuti strategi **retry-once** yang didefinisikan pada Architecture Section 13.

### `ai_service` Architecture

`ai_service` merupakan **in-process Laravel service**, bukan service atau container terpisah.

`ai_service` bertanggung jawab untuk:

* membangun prompt;
* memilih primary/fallback model;
* memanggil Featherless API;
* menangani retry/fallback;
* memvalidasi structured output; dan
* menyediakan abstraction layer antara business logic Laravel dan AI provider.

Tidak terdapat komunikasi REST antara Laravel dengan `ai_service` karena keduanya berada dalam proses aplikasi Laravel yang sama.

Arsitektur AI:

**React → Laravel API → `ai_service` → Featherless API**

Jika primary gagal atau timeout:

**`ai_service` → retry-once → `gpt-oss-20b`**


Stack ini siap menjadi dasar untuk tahap berikutnya:

```
Tech Stack (dokumen ini)
  ↓
Database Design       — schema untuk materials, topics, subtopics, chunks, sessions, quizzes, learning_state
  ↓
API Design            — route/contract per modul (Materials, Processing, StudySession, Quiz, LearningState)
  ↓
AI Architecture        — prompt template per capability (explain, quiz, evaluate, extract), JSON schema per capability
  ↓
UI/UX Design           — Home, My Materials, Material Detail, Study Session Config (modal), Studyback Workspace
```
