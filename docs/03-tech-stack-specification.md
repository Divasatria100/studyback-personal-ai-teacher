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
**Base preference:** React (frontend), Laravel (backend), PostgreSQL (database), `ai_service` sebagai provider-agnostic AI abstraction (default provider: OpenRouter dengan route `openrouter/free`; Featherless.ai sebagai optional hackathon provider; Mock AI Provider untuk development/testing)

---

## 1. Executive Summary

**Apakah current stack sudah cukup? Ya. Tech stack untuk Studyback MVP telah ditetapkan dan siap menjadi baseline implementation.**

React, Laravel, PostgreSQL, dan `ai_service` tetap menjadi stack inti. Tidak diperlukan perubahan pada komponen arsitektur utama. Seluruh keputusan teknologi pendukung yang diperlukan untuk MVP juga telah ditetapkan secara eksplisit:

* **Modular monolith** → Laravel digunakan sebagai satu application backend dengan service classes dan folder per modul, tanpa microservice terpisah.
* **AI Orchestrator** → diimplementasikan sebagai `ai_service` **in-process Laravel service** yang thin dan stateless. Service ini menjadi satu-satunya caller ke external LLM provider — melalui sebuah **LLM Provider Abstraction** di dalamnya — dan tidak memiliki database, API publik, authentication, atau deployment terpisah.
* **AI Provider** → `ai_service` tidak terikat pada satu provider tunggal. **OpenRouter** dengan route `openrouter/free` ditetapkan sebagai default provider/route untuk MVP. **Featherless.ai** tetap didukung sebagai **optional provider**, terutama karena merupakan hackathon partner dan berpotensi menyediakan inference credits. **Mock AI Provider** tersedia untuk local development, testing, dan situasi ketika tidak ada real AI API yang dapat diakses.
* **AI Model** → tidak ada primary/fallback model yang di-hardcode secara permanen ke dalam arsitektur. Model spesifik seperti `gpt-oss-20b` atau `Nemotron 3 Nano 30B A3B` dapat digunakan secara opsional ketika pinned/deterministic model selection dibutuhkan dan model tersebut tersedia pada provider/plan yang dipilih. Baseline MVP tetap menggunakan `openrouter/free` sebagai default route.
* **RAG / Retrieval** → menggunakan PostgreSQL filter query berbasis `material_id` dan `topic/subtopic_id`. Vector database tidak digunakan pada MVP.
* **Chunking** → menggunakan fixed-length chunking dengan target **~1.000 karakter dan ~200 karakter overlap**. Heading-based atau heading-regex chunking tidak digunakan.
* **PDF Text Extraction** → `spatie/pdf-to-text` dengan Poppler ditetapkan sebagai primary extractor, dengan `smalot/pdfparser` sebagai optional fallback.
* **File Storage** → Laravel Filesystem dengan local disk digunakan untuk menyimpan PDF secara private dan menyediakan authenticated backend-proxied download.
* **Background Processing** → synchronous processing digunakan sebagai baseline MVP. Laravel Queue hanya menjadi opsi cadangan apabila processing terlalu lambat untuk UX.
* **Redis dan Vector Database** → tidak digunakan pada MVP karena tidak terdapat kebutuhan arsitektur yang membenarkan penambahan keduanya.
* **Containerization** → Docker + docker-compose digunakan untuk frontend, backend, dan PostgreSQL. Tidak ada container terpisah untuk `ai_service`.

Dengan keputusan tersebut, tidak ada lagi komponen utama yang berada dalam status *undecided* atau *needs benchmark*. Dokumen ini berfungsi sebagai **final tech stack baseline** sebelum masuk ke tahap Database Design, API Design, AI Architecture, dan UI/UX implementation.

**Kesimpulan satu kalimat:** Studyback MVP memiliki tech stack dan arsitektur pendukung yang sudah ditetapkan secara final — termasuk business logic yang independen terhadap AI provider tertentu — sehingga implementasi dapat dimulai tanpa menambahkan komponen infrastruktur baru di luar keputusan yang tercantum dalam dokumen ini.

---

## 2. Architecture → Tech Stack Mapping

| Architecture Component                                                       | Technology                                                                                      | Status                                           |
| ---------------------------------------------------------------------------- | ------------------------------------------------------------------------------------------------ | ------------------------------------------------ |
| Frontend (SPA)                                                               | React                                                                                            | KEEP                                             |
| API / Application Backend                                                    | Laravel (Controllers + Form Requests)                                                            | KEEP                                             |
| Application Modules (Materials, Topics, Study Session, Quiz, Learning State) | Laravel Service classes / Actions, satu per modul, di dalam satu Laravel app (modular monolith)  | KEEP + terapkan sebagai folder per modul         |
| Authentication                                                               | Laravel Sanctum (session/token)                                                                  | ADD (bagian dari Laravel, bukan komponen baru)   |
| AI Orchestrator                                                              | `ai_service` — in-process Laravel service, thin dan stateless                                    | KEEP + tetapkan sebagai internal Laravel service |
| LLM Provider Abstraction                                                     | Interface provider-agnostic di dalam `ai_service` (OpenRouter / Featherless.ai / Mock)           | ADD (bagian dari `ai_service`, bukan komponen baru) |
| Retrieval / RAG                                                              | PostgreSQL query filter (material_id + topic_id)                                                 | KEEP                                             |
| LLM Interface                                                                | HTTP client dari `ai_service`, melalui LLM Provider Abstraction, ke external LLM provider (OpenAI-compatible) | KEEP + generalisasi dari Featherless-only menjadi provider-agnostic |
| Database                                                                     | PostgreSQL                                                                                       | KEEP                                             |
| File Storage                                                                 | Laravel Filesystem (local disk driver)                                                           | ADD (konfigurasi, bukan tool baru)               |
| Material Processing Pipeline                                                 | Laravel job/controller action (sync) + library PDF extraction                                    | ADD library                                      |
| Background Processing                                                        | Tidak ada (synchronous), Laravel Queue `sync` driver jika diperlukan                              | NOT REQUIRED (Redis), OPTIONAL (Queue)           |
| Containerization                                                             | Docker (docker-compose: frontend, app, db)                                                       | KEEP                                             |

**Catatan tentang posisi `ai_service`:** `ai_service` merupakan **in-process service di dalam aplikasi Laravel**, bukan Python/Node service, microservice, atau container terpisah. `ai_service` tidak memiliki API publik, authentication terpisah, database, atau deployment independen.

`ai_service` berfungsi sebagai thin, stateless abstraction layer yang bertanggung jawab untuk membangun prompt, memilih/mengonfigurasi provider, memilih/mengonfigurasi model, memanggil external LLM provider melalui LLM Provider Abstraction, menangani retry/fallback, memvalidasi structured output, dan menormalisasi response dari provider menjadi format internal yang konsisten. Laravel tetap menjadi satu-satunya pemilik business state dan database state. `ai_service` tidak pernah menulis langsung ke database.

Komunikasi AI menggunakan HTTP hanya pada boundary eksternal:

**Laravel `ai_service` → LLM Provider Abstraction → Configured External LLM Provider (OpenRouter / Featherless.ai / Mock)**

Tidak ada HTTP communication antara Laravel dengan `ai_service` karena `ai_service` berjalan di dalam proses aplikasi Laravel yang sama. Detail provider-specific (base URL, API key, format request) diisolasi di dalam implementation/configuration layer LLM Provider Abstraction, sehingga modul aplikasi (Materials, Topics, Quiz, dsb.) tidak pernah bergantung langsung pada OpenRouter, Featherless, atau model tertentu.

Keputusan ini menetapkan arsitektur **Modular Monolith** untuk MVP dan menolak model hybrid atau standalone AI service untuk saat ini.


---

# 3. Recommended Tech Stack

| Area                              | Technology                                            | Purpose                                                                                                                  | Status       |
| ---------------------------------- | ------------------------------------------------------ | -------------------------------------------------------------------------------------------------------------------------- | ------------ |
| Frontend                          | React                                                  | SPA untuk Home / My Materials / Studyback Workspace                                                                      | FINAL        |
| Backend/API                       | Laravel                                                | Modular monolith, seluruh business logic & state mutation                                                                | FINAL        |
| Database                          | PostgreSQL                                             | Materials, topics, subtopics, chunks, sessions, quizzes, learning state                                                  | FINAL        |
| Auth                               | Laravel Sanctum                                        | Session/token authentication dan user ownership scoping                                                                  | FINAL        |
| AI Integration                    | `ai_service` (Laravel in-process service)              | Thin wrapper untuk prompt construction, provider-agnostic LLM calls, retry handling, dan structured output validation    | FINAL        |
| LLM Provider Abstraction          | Interface di dalam `ai_service`                        | Menyembunyikan detail provider-specific dari application modules; memungkinkan penggantian provider tanpa mengubah business logic | FINAL        |
| Default AI Provider               | OpenRouter                                             | Default provider untuk inference — topic extraction, quiz generation, Teach Me, dan answer evaluation                    | FINAL        |
| Default AI Route/Model            | `openrouter/free`                                      | Free-model router pada OpenRouter; dynamically memilih model gratis yang tersedia                                       | FINAL        |
| Optional AI Provider              | Featherless.ai                                         | Hackathon partner; digunakan apabila dikonfigurasi dan inference credits berhasil di-claim                               | OPTIONAL     |
| Development/Test AI Provider      | Mock AI Provider                                       | Digunakan untuk local development dan automated testing tanpa memanggil real AI API                                      | OPTIONAL     |
| Pinned Model Strategy (opsional)  | mis. `gpt-oss-20b`, `Nemotron 3 Nano 30B A3B`          | Digunakan hanya ketika deterministic model selection dibutuhkan dan model tersedia pada provider/plan yang dipilih        | OPTIONAL     |
| PDF Text Extraction               | `spatie/pdf-to-text` (wrap `pdftotext` dari Poppler)   | Extraction PDF → teks mentah                                                                                             | FINAL        |
| PDF Extraction Fallback           | `smalot/pdfparser`                                     | Fallback jika binary Poppler tidak tersedia di environment deploy                                                        | OPTIONAL     |
| File Storage                      | Laravel Filesystem, driver `local`                     | Menyimpan PDF asli untuk Download Material                                                                               | FINAL        |
| RAG / Retrieval                   | PostgreSQL `WHERE`-filter query                        | Filter chunk berdasarkan `material_id` + `topic/subtopic_id`                                                             | FINAL        |
| Chunking                          | PHP native fixed-length chunking                       | Membagi teks secara deterministik sebelum topic identification                                                          | FINAL        |
| Chunk Size                        | ~1,000 characters                                       | Target ukuran setiap chunk                                                                                               | FINAL        |
| Chunk Overlap                     | ~200 characters                                         | Mempertahankan konteks antar chunk                                                                                       | FINAL        |
| Background Processing             | Synchronous (inline)                                    | Processing dilakukan dalam request lifecycle untuk MVP                                                                   | FINAL        |
| Background Processing (opsional)  | Laravel Queue, driver `sync` atau `database`           | Hanya digunakan jika processing upload terlalu lambat untuk UX                                                           | OPTIONAL     |
| Redis                              | —                                                       | Tidak ada use case yang membutuhkan Redis pada MVP                                                                       | NOT REQUIRED |
| Vector Database                   | —                                                       | Retrieval dibatasi pada single-material/topic; PostgreSQL filtering cukup                                                | NOT REQUIRED |
| Containerization                  | Docker + docker-compose                                | Container untuk Laravel, React, dan PostgreSQL                                                                           | FINAL        |
| API Communication                 | REST (JSON)                                             | Frontend ↔ Laravel                                                                                                       | FINAL        |
| External AI Communication         | HTTPS REST API (OpenAI-compatible)                      | Laravel `ai_service` ↔ configured LLM provider (OpenRouter default, Featherless optional)                                | FINAL        |

### AI Provider & Model Configuration

Studyback tidak lagi bergantung secara ketat pada satu provider AI tunggal atau satu model tunggal. `ai_service` mengekspos sebuah **LLM Provider Abstraction** di mana provider dan model dapat dikonfigurasi, bukan di-hardcode ke dalam business logic.

**Default Provider — OpenRouter**

OpenRouter dipilih sebagai default provider untuk MVP karena:

* Menyediakan OpenAI-compatible API sehingga integrasi dari sisi `ai_service` tetap sederhana (HTTP client yang sama dengan yang sebelumnya digunakan untuk Featherless).
* Menyediakan route `openrouter/free`, yaitu free-model router yang secara dinamis memilih model gratis yang sedang tersedia di OpenRouter — bukan satu model tunggal seperti `gpt-oss-20b`.
* Tidak mengunci Studyback pada satu model spesifik; ketersediaan model gratis di OpenRouter dapat berubah dari waktu ke waktu, dan router menangani pemilihan tersebut di level provider, bukan di level arsitektur aplikasi.

**Default Route — `openrouter/free`**

`openrouter/free` adalah router, bukan model individual. Beberapa hal penting untuk didokumentasikan dengan benar:

* `openrouter/free` secara dinamis memilih dari beberapa varian model gratis yang tersedia di OpenRouter pada saat request dikirim.
* Router dapat mempertimbangkan kapabilitas yang dibutuhkan oleh request, termasuk structured outputs, tool calling, image understanding, dan kapabilitas lain yang didukung.
* Karena pool model gratis yang tersedia dapat berubah, Studyback **tidak** menjadikan arsitektur intinya bergantung pada satu model spesifik di baliknya.
* Model spesifik yang akhirnya dipilih di balik `openrouter/free` merupakan **implementation/runtime detail**, bukan keputusan arsitektur aplikasi yang permanen.

**Optional Provider — Featherless.ai**

Featherless.ai tetap didukung sebagai provider opsional, terutama karena:

* Featherless.ai merupakan hackathon partner untuk event ini.
* Peserta berpotensi memperoleh inference credits apabila berhasil melakukan klaim credits dari Featherless.
* Featherless.ai menyediakan endpoint OpenAI-compatible, sehingga dapat diintegrasikan melalui LLM Provider Abstraction yang sama tanpa mengubah business logic aplikasi.

Featherless.ai **tidak** menjadi provider wajib. Apabila tidak dikonfigurasi atau credits tidak berhasil diklaim, aplikasi tetap dapat berjalan sepenuhnya menggunakan OpenRouter (atau Mock AI Provider untuk development).

**Development/Test Provider — Mock AI Provider**

Mock AI Provider digunakan untuk:

* Local development tanpa membutuhkan API key real.
* Automated testing yang membutuhkan output deterministic dan tidak bergantung pada layanan eksternal.
* Situasi ketika tidak ada real AI API yang dapat diakses (mis. rate limit, downtime, atau credits habis).

**Pinned Model Strategy (Opsional)**

Model spesifik seperti `gpt-oss-20b` atau `Nemotron 3 Nano 30B A3B` **boleh** digunakan sebagai pinned model apabila:

* Deterministic model selection dibutuhkan (misalnya untuk konsistensi hasil selama demo), dan
* Model tersebut tersedia pada provider/plan yang dikonfigurasi.

Model-model ini adalah **model options**, bukan router — berbeda dari `openrouter/free` yang merupakan router. Model-model ini **tidak** ditetapkan sebagai primary/fallback model wajib pada Tech Stack; penetapan tersebut, jika dibutuhkan, didokumentasikan sebagai strategi opsional pada AI Architecture document, bukan sebagai keputusan Tech Stack yang mengunci implementasi.

### AI Service Architecture

`ai_service` merupakan **in-process Laravel service**, bukan container atau backend service terpisah.

Dengan demikian:

* `ai_service` berjalan di dalam aplikasi Laravel.
* Tidak terdapat komunikasi REST antara Laravel dan `ai_service`.
* `ai_service` menjadi abstraction layer antara business logic Laravel dan external LLM provider yang dikonfigurasi.
* `ai_service` menangani prompt construction, provider selection, model selection, API request, retry/fallback, structured-output validation, dan response normalization.
* External LLM provider (OpenRouter secara default, Featherless.ai secara opsional, atau Mock AI Provider untuk development/testing) diakses melalui LLM Provider Abstraction di dalam `ai_service`.
* Tidak diperlukan container khusus untuk `ai_service`.

Arsitektur komunikasi AI:

**React → Laravel API → `ai_service` → LLM Provider Abstraction → Configured External LLM Provider**

Provider dan model yang digunakan ditentukan melalui konfigurasi (lihat Section 7.3 — Environment Configuration), bukan hard-coded di dalam business logic.

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
Proses dilakukan melalui `ai_service`, yang meneruskan request ke provider LLM yang dikonfigurasi (default: OpenRouter dengan route `openrouter/free`; opsional: Featherless.ai atau Mock AI Provider).
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
`ai_service` → Configured LLM Provider (OpenRouter default / Featherless optional / Mock)
     ↓
AI Response

Implementasi teknis (konsep, bukan schema):

Chunks memiliki relasi terhadap material, topic, dan jika diperlukan subtopic.
PostgreSQL menggunakan index pada kolom filtering yang relevan untuk menjaga query tetap cepat.
Retrieval dilakukan menggunakan filtering berdasarkan material dan topic/subtopic.
Tidak membutuhkan vector embedding, vector database, atau ekstensi PostgreSQL tambahan untuk MVP.

---

## 7. AI Provider & Model Selection dan Structured Output Flow

### 7.1 Rekomendasi

Studyback menggunakan pendekatan **provider-agnostic**: business logic aplikasi bergantung pada abstraksi `ai_service`, bukan pada satu provider atau model tertentu.

**DEFAULT PROVIDER: OpenRouter, route `openrouter/free`**

- OpenRouter dipilih sebagai default provider untuk MVP karena menyediakan OpenAI-compatible API dan sebuah free-model router (`openrouter/free`) yang secara dinamis memilih model gratis yang tersedia.
- `openrouter/free` **bukan** model individual — ia adalah router yang dapat mempertimbangkan kapabilitas yang dibutuhkan request (structured outputs, tool calling, image understanding, dsb.) ketika memilih model gratis yang tersedia saat itu.
- Karena pool model gratis dapat berubah sewaktu-waktu, model spesifik di balik `openrouter/free` diperlakukan sebagai runtime detail, bukan keputusan arsitektur permanen.

**OPTIONAL PROVIDER: Featherless.ai**

- Digunakan apabila dikonfigurasi (`FEATHERLESS_API_KEY` tersedia) dan inference credits berhasil diklaim, mengingat Featherless.ai adalah hackathon partner untuk event ini.
- Diakses melalui LLM Provider Abstraction yang sama sehingga tidak membutuhkan perubahan pada business logic aplikasi.

**DEVELOPMENT/TEST PROVIDER: Mock AI Provider**

- Digunakan untuk local development dan automated testing agar tidak bergantung pada layanan eksternal maupun API key real.

**OPTIONAL PINNED MODEL STRATEGY**

Apabila deterministic model selection dibutuhkan (misalnya demi konsistensi hasil saat demo), `ai_service` dapat dikonfigurasi untuk menggunakan model yang di-pin secara eksplisit, selama model tersebut tersedia pada provider/plan yang dikonfigurasi. Contoh model options yang dapat dipertimbangkan (bukan router, dan bukan model gratis permanen):

- `gpt-oss-20b` — context window besar (128K), mendukung tool-use/structured output.
- `Nemotron 3 Nano 30B A3B` — alternatif model options untuk task conversational seperti Teach Me.

Sebagai **optional optimization** (didokumentasikan lebih lanjut pada AI Architecture document), task-specific model mapping dapat berupa:

| Use Case            | Model (opsional, jika tersedia)                          |
| -------------------- | ----------------------------------------------------------- |
| Topic Identification | `gpt-oss-20b` (jika tersedia)                                |
| Teach Me              | `Nemotron 3 Nano 30B A3B` atau `gpt-oss-20b` (jika tersedia) |
| Quiz Generation       | `gpt-oss-20b` (jika tersedia)                                |
| Answer Evaluation     | `gpt-oss-20b` (jika tersedia)                                |

Baseline MVP tetap **`openrouter/free`** untuk seluruh use case di atas; task-specific pinned model hanyalah optimisasi opsional dan tidak mengubah baseline Tech Stack.

### 7.2 Fallback Strategy

Karena ketersediaan provider dan model dapat berubah, fallback logic **tidak** didefinisikan sebagai satu pasangan primary-model → fallback-model yang fixed. Sebagai gantinya, fallback dibedakan menjadi tiga level:

1. **Provider fallback** — apabila default provider (OpenRouter) tidak dapat diakses atau gagal, `ai_service` dapat dikonfigurasi untuk mencoba provider opsional (Featherless.ai) apabila tersedia dan dikonfigurasi.
2. **Model fallback** — apabila implementasi menggunakan pinned model, model-level fallback dapat menggunakan model lain yang kompatibel pada provider yang sama (mis. `gpt-oss-20b` ↔ `Nemotron 3 Nano 30B A3B`), sesuai konfigurasi.
3. **Development fallback** — apabila tidak ada provider real yang dapat diakses (mis. selama local development atau automated testing), `ai_service` menggunakan Mock AI Provider.

Prinsipnya: fallback logic bersifat **configurable**, bukan hard-coded ke dalam business logic Laravel. Modul aplikasi memanggil `ai_service` tanpa mengetahui provider/model mana yang sedang aktif; `ai_service`-lah yang menangani retry dan fallback berdasarkan konfigurasi yang berlaku.

### 7.3 Environment Configuration

Provider dan model dikonfigurasi melalui environment variables, bukan hard-coded di dalam business logic. Contoh konfigurasi konseptual:

```
AI_PROVIDER=openrouter
AI_MODEL=openrouter/free

OPENROUTER_API_KEY=your_openrouter_api_key

# Optional — hanya diperlukan jika Featherless.ai digunakan sebagai provider fallback/opsional
FEATHERLESS_API_KEY=your_featherless_api_key
```

Detail provider-specific (base URL, header autentikasi, format request/response) diisolasi di dalam implementation/configuration layer LLM Provider Abstraction (mis. per-provider adapter class di dalam `ai_service`), sehingga penggantian atau penambahan provider tidak membutuhkan perubahan pada modul aplikasi (Materials, Topics, Quiz, Learning State, dsb.).

### 7.4 AI Service Responsibilities

`ai_service` bertanggung jawab untuk:

- membangun prompt;
- memilih/mengonfigurasi provider (OpenRouter default, Featherless.ai opsional, Mock untuk dev/test);
- memilih/mengonfigurasi model (default `openrouter/free`, atau pinned model bila dikonfigurasi);
- mengirim AI request ke provider yang aktif;
- menangani error dari provider;
- menangani retry/fallback sesuai konfigurasi (Section 7.2);
- memvalidasi structured output;
- menormalisasi response dari provider menjadi format internal yang konsisten; dan
- menyembunyikan detail implementasi provider-specific dari modul aplikasi.

`ai_service` **tidak**:

- memiliki business state;
- menulis langsung ke database;
- menjadi microservice terpisah;
- memiliki API publik; dan
- berisi perhitungan learning-state yang bersifat application-specific.

Modul aplikasi Laravel tetap bertanggung jawab untuk:

- mempersist data;
- menghitung skor quiz;
- memperbarui mastery;
- menentukan learning state; dan
- menerapkan deterministic business rules.

### 7.5 Structured Output Flow

```
LLM (Configured Provider — OpenRouter default / Featherless optional / Mock)
  ↓ raw output
Structured JSON (schema: topics[], quiz_questions[], evaluation{verdict, feedback, subtopic})
  ↓ divalidasi
ai_service (parse + validate JSON shape; retry atau fallback ke provider/model lain sesuai konfigurasi jika invalid)
  ↓ hasil bersih (data, bukan opini tentang state)
Laravel (Application Modules: Processing, Quiz, Learning State)
  ↓ menerapkan aturan deterministic
Application Logic (persist topics, simpan quiz, hitung skor, update mastery/status)
```

Structured-output validation bekerja **independen dari provider/model** yang sedang digunakan — kontrak schema (`topics[]`, `quiz_questions[]`, `evaluation{}`) tetap sama apapun provider/model di baliknya. Apabila suatu provider/model tidak dapat secara reliable memenuhi kontrak structured-output tersebut, `ai_service` dapat retry atau menggunakan provider/model lain yang telah dikonfigurasi, sesuai strategi fallback pada Section 7.2.

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
│   │       └── AiOrchestrator.php   # in-process ai_service; satu-satunya caller ke configured LLM provider melalui provider abstraction
│   └── ... (struktur Laravel standar)
└── docs/                     # dokumen ini + Product Spec + System Architecture
```

`ai_service` tidak memiliki folder terpisah di root project. `ai_service` diimplementasikan sebagai **in-process Laravel service** melalui `AiOrchestrator.php` di dalam `backend/app/Services/`.

`AiOrchestrator.php` merupakan thin, stateless service yang bertanggung jawab untuk:

* membangun prompt;
* memilih/mengonfigurasi provider dan model melalui LLM Provider Abstraction;
* memanggil configured external LLM provider (default: OpenRouter `openrouter/free`; opsional: Featherless.ai; dev/test: Mock AI Provider);
* menangani retry/fallback sesuai konfigurasi; dan
* memvalidasi structured output.

`AiOrchestrator.php` merupakan **single caller** ke external LLM provider — bukan ke satu provider tertentu, melainkan ke provider yang sedang dikonfigurasi melalui provider abstraction.

Folder tambahan di root hanya `docs/`. Tidak ada folder `ai_service/`, `workers/`, `queue/`, atau `services/` terpisah karena MVP tidak menggunakan background worker atau microservice terpisah.

Modularitas diwujudkan melalui struktur folder di dalam `backend/`, bukan sebagai deployable unit terpisah. Struktur ini konsisten dengan keputusan **Modular Monolith** pada Architecture Section 2.


---

## 10. Additional Technologies to Learn

### MUST LEARN
- **`spatie/pdf-to-text` + Poppler-utils** — cara install di Dockerfile, cara handle jika binary gagal/PDF corrupt (untuk failure handling Section 13: "PDF extraction fails").
- **Laravel Filesystem API** (`Storage::disk()`) — khususnya cara serve file lewat route terautentikasi (bukan file public), untuk memenuhi Security Section 14.
- **OpenRouter API (OpenAI-compatible endpoint)** — format request/response, cara menggunakan route `openrouter/free`, cara memaksa/mendorong structured JSON output (system prompt + schema instruction), serta rate limit pada free tier.
- **Laravel Sanctum** — jika belum pernah pakai, ini auth paling ringan untuk SPA + API token, sesuai kebutuhan Section 14.
- **Environment-based configuration untuk AI provider** — cara mengisolasi konfigurasi provider (`AI_PROVIDER`, `AI_MODEL`, API key per provider) di luar business logic, agar penggantian provider tidak membutuhkan perubahan kode pada modul aplikasi.

### SHOULD LEARN
- **Featherless API (OpenAI-compatible endpoint)** — dipelajari sebagai provider opsional, terutama jika inference credits dari hackathon berhasil diklaim.
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
| AI Integration Layer   | `ai_service` — thin, stateless, in-process Laravel service; satu-satunya caller ke configured LLM provider melalui LLM Provider Abstraction |
| Default AI Provider    | OpenRouter                                                                                                          |
| Default AI Route/Model | `openrouter/free` — free-model router, dynamically memilih model gratis yang tersedia                             |
| Optional AI Provider   | Featherless.ai — hackathon partner, digunakan jika dikonfigurasi dan credits tersedia                              |
| Dev/Test AI Provider   | Mock AI Provider                                                                                                    |
| Pinned Model (opsional)| mis. `gpt-oss-20b`, `Nemotron 3 Nano 30B A3B` — hanya jika deterministic selection dibutuhkan dan tersedia          |
| Background Processing  | Synchronous (inline); Laravel Queue `database` driver sebagai opsi cadangan                                        |
| Redis                   | Tidak digunakan                                                                                                     |
| Vector Database        | Tidak digunakan                                                                                                     |
| Containerization        | Docker + docker-compose (frontend, backend, db)                                                                    |
| API Communication       | REST/JSON                                                                                                           |
| AI Communication        | HTTPS REST/JSON, OpenAI-compatible (`ai_service` → LLM Provider Abstraction → configured provider)                 |

### Chunking Strategy

Chunking menggunakan **fixed-length chunking** dengan target:

* Chunk length: **~1,000 characters**
* Chunk overlap: **~200 characters**

Heading-based atau heading-regex chunking **tidak digunakan**.

Chunking bersifat deterministic dan dilakukan sebelum topic identification.

### AI Provider & Model Strategy

Studyback menggunakan konfigurasi AI yang **provider-agnostic**, bukan fixed ke satu provider/model:

* **Default provider:** OpenRouter
* **Default route:** `openrouter/free`
* **Optional provider:** Featherless.ai (hackathon partner, jika dikonfigurasi dan credits tersedia)
* **Dev/test provider:** Mock AI Provider
* **Optional pinned model:** mis. `gpt-oss-20b` atau `Nemotron 3 Nano 30B A3B`, hanya jika deterministic model selection dibutuhkan dan model tersedia pada provider/plan yang dikonfigurasi

`openrouter/free` digunakan sebagai default route untuk seluruh AI use case pada MVP (topic extraction, quiz generation, Teach Me, answer evaluation). Task-specific pinned model mapping, jika digunakan, bersifat opsional dan didokumentasikan sebagai optimisasi pada AI Architecture document — bukan sebagai baseline Tech Stack yang wajib.

Fallback mengikuti strategi berlapis (provider fallback → model fallback → development fallback) sebagaimana didefinisikan pada Section 7.2, dan bersifat configurable, bukan hard-coded ke dalam business logic.

### `ai_service` Architecture

`ai_service` merupakan **in-process Laravel service**, bukan service atau container terpisah.

`ai_service` bertanggung jawab untuk:

* membangun prompt;
* memilih/mengonfigurasi provider dan model melalui LLM Provider Abstraction;
* mengirim request ke configured external LLM provider;
* menangani retry/fallback sesuai konfigurasi;
* memvalidasi structured output; dan
* menyediakan abstraction layer antara business logic Laravel dan AI provider — sehingga business logic tidak pernah bergantung langsung pada OpenRouter, Featherless, atau model tertentu.

Tidak terdapat komunikasi REST antara Laravel dengan `ai_service` karena keduanya berada dalam proses aplikasi Laravel yang sama.

Arsitektur AI:

**React → Laravel API → `ai_service` → LLM Provider Abstraction → Configured External LLM Provider**

Jika default provider/route gagal atau timeout:

**`ai_service` → provider/model fallback sesuai konfigurasi (mis. retry pada `openrouter/free`, lalu Featherless.ai jika dikonfigurasi, lalu Mock AI Provider pada environment development)**


Stack ini siap menjadi dasar untuk tahap berikutnya:

```
Tech Stack (dokumen ini)
  ↓
Database Design       — schema untuk materials, topics, subtopics, chunks, sessions, quizzes, learning_state
  ↓
API Design            — route/contract per modul (Materials, Processing, StudySession, Quiz, LearningState)
  ↓
AI Architecture        — prompt template per capability (explain, quiz, evaluate, extract), JSON schema per capability, serta detail task-specific model mapping (opsional)
  ↓
UI/UX Design           — Home, My Materials, Material Detail, Study Session Config (modal), Studyback Workspace
```