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


# Studyback — Turn Your Old Study Materials Into a Personal AI Teacher

## 1. Ringkasan Produk

### 1.1 Problem

Mahasiswa sering ingin mempelajari kembali materi kuliah yang sudah lama dipelajari, tetapi materi tersebut tersebar di berbagai PDF, slide, dan catatan. Ketika kembali belajar, mereka harus menentukan sendiri apa yang perlu dipelajari, meminta penjelasan, mencari latihan soal, dan menilai sendiri apakah mereka benar-benar sudah memahami materi tersebut.

### 1.2 Solution

Studyback adalah AI personal teacher yang mengubah materi pembelajaran milik pengguna menjadi pengalaman belajar adaptif.

Pengguna cukup mengunggah materi pembelajaran, kemudian Studyback memahami dan mengorganisasi materi tersebut ke dalam Material Library. Ketika pengguna ingin kembali belajar di kemudian hari, mereka dapat memilih materi yang pernah diunggah, melihat informasi dan topik yang tersedia, lalu memulai Adaptive Study Session.

Studyback dapat:

- Menjelaskan konsep
- Menyederhanakan materi
- Menguji pemahaman melalui quiz
- Mengevaluasi jawaban
- Mengidentifikasi topik yang masih lemah
- Mengarahkan pengguna untuk mempelajarinya kembali

### 1.3 Core Product Principle

Studyback bukan sekadar chatbot yang menjawab pertanyaan tentang materi. Studyback adalah:

> "An AI teacher that understands your study material, teaches you, tests you, identifies what you don't understand, and guides you back to what you need to learn."

**Core learning loop:**

```
Learn → Test → Evaluate → Review
```

**Core product flow:**

```
Material → Study Session → Learning State → Personalized Review
```

### 1.4 Why Interesting?

Berbeda dari chatbot belajar yang hanya menunggu pertanyaan pengguna, Studyback menjalankan learning loop di atas secara aktif — sistem tidak hanya menjawab pertanyaan, tetapi menggunakan hasil interaksi pengguna untuk menentukan apa yang perlu dipelajari selanjutnya.

Materi belajar berasal dari dokumen pengguna sendiri, sehingga pengalaman belajar tetap berfokus pada konteks dan kurikulum yang mereka gunakan.

Studyback juga menyimpan materi dan learning state pengguna sehingga proses belajar tidak berhenti setelah satu sesi. Pengguna dapat kembali ke materi yang sama di kemudian hari dan melanjutkan proses belajar berdasarkan progress sebelumnya.

### 1.5 AI Role

AI digunakan untuk:

- Memahami materi pengguna
- Mengidentifikasi topic/concept
- Menghasilkan penjelasan yang adaptif dan menyederhanakan konsep sesuai kebutuhan pengguna
- Membuat pertanyaan/quiz berdasarkan materi
- Mengevaluasi jawaban pengguna dan memberikan feedback
- Menentukan konsep yang perlu dipelajari kembali

### 1.6 Target User

Mahasiswa yang ingin mempelajari kembali materi kuliah yang pernah mereka pelajari, terutama menjelang ujian atau saat melakukan revisi terhadap semester sebelumnya.

Konsep ini dapat diperluas ke siswa atau karyawan untuk kebutuhan pembelajaran dan knowledge refresh di masa depan.

---

## 2. Product Flow (Ringkasan Menyeluruh)

Studyback memiliki dua jalur utama tergantung apakah pengguna mengunggah materi baru atau membuka materi yang sudah ada.

**NEW MATERIAL FLOW**

```
Home
  ↓
Upload Material
  ↓
Material Processing
  ↓
Material Ready
  ↓
Start Learning
  ↓
Studyback Workspace
```

**EXISTING MATERIAL FLOW**

```
Home / My Materials
  ↓
Material Detail
  ↓
Start Study Session
  ↓
Study Session Configuration
  ↓
Studyback Workspace
```

**Alur lengkap setelah sesi belajar selesai:**

```
Studyback Workspace
  ↓
Learn → Test → Evaluate → Weak Topic Detection → Review
  ↓
Learning State diperbarui
  ↓
Kembali ke My Materials
  ↓
User dapat melanjutkan belajar di kemudian hari
```

---

## 3. Home

Home adalah entry point utama, terutama untuk pengguna baru atau pengguna yang ingin langsung mempelajari materi baru. Home **tidak** menjalankan learning session — Home hanya menangani proses dari upload hingga materi siap dipelajari.

**Layout:**

- Hero besar di kiri — menangani seluruh upload flow
- Profile di kanan atas
- Recent Materials di bawah Profile (tetap berada di area samping Hero), menampilkan ± 5 materi terbaru, dengan tautan **See More / View All** menuju My Materials

Jika pengguna belum login:
- Profile → konten blur + glass overlay + tombol Login
- Recent Materials → tidak membocorkan materi pengguna, gunakan empty/private state

Visual mengikuti arah glassmorphism: soft gradient, translucent glass, rounded container, subtle border/glow, typography besar, dan primary button yang solid/kontras.

**Upload Flow (di dalam Hero):**

```
Upload Material
  ↓
Uploading...
  ↓
Extracting Content...
  ↓
Understanding Material...
  ↓
Identifying Topics...
  ↓
Material Ready ✓
  ↓
[Start Learning]
  ↓
Studyback Workspace
```

Contoh state setelah materi selesai diproses:

```
Material Ready ✓

Object Oriented Programming
5 topics identified

[Start Learning]
```

Setelah **Start Learning** ditekan, user langsung diarahkan ke Studyback Workspace dan proses belajar dimulai di sana.

---

## 4. My Materials (Material Library)

My Materials bertanggung jawab hanya untuk:

- Melihat, mencari, dan memfilter materi yang pernah diunggah
- Memilih materi (material card) untuk dibuka
- Membuka Material Detail

**Upload bukan flow utama di halaman ini.** Upload tetap dilakukan melalui Home. Jika tombol **[+ Upload Material]** ditampilkan di sini, tombol tersebut hanya berfungsi sebagai shortcut yang mengarahkan user kembali ke Home untuk melakukan proses upload:

```
My Materials → [+ Upload Material] → Home → Upload Material
```

Jika user belum memiliki materi, tampilkan empty state:

```
No materials yet

Upload your first study material and let Studyback
become your personal AI teacher.

[Upload Material] → mengarah ke Home
```

My Materials tidak menjalankan proses learning session apa pun — halaman ini murni tempat memilih materi sebelum masuk ke Material Detail.

---

## 5. Material Detail

Ketika pengguna memilih material card, pengguna tidak langsung masuk ke Study Session — mereka terlebih dahulu melihat halaman detail materi.

**Isi Material Detail:**

*Material Information*
- Material name/title
- Short description
- Upload date
- Number of topics

*Topics*
- Topic/concept list

*Learning Progress*
- Overall mastery (jika materi sudah pernah dipelajari)
- Not Started (jika belum pernah dipelajari)

*Actions*
- Download Material
- Start Study Session (primary action)

Material Detail **tidak** menjalankan proses pembelajaran, quiz, review, atau chat AI — halaman ini murni informasi dan entry point menuju Study Session. Seluruh proses pembelajaran terjadi di Studyback Workspace.

```
Material Detail
  ↓
[Start Study Session]
  ↓
Study Session Configuration
  ↓
Studyback Workspace
```

---

## 6. Study Session Configuration

Sebelum memulai sesi, pengguna dapat mengatur cara mereka ingin belajar. Untuk menjaga scope tetap realistis dalam hackathon 48 jam, **Study Session Configuration bukan halaman/destination baru**, melainkan modal/dialog/overlay ringan yang muncul sebelum masuk ke Studyback Workspace.

**Opsi konfigurasi:**

- **Topics** — topic/concept mana yang ingin dipelajari
- **Learning Mode** — Teach Me / Quiz Me / Review Weak Topics / Guided Study Session
- **Difficulty** — Easy / Medium / Hard

Guided Study Session adalah mode utama yang menjalankan learning loop penuh (Learn → Test → Evaluate → Review).

---

## 7. Studyback Workspace

Studyback Workspace adalah **satu-satunya tempat** di mana actual learning experience berlangsung. Semua mode belajar — Teach Me, Quiz Me, Review Weak Topics, Guided Study Session — tetap berada dalam satu workspace yang sama; tidak ada halaman terpisah seperti `/teach`, `/quiz`, atau `/review`.

Studyback Workspace diakses melalui salah satu dari dua jalur berikut:

```
Home → Upload Material → Material Ready → Start Learning → Studyback Workspace
```
```
My Materials → Material Detail → Start Study Session → Study Session Configuration → Studyback Workspace
```

### 7.1 Dual Interaction Model

Workspace menggabungkan dua jenis interface dalam satu ruang yang sama, sehingga pengguna tidak merasa berpindah aplikasi:

**Conversational Interface** (digunakan oleh Teach Me & Review Weak Topics)
- Explanation
- Follow-up questions
- Socratic questioning

**Structured Interface** (digunakan oleh Quiz Me)
- Multiple choice
- True/False
- Short answer
- Quiz results

Quiz secara khusus menggunakan structured UI, bukan chat bubble biasa — namun tetap berada di workspace yang sama.

### 7.2 Learning Modes

**Teach Me**
Menggunakan conversational interface. AI menjelaskan topic/concept berdasarkan materi yang dipilih, dapat menyederhanakan penjelasan, memberi contoh, dan menjawab pertanyaan lanjutan.

Contoh:
```
User: "Teach me polymorphism."
Studyback: [menjelaskan polymorphism berdasarkan materi user]

User dapat melanjutkan dengan:
- Explain simpler
- Give example
- Quiz me
```

**Quiz Me**
Menggunakan structured quiz interface.

```
Quiz — Polymorphism
Question 2 of 5

Which statement best explains polymorphism?
○ A. ...
○ B. ...
○ C. ...
○ D. ...

[Submit Answer]
```

Setelah user menjawab, AI mengevaluasi jawaban, menentukan benar/salah, memberikan feedback, menyimpan hasil quiz, dan memperbarui topic mastery.

```
Quiz Complete ✓
3 / 5 Correct
Score: 60%

Topic Performance:
- Polymorphism → 42%
- Inheritance → 82%
- Encapsulation → 91%

[Review Weak Topics]   [Try Quiz Again]
```

Hasil quiz ditampilkan sebagai summary dengan aksi yang jelas — workspace **tidak** berpindah tampilan secara otomatis/agresif setelah quiz selesai.

**Review Weak Topics**
Menggunakan learning state yang dihasilkan dari quiz dan interaksi sebelumnya. Topic/subtopic dengan mastery rendah ditandai **Needs Review**.

```
Polymorphism
42% Mastery
Needs Review
[Review]
```

Ketika user menekan Review:
- Studyback otomatis memfokuskan AI Teacher pada topic/subtopic tersebut
- AI menjelaskan kembali dengan pendekatan berbeda, memberi contoh, dan dapat meminta user menjelaskan ulang konsep tersebut
- AI memberikan mini-question atau re-test
- Learning state diperbarui berdasarkan hasil review

Review tidak membuka halaman baru — user tetap berada di Studyback Workspace.

**Guided Study Session (Primary Mode)**
Mode utama yang menggabungkan seluruh learning loop dalam satu workspace:

```
Guided Study Session
  ↓
Teach
  ↓
Check Understanding
  ↓
Quiz
  ↓
Evaluate
  ↓
Weak Topic Detection
  ↓
Review
  ↓
Re-test
  ↓
Updated Mastery
```

AI menentukan kapan user perlu berpindah dari satu tahap ke tahap berikutnya berdasarkan hasil interaksi — bukan urutan statis, melainkan mengikuti learning state secara adaptif.

### 7.3 Workspace Layout

Workspace menggunakan dua area utama:

**Left Sidebar (Learning Map + Navigation)**
- Material name
- Overall mastery
- Topic & subtopic list
- Learning status per topic/subtopic
- Expand/collapse progress

**Main Workspace**
- AI Teacher interaction (Teach Me)
- Structured quiz interface (Quiz Me)
- Review interaction (Review Weak Topics)
- Guided Study Session
- Learning feedback

Contoh sidebar:

```
┌─────────────────────────┐
│ ← My Materials          │
│                          │
│ Object Oriented          │
│ Programming               │
│                          │
│ Overall Mastery          │
│ ███████░░░ 72%           │
│                          │
│ TOPICS                   │
│                          │
│ ✓ Object & Class      ▸  │
│ ✓ Encapsulation       ▸  │
│ ◐ Inheritance         ▾  │
│   ██████░░░░ 64%          │
│   In Progress             │
│                          │
│ ⚠ Polymorphism        ▸  │
│ ○ Abstraction          ▸  │
│                          │
└─────────────────────────┘
```

### 7.4 Sidebar sebagai Learning Map

Sidebar bukan hanya navigasi — sidebar juga berfungsi sebagai peta visual yang menunjukkan kondisi pemahaman user terhadap setiap topic/subtopic, sehingga user dapat langsung melihat apa yang sudah dikuasai, sedang dipelajari, perlu direview, dan belum pernah dipelajari.

Topic/subtopic menggunakan interaksi collapsible/accordion, default dalam keadaan compact:

```
Collapsed:
▸ Polymorphism

Expanded:
▾ Polymorphism
  █████░░░░░ 42%
  Needs Review
```

Progress bar hanya ditampilkan ketika subtopic dibuka.

**Learning status:**

| Simbol | Status |
|---|---|
| ✓ | Mastered |
| ◐ | In Progress |
| ⚠ | Needs Review |
| ○ | Not Started |

Jika user menekan subtopic dengan status Needs Review, Studyback langsung memfokuskan AI Teacher pada topic/subtopic tersebut:

```
⚠ Polymorphism
  ↓ user clicks
Studyback focuses AI Teacher on Polymorphism
  ↓
Review / Teach interaction begins
```

Dengan demikian sidebar juga menjadi shortcut menuju personalized review.

---

## 8. Adaptive Learning Loop & Learning State

### 8.1 Learning Loop

```
Learn
  ↓ AI menjelaskan materi/topic
Test
  ↓ AI menghasilkan quiz
Evaluate
  ↓ AI mengevaluasi jawaban & memberi feedback
Weak Topic Detection
  ↓ Sistem menentukan topic yang masih lemah
Review
  ↓ Studyback merekomendasikan materi/topic untuk dipelajari kembali
Learning State diperbarui
```

Hasil quiz/interaksi secara langsung memengaruhi Learning State — loop ini tidak boleh terlihat sebagai workflow statis:

```
Quiz → Calculate Score → Update Topic/Subtopic Mastery → Determine Status
  → Needs Review / In Progress / Mastered → Recommendation / Review
```

### 8.2 Mastery System

Mastery disimpan terutama pada level **Subtopic**, dihitung dengan pendekatan sederhana dan deterministic (bukan knowledge tracing atau machine learning kompleks):

| Skor | Status |
|---|---|
| < 60% | Needs Review |
| 60% – 79% | In Progress |
| ≥ 80% | Mastered |

### 8.3 Learning State (Contoh)

```
Object Oriented Programming

Topic Mastery:
- Class & Object   → 95%  (Mastered)
- Encapsulation    → 87%  (Mastered)
- Inheritance      → 72%  (In Progress)
- Polymorphism     → 42%  (Needs Review)
- Abstraction      → 70%  (In Progress)

Weak Topic: Polymorphism

Recommendation:
"You struggled with Polymorphism. Let's review it."
```

Learning State disimpan agar proses belajar dapat berlanjut di kemudian hari — pengguna dapat kembali ke materi yang sama dan melanjutkan berdasarkan progress sebelumnya.

---

## 9. Prinsip AI Output & Context Boundary

### 9.1 Structured AI Output

Untuk bagian yang membutuhkan data terstruktur, AI menghasilkan structured JSON yang kemudian digunakan oleh application logic. Contoh area yang menggunakan pendekatan ini:

- Topic extraction
- Quiz generation
- Answer evaluation
- Output terkait learning state

Ini dijelaskan pada level arsitektur produk saja, bukan sebagai dokumen implementasi teknis penuh.

### 9.2 Context Boundary (RAG)

Studyback menggunakan materi milik user sebagai sumber utama pembelajaran — AI harus menjawab berdasarkan materi yang dipilih/diunggah user, dengan alur singkat:

```
Material → Chunking → Retrieval → Relevant Context → AI Response
```

Ini dijaga tetap sederhana; tidak diperluas menjadi sistem RAG yang kompleks.

---

## 10. Navigation Structure

Navbar tetap sederhana dan **tidak** memiliki menu "Study" — Studyback Workspace bukan destination navbar utama, melainkan diakses melalui Home atau My Materials.

**Navbar:**
- Home
- My Materials
- Profile

**Jalur menuju Studyback Workspace:**

```
Home → Upload Material → Material Ready → Start Learning → Studyback Workspace
Home → Recent Materials → Material Detail → Start Study Session → Studyback Workspace
My Materials → Material Detail → Start Study Session → Studyback Workspace
```

**Jalur upload dari My Materials:**

```
My Materials → [+ Upload Material] → Home → Upload Material
```

---

## 11. Final Product Architecture

```
HOME
├── Hero
│   └── Upload Material
│       └── Processing
│           └── Material Ready
│               └── Start Learning → Studyback Workspace
├── Profile
└── Recent Materials
    └── View All → My Materials


MY MATERIALS
├── [+ Upload Material] → Home
├── Search / Filter
└── Material Grid
    └── Material Detail
        ├── Material Information
        ├── Topics
        ├── Learning Progress
        ├── Download Material
        └── Start Study Session
            └── Study Session Configuration (modal)
                └── Studyback Workspace


STUDYBACK WORKSPACE
├── Sidebar (Learning Map)
│   ├── Overall Mastery
│   ├── Topics
│   ├── Subtopics
│   ├── Progress
│   └── Learning Status
└── Main Learning Area
    ├── Teach Me
    ├── Quiz Me
    ├── Review Weak Topics
    └── Guided Study Session
        └── Learn → Test → Evaluate → Review
```

---

## 12. 48-Hour MVP Scope

**MUST HAVE**

*Home*
- Upload PDF
- Processing state
- Material Ready state
- Start Learning

*Material*
- My Materials (Material Library)
- Material Detail
- Download Material

*AI*
- PDF text extraction
- Topic/subtopic identification
- Context-aware explanation
- Quiz generation
- Answer evaluation

*Studyback Workspace*
- Topic/subtopic sidebar
- Mastery/status
- Teach Me
- Quiz Me
- Review Weak Topics
- Guided Study Session

*Learning State*
- Quiz score
- Subtopic mastery
- Learning status
- Needs Review detection
- Review recommendation

**SHOULD HAVE**

- Explain simpler / Give example
- Short answer questions
- Smooth transitions
- Loading/skeleton states

**CUT / DEFER**

- Voice tutor, Speech-to-text
- Multi-document reasoning
- Advanced analytics
- Complex recommendation algorithms
- Custom/fine-tuned AI model
- Social features, Gamification
- Advanced search/filter
