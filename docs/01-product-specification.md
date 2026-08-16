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

## 1. Product Summary

### 1.1 Problem

Students often want to revisit course material they studied long ago, but that material is scattered across various PDFs, slides, and notes. When they return to study, they have to decide for themselves what to learn, ask for explanations, find practice questions, and assess on their own whether they truly understand the material.

### 1.2 Solution

Studyback is an AI personal teacher that turns the user's own study material into an adaptive learning experience.

Users simply upload their study material, then Studyback understands and organizes it into the Material Library. When users want to study again later, they can select material they have uploaded before, view the available information and topics, and start an Adaptive Study Session.

Studyback can:

- Explain concepts
- Simplify material
- Test understanding through quizzes
- Evaluate answers
- Identify weak topics
- Guide users to re-learn them

### 1.3 Core Product Principle

Studyback is not just a chatbot that answers questions about the material. Studyback is:

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

Unlike study chatbots that merely wait for user questions, Studyback actively runs the learning loop above — the system does not only answer questions, but uses the results of user interactions to determine what should be learned next.

The study material comes from the user's own documents, so the learning experience remains focused on the context and curriculum they use.

Studyback also stores the user's material and learning state so that the learning process does not stop after a single session. Users can return to the same material later and continue the learning process based on their previous progress.

### 1.5 AI Role

AI is used to:

- Understand the user's material
- Identify topics/concepts
- Generate adaptive explanations and simplify concepts according to the user's needs
- Create questions/quizzes based on the material
- Evaluate user answers and provide feedback
- Determine concepts that need to be re-learned

### 1.6 Target User

Students who want to revisit course material they have studied before, especially before exams or when reviewing previous semesters.

This concept can be extended to students or employees for learning and knowledge refresh needs in the future.

---

## 2. Product Flow (Overall Summary)

Studyback has two main paths depending on whether the user uploads new material or opens existing material.

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

**Complete flow after a study session ends:**

```
Studyback Workspace
  ↓
Learn → Test → Evaluate → Weak Topic Detection → Review
  ↓
Learning State updated
  ↓
Return to My Materials
  ↓
User can continue studying later
```

---

## 3. Home

Home is the main entry point, especially for new users or users who want to study new material right away. Home does **not** run a learning session — Home only handles the process from upload until the material is ready to study.

**Layout:**

- Large hero on the left — handles the entire upload flow
- Profile at the top right
- Recent Materials below the Profile (staying in the side area next to the Hero), showing the ± 5 most recent materials, with a **See More / View All** link to My Materials

If the user is not logged in:
- Profile → blurred content + glass overlay + Login button
- Recent Materials → does not leak the user's material, use an empty/private state

The visual follows a glassmorphism direction: soft gradient, translucent glass, rounded container, subtle border/glow, large typography, and a solid/contrasting primary button.

**Upload Flow (inside the Hero):**

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

Example state after the material has been processed:

```
Material Ready ✓

Object Oriented Programming
5 topics identified

[Start Learning]
```

Once **Start Learning** is pressed, the user is taken directly to the Studyback Workspace and the learning process begins there.

---

## 4. My Materials (Material Library)

My Materials is responsible only for:

- Viewing, searching, and filtering previously uploaded materials
- Selecting a material (material card) to open
- Opening Material Detail

**Upload is not the main flow on this page.** Upload is still done through Home. If the **[+ Upload Material]** button is shown here, it only serves as a shortcut that directs the user back to Home to perform the upload process:

```
My Materials → [+ Upload Material] → Home → Upload Material
```

If the user does not have any material yet, show an empty state:

```
No materials yet

Upload your first study material and let Studyback
become your personal AI teacher.

[Upload Material] → navigates to Home
```

My Materials does not run any learning session process — this page is purely a place to select material before entering Material Detail.

---

## 5. Material Detail

When the user selects a material card, they do not enter the Study Session directly — they first see the material detail page.

**Material Detail contents:**

*Material Information*
- Material name/title
- Short description
- Upload date
- Number of topics

*Topics*
- Topic/concept list

*Learning Progress*
- Overall mastery (if the material has been studied before)
- Not Started (if it has never been studied)

*Actions*
- Download Material
- Start Study Session (primary action)

Material Detail does **not** run any learning, quiz, review, or AI chat process — this page is purely informational and serves as the entry point to the Study Session. All learning processes happen in the Studyback Workspace.

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

Before starting a session, users can configure how they want to study. To keep the scope realistic within a 48-hour hackathon, **Study Session Configuration is not a new page/destination**, but a lightweight modal/dialog/overlay that appears before entering the Studyback Workspace.

**Configuration options:**

- **Topics** — which topic/concept to study
- **Learning Mode** — Teach Me / Quiz Me / Review Weak Topics / Guided Study Session
- **Difficulty** — Easy / Medium / Hard

Guided Study Session is the main mode that runs the full learning loop (Learn → Test → Evaluate → Review).

---

## 7. Studyback Workspace

The Studyback Workspace is the **only place** where the actual learning experience takes place. All learning modes — Teach Me, Quiz Me, Review Weak Topics, Guided Study Session — remain within the same workspace; there are no separate pages such as `/teach`, `/quiz`, or `/review`.

The Studyback Workspace is accessed through one of the following two paths:

```
Home → Upload Material → Material Ready → Start Learning → Studyback Workspace
```
```
My Materials → Material Detail → Start Study Session → Study Session Configuration → Studyback Workspace
```

### 7.1 Dual Interaction Model

The workspace combines two types of interfaces in the same space, so users do not feel like they are switching applications:

**Conversational Interface** (used by Teach Me & Review Weak Topics)
- Explanation
- Follow-up questions
- Socratic questioning

**Structured Interface** (used by Quiz Me)
- Multiple choice
- True/False
- Short answer
- Quiz results

Quiz specifically uses a structured UI, not ordinary chat bubbles — but it still remains within the same workspace.

### 7.2 Learning Modes

**Teach Me**
Uses the conversational interface. The AI explains the topic/concept based on the selected material, can simplify explanations, give examples, and answer follow-up questions.

Example:
```
User: "Teach me polymorphism."
Studyback: [explains polymorphism based on the user's material]

User can continue with:
- Explain simpler
- Give example
- Quiz me
```

**Quiz Me**
Uses the structured quiz interface.

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

After the user answers, the AI evaluates the answer, determines correct/incorrect, provides feedback, stores the quiz result, and updates topic mastery.

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

The quiz result is shown as a summary with clear actions — the workspace does **not** switch views automatically/aggressively after the quiz ends.

**Review Weak Topics**
Uses the learning state generated from quizzes and previous interactions. Topics/subtopics with low mastery are marked **Needs Review**.

```
Polymorphism
42% Mastery
Needs Review
[Review]
```

When the user presses Review:
- Studyback automatically focuses the AI Teacher on that topic/subtopic
- The AI explains it again with a different approach, gives examples, and can ask the user to re-explain the concept
- The AI gives a mini-question or re-test
- The learning state is updated based on the review results

Review does not open a new page — the user stays in the Studyback Workspace.

**Guided Study Session (Primary Mode)**
The main mode that combines the entire learning loop in one workspace:

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

The AI determines when the user needs to move from one stage to the next based on interaction results — not a static sequence, but one that follows the learning state adaptively.

### 7.3 Workspace Layout

The workspace uses two main areas:

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

Example sidebar:

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

### 7.4 Sidebar as Learning Map

The sidebar is not just navigation — it also acts as a visual map that shows the user's understanding of each topic/subtopic, so users can immediately see what they have mastered, are currently studying, need to review, and have never studied.

Topics/subtopics use a collapsible/accordion interaction, compact by default:

```
Collapsed:
▸ Polymorphism

Expanded:
▾ Polymorphism
  █████░░░░░ 42%
  Needs Review
```

The progress bar is only shown when the subtopic is expanded.

**Learning status:**

| Symbol | Status |
|---|---|
| ✓ | Mastered |
| ◐ | In Progress |
| ⚠ | Needs Review |
| ○ | Not Started |

If the user taps a subtopic with the Needs Review status, Studyback immediately focuses the AI Teacher on that topic/subtopic:

```
⚠ Polymorphism
  ↓ user clicks
Studyback focuses AI Teacher on Polymorphism
  ↓
Review / Teach interaction begins
```

Thus, the sidebar also becomes a shortcut to personalized review.

---

## 8. Adaptive Learning Loop & Learning State

### 8.1 Learning Loop

```
Learn
  ↓ AI explains the material/topic
Test
  ↓ AI generates quiz
Evaluate
  ↓ AI evaluates answers & gives feedback
Weak Topic Detection
  ↓ System identifies weak topics
Review
  ↓ Studyback recommends material/topic to re-learn
Learning State updated
```

Quiz/interaction results directly affect the Learning State — this loop must not appear as a static workflow:

```
Quiz → Calculate Score → Update Topic/Subtopic Mastery → Determine Status
  → Needs Review / In Progress / Mastered → Recommendation / Review
```

### 8.2 Mastery System

Mastery is stored primarily at the **Subtopic** level, calculated with a simple and deterministic approach (not complex knowledge tracing or machine learning):

| Score | Status |
|---|---|
| < 60% | Needs Review |
| 60% – 79% | In Progress |
| ≥ 80% | Mastered |

### 8.3 Learning State (Example)

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

The Learning State is stored so that the learning process can continue later — users can return to the same material and continue based on their previous progress.

---

## 9. AI Output Principles & Context Boundary

### 9.1 Structured AI Output

For parts that require structured data, the AI generates structured JSON that is then used by the application logic. Examples of areas that use this approach:

- Topic extraction
- Quiz generation
- Answer evaluation
- Learning state-related output

This is described at the product architecture level only, not as a full technical implementation document.

### 9.2 Context Boundary (RAG)

Studyback uses the user's material as the primary learning source — the AI must answer based on the material the user selected/uploaded, with a brief flow:

```
Material → Chunking → Retrieval → Relevant Context → AI Response
```

This is kept simple; it is not expanded into a complex RAG system.

---

## 10. Navigation Structure

The navbar stays simple and does **not** have a "Study" menu — the Studyback Workspace is not a main navbar destination, but is accessed through Home or My Materials.

**Navbar:**
- Home
- My Materials
- Profile

**Paths to the Studyback Workspace:**

```
Home → Upload Material → Material Ready → Start Learning → Studyback Workspace
Home → Recent Materials → Material Detail → Start Study Session → Studyback Workspace
My Materials → Material Detail → Start Study Session → Studyback Workspace
```

**Upload path from My Materials:**

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