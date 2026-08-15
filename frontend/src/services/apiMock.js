// @deprecated — DEV/TEST ONLY. The production application flow now uses the
// real Laravel backend through services/api.js (axios + Bearer token). This
// file is retained for local experimentation/mock scenarios but is NOT
// imported anywhere in src and therefore never runs in the app.
//
// Stateful client-side Mock API Database stored in localStorage
const LATENCY = 300; // ms

const sleep = (ms) => new Promise((resolve) => setTimeout(resolve, ms));

const getStorage = (key, defaultVal) => {
  const data = localStorage.getItem(`studyback_${key}`);
  return data ? JSON.parse(data) : defaultVal;
};

const setStorage = (key, val) => {
  localStorage.setItem(`studyback_${key}`, JSON.stringify(val));
};

// Initialize Mock Database
const initDb = () => {
  if (!localStorage.getItem('studyback_initialized')) {
    setStorage('users', [
      { id: 1, name: 'Demo', email: 'demo@gmail', password: 'password' }
    ]);
    setStorage('currentUser', { id: 1, name: 'Demo', email: 'demo@gmail' });
    setStorage('token', 'mock_token_demo');

    // Default initial material
    const defaultMaterial = {
      id: 12,
      title: 'Object Oriented Programming',
      description: 'Lecture notes on classes, objects, inheritance, polymorphism, and encapsulation.',
      original_filename: 'oop-notes.pdf',
      file_size_bytes: 842104,
      status: 'ready',
      topics_count: 3,
      overall_mastery: 45,
      created_at: '2026-08-14T09:00:00Z'
    };

    setStorage('materials', [defaultMaterial]);

    const defaultTopics = {
      material_id: 12,
      overall_mastery: 45,
      topics: [
        {
          id: 101,
          name: 'Classes & Objects',
          description: 'The blueprint of objects and instance creation.',
          order_index: 1,
          subtopics: [
            { id: 1040, name: 'Instantiation', description: 'Creating instances using constructor functions.', order_index: 1, mastery_score: 80, status: 'mastered' },
            { id: 1041, name: 'Object References', description: 'Understanding memory allocation and object pointers.', order_index: 2, mastery_score: 50, status: 'in_progress' }
          ]
        },
        {
          id: 102,
          name: 'Inheritance & Polymorphism',
          description: 'How classes derive behavior and dynamically override implementations.',
          order_index: 2,
          subtopics: [
            { id: 1042, name: 'Polymorphism', description: 'Same interface, different implementations.', order_index: 1, mastery_score: 42, status: 'needs_review' },
            { id: 1043, name: 'Method Overriding', description: 'Redefining parent methods in child classes.', order_index: 2, mastery_score: 20, status: 'needs_review' }
          ]
        },
        {
          id: 103,
          name: 'Encapsulation & Abstraction',
          description: 'Hiding internal states and exposing abstract interfaces.',
          order_index: 3,
          subtopics: [
            { id: 1044, name: 'Data Hiding', description: 'Private and protected access modifiers.', order_index: 1, mastery_score: 85, status: 'mastered' }
          ]
        }
      ]
    };

    setStorage('topics_12', defaultTopics);
    setStorage('study_sessions', []);
    setStorage('quizzes', []);
    setStorage('quiz_answers', []);
    localStorage.setItem('studyback_initialized', 'true');
  }
};

initDb();

// AUTH SERVICE
export const authService = {
  register: async (name, email, password) => {
    await sleep(LATENCY);
    const users = getStorage('users', []);
    if (users.find(u => u.email === email)) {
      throw { status: 422, message: 'Email already registered', errors: { email: ['Email already taken'] } };
    }
    const newUser = { id: Date.now(), name, email, password };
    users.push(newUser);
    setStorage('users', users);

    const token = `mock_token_${newUser.id}_${Math.random().toString(36).substr(2)}`;
    setStorage('token', token);
    setStorage('currentUser', { id: newUser.id, name: newUser.name, email: newUser.email });
    return { user: { id: newUser.id, name: newUser.name, email: newUser.email }, token };
  },

  login: async (email, password) => {
    await sleep(LATENCY);
    const users = getStorage('users', []);
    const user = users.find(u => u.email === email && u.password === password);
    if (!user) {
      throw { status: 401, message: 'Invalid credentials. Please verify your email and password.' };
    }
    const token = `mock_token_${user.id}_${Math.random().toString(36).substr(2)}`;
    setStorage('token', token);
    setStorage('currentUser', { id: user.id, name: user.name, email: user.email });
    return { user: { id: user.id, name: user.name, email: user.email }, token };
  },

  logout: async () => {
    await sleep(LATENCY);
    setStorage('token', null);
    setStorage('currentUser', null);
    return { message: 'Logged out successfully.' };
  },

  me: async () => {
    await sleep(LATENCY);
    const currentUser = getStorage('currentUser', null);
    const token = getStorage('token', null);
    if (!token || !currentUser) {
      throw { status: 401, message: 'Unauthenticated.' };
    }
    return { ...currentUser, created_at: '2026-01-10T08:00:00Z' };
  }
};

// MATERIALS SERVICE
export const materialService = {
  upload: async (file, title, description, onProgress) => {
    // Simulate steps in uploading & processing
    const totalSteps = 5;
    const steps = [
      'Uploading…',
      'Extracting Content…',
      'Understanding Material…',
      'Identifying Topics…',
      'Material Ready ✓'
    ];

    for (let i = 0; i < totalSteps; i++) {
      if (onProgress) {
        onProgress(steps[i], Math.round(((i + 1) / totalSteps) * 100));
      }
      await sleep(800); // 800ms per step to show animations/states
    }

    const materials = getStorage('materials', []);
    const newId = Date.now();
    const newMaterial = {
      id: newId,
      title: title || file.name.replace(/\.[^/.]+$/, ""),
      description: description || 'Simulated study guide',
      original_filename: file.name,
      file_size_bytes: file.size || 1048576,
      status: 'ready',
      topics_count: 2,
      overall_mastery: 0,
      created_at: new Date().toISOString()
    };

    materials.unshift(newMaterial);
    setStorage('materials', materials);

    // Generate mock topics and subtopics for this material
    const mockTopics = {
      material_id: newId,
      overall_mastery: 0,
      topics: [
        {
          id: newId + 100,
          name: 'Core Concepts',
          description: 'Basic fundamentals found in the material.',
          order_index: 1,
          subtopics: [
            { id: newId + 1001, name: 'Key Definitions', description: 'Crucial definitions and core terminology.', order_index: 1, mastery_score: 0, status: 'not_started' },
            { id: newId + 1002, name: 'Practical Applications', description: 'How these concepts function in real-world environments.', order_index: 2, mastery_score: 0, status: 'not_started' }
          ]
        },
        {
          id: newId + 200,
          name: 'Advanced Mechanics',
          description: 'Deep dive into complex operations.',
          order_index: 2,
          subtopics: [
            { id: newId + 2001, name: 'Optimization & Efficiency', description: 'How to optimize usage.', order_index: 1, mastery_score: 0, status: 'not_started' }
          ]
        }
      ]
    };
    setStorage(`topics_${newId}`, mockTopics);

    return newMaterial;
  },

  list: async (search = '', status = '', sort = 'recent') => {
    await sleep(LATENCY);
    let list = getStorage('materials', []);

    if (search) {
      const q = search.toLowerCase();
      list = list.filter(m => m.title.toLowerCase().includes(q) || m.description.toLowerCase().includes(q));
    }

    if (status) {
      list = list.filter(m => m.status === status);
    }

    // Sort
    if (sort === 'recent') {
      list.sort((a, b) => new Date(b.created_at) - new Date(a.created_at));
    } else if (sort === 'title') {
      list.sort((a, b) => a.title.localeCompare(b.title));
    }

    return { data: list, meta: { current_page: 1, per_page: 20, total: list.length } };
  },

  get: async (id) => {
    await sleep(LATENCY);
    const list = getStorage('materials', []);
    const material = list.find(m => m.id === Number(id));
    if (!material) {
      throw { status: 404, message: 'Material not found.' };
    }
    return material;
  },

  download: async (id) => {
    await sleep(LATENCY);
    const material = await materialService.get(id);
    // Simulate blob streaming
    const content = 'Mock PDF binary content for file: ' + material.original_filename;
    const blob = new Blob([content], { type: 'application/pdf' });
    const url = window.URL.createObjectURL(blob);
    const a = document.createElement('a');
    a.href = url;
    a.download = material.original_filename;
    document.body.appendChild(a);
    a.click();
    document.body.removeChild(a);
    window.URL.revokeObjectURL(url);
    return true;
  },

  getTopics: async (materialId) => {
    await sleep(LATENCY);
    const data = getStorage(`topics_${materialId}`, null);
    if (!data) {
      throw { status: 404, message: 'Topics not found for this material.' };
    }

    // Recalculate overall mastery dynamically
    let totalScore = 0;
    let totalSubtopics = 0;
    data.topics.forEach(t => {
      t.subtopics.forEach(s => {
        totalScore += s.mastery_score;
        totalSubtopics++;
      });
    });
    const overallMastery = totalSubtopics > 0 ? Math.round(totalScore / totalSubtopics) : 0;

    data.overall_mastery = overallMastery;

    // Sync back to material model
    const materials = getStorage('materials', []);
    const matIndex = materials.findIndex(m => m.id === Number(materialId));
    if (matIndex !== -1) {
      materials[matIndex].overall_mastery = overallMastery;
      setStorage('materials', materials);
    }

    return data;
  }
};

// STUDY SESSIONS SERVICE
export const studySessionService = {
  create: async (materialId, mode, difficulty, topicIds) => {
    await sleep(LATENCY);
    const sessions = getStorage('study_sessions', []);
    const newSession = {
      id: Date.now(),
      material_id: Number(materialId),
      mode,
      difficulty: difficulty || 'medium',
      status: 'active',
      topic_ids: topicIds || [],
      started_at: new Date().toISOString(),
      ended_at: null
    };
    sessions.push(newSession);
    setStorage('study_sessions', sessions);
    return newSession;
  },

  get: async (id) => {
    await sleep(LATENCY);
    const sessions = getStorage('study_sessions', []);
    const session = sessions.find(s => s.id === Number(id));
    if (!session) {
      throw { status: 404, message: 'Study Session not found.' };
    }
    return session;
  },

  complete: async (id) => {
    await sleep(LATENCY);
    const sessions = getStorage('study_sessions', []);
    const idx = sessions.findIndex(s => s.id === Number(id));
    if (idx === -1) {
      throw { status: 404, message: 'Study Session not found.' };
    }
    if (sessions[idx].status === 'completed') {
      throw { status: 409, message: 'Session is already completed.' };
    }
    sessions[idx].status = 'completed';
    sessions[idx].ended_at = new Date().toISOString();
    setStorage('study_sessions', sessions);
    return sessions[idx];
  },

  getExplanation: async (sessionId, subtopicId, intent, message) => {
    await sleep(800); // AI generation latency
    const session = await studySessionService.get(sessionId);
    const topicsData = await materialService.getTopics(session.material_id);

    let subtopicName = 'Selected Concept';
    topicsData.topics.forEach(t => {
      const sub = t.subtopics.find(s => s.id === Number(subtopicId));
      if (sub) subtopicName = sub.name;
    });

    let explanation = '';
    if (intent === 'simplify') {
      explanation = `Simplifying **${subtopicName}**:\n\nImagine you are building a simple LEGO house. Instead of building every door, brick, and window from scratch each time, you use pre-made blocks. That is the essence here: organizing details into easily understandable chunks.\n\nFor example, instead of worrying about minor details, you can conceptualize it as a single block that performs one task correctly.`;
    } else if (intent === 'example') {
      explanation = `Here is a real-world example of **${subtopicName}**:\n\nThink about driving a car. You interact with the accelerator, the brake, and the steering wheel. You don't need to know how the internal combustion engine works, or how the mechanical differential distributes power to the wheels. \n\nThe controls (pedals, wheel) are the public interface, and the mechanical engine internals are hidden away (encapsulated) from you.`;
    } else if (intent === 'review') {
      explanation = `Let's review **${subtopicName}** which needs review:\n\nThe key reason this concept gets tricky is matching the definition with the exact application constraints. Remember:\n\n1. It resolves issues by applying a clean hierarchy.\n2. It acts dynamically at runtime.\n3. Make sure to double check option attributes before submitting.`;
    } else {
      explanation = `### Understanding ${subtopicName}\n\n**${subtopicName}** is a core pillar in this material. It allows you to model real-world concepts directly in code or logic.\n\n- **Purpose**: Establishes clean hierarchies, promotes modularity, and makes systems extensible.\n- **Implementation**: Typically defined using key attributes, methods, or structural blueprints.\n\nDo you have a specific question about how this is implemented, or would you like to see a code example?`;
    }

    if (message) {
      explanation = `Regarding your question: "${message}"\n\nThat's an excellent point! In the context of **${subtopicName}**, we handle it exactly by defining boundary rules. This prevents components from overriding properties unexpectedly and maintains correct program flow.`;
    }

    return {
      subtopic_id: Number(subtopicId),
      explanation
    };
  }
};

// QUIZ SERVICE
const MOCK_QUESTIONS = {
  1040: [ // Instantiation
    { question_text: "Which keyword is typically used in Java/JS to instantiate a new object from a class?", options: ["new", "create", "make", "instantiate"], correct_answer: "new" },
    { question_text: "What function gets called automatically during object instantiation?", options: ["Constructor", "Initializer", "Destructor", "Main"], correct_answer: "Constructor" }
  ],
  1041: [ // Object References
    { question_text: "What happens when you assign one object variable to another in Java (e.g., objA = objB)?", options: ["Both reference the same object", "A copy of objB is created", "Compilation error", "A new pointer is allocated with copied data"], correct_answer: "Both reference the same object" },
    { question_text: "Where are instantiated objects stored in memory?", options: ["Heap memory", "Stack memory", "Static segment", "CPU Registers"], correct_answer: "Heap memory" }
  ],
  1042: [ // Polymorphism
    { question_text: "Which statement best explains polymorphism?", options: ["Allowing a single interface to represent different underlying forms", "Hiding internal implementation details", "Creating a new class from an existing class", "Restricting access to class variables"], correct_answer: "Allowing a single interface to represent different underlying forms" },
    { question_text: "What is an example of runtime polymorphism?", options: ["Method Overriding", "Method Overloading", "Operator Overloading", "Constructors"], correct_answer: "Method Overriding" }
  ],
  1043: [ // Method Overriding
    { question_text: "How does Method Overriding differ from Method Overloading?", options: ["Overriding uses the same signature in sub-classes; Overloading uses different parameters", "Overriding happens at compile-time", "Overriding does not require inheritance", "They are identical concepts"], correct_answer: "Overriding uses the same signature in sub-classes; Overloading uses different parameters" }
  ],
  1044: [ // Data Hiding
    { question_text: "Which access modifier provides the maximum level of data hiding?", options: ["private", "public", "protected", "default"], correct_answer: "private" }
  ]
};

export const quizService = {
  create: async (sessionId, topicId, subtopicId, difficulty, questionCount = 3) => {
    await sleep(800); // generation latency
    const quizzes = getStorage('quizzes', []);

    // Pick questions based on subtopicId or topicId
    let pools = [];
    if (subtopicId) {
      pools = MOCK_QUESTIONS[Number(subtopicId)] || [];
    } else {
      // Get all subtopic ids for the topic
      const session = await studySessionService.get(sessionId);
      const topicsData = await materialService.getTopics(session.material_id);
      const topic = topicsData.topics.find(t => t.id === Number(topicId));
      if (topic) {
        topic.subtopics.forEach(sub => {
          if (MOCK_QUESTIONS[sub.id]) {
            pools = pools.concat(MOCK_QUESTIONS[sub.id].map(q => ({ ...q, subtopic_id: sub.id })));
          }
        });
      }
    }

    if (pools.length === 0) {
      // Fallback pool
      pools = [
        { question_text: "Which concept allows wrapping variables and methods into a single class?", options: ["Encapsulation", "Polymorphism", "Inheritance", "Abstraction"], correct_answer: "Encapsulation", subtopic_id: subtopicId || 1040 },
        { question_text: "Which of the following defines a template or blueprint for creating objects?", options: ["Class", "Method", "Variable", "Package"], correct_answer: "Class", subtopic_id: subtopicId || 1041 }
      ];
    }

    // Limit and format questions
    const limit = Math.min(questionCount, pools.length);
    const selectedPool = pools.slice(0, limit);

    const quizId = Date.now();
    const quizQuestions = selectedPool.map((q, idx) => ({
      id: quizId + idx + 1,
      subtopic_id: q.subtopic_id || Number(subtopicId) || 1040,
      question_type: 'multiple_choice',
      question_text: q.question_text,
      options: q.options,
      correct_answer: q.correct_answer, // Hidden from client, evaluated server-side (mocked)
      order_index: idx
    }));

    // Save questions in mock DB (with correct_answer)
    setStorage(`quiz_questions_raw_${quizId}`, quizQuestions);

    const newQuiz = {
      id: quizId,
      study_session_id: Number(sessionId),
      topic_id: Number(topicId),
      subtopic_id: subtopicId ? Number(subtopicId) : null,
      difficulty: difficulty || 'medium',
      status: 'in_progress',
      total_questions: quizQuestions.length,
      correct_count: 0,
      score: null,
      questions: quizQuestions.map(q => ({
        id: q.id,
        subtopic_id: q.subtopic_id,
        question_type: q.question_type,
        question_text: q.question_text,
        options: q.options,
        order_index: q.order_index,
        answered: false
      }))
    };

    quizzes.push(newQuiz);
    setStorage('quizzes', quizzes);

    return newQuiz;
  },

  get: async (id) => {
    await sleep(LATENCY);
    const quizzes = getStorage('quizzes', []);
    const quiz = quizzes.find(q => q.id === Number(id));
    if (!quiz) {
      throw { status: 404, message: 'Quiz not found.' };
    }
    return quiz;
  },

  submitAnswer: async (quizId, questionId, submittedAnswer) => {
    await sleep(500); // AI grading latency

    const quizzes = getStorage('quizzes', []);
    const qIndex = quizzes.findIndex(q => q.id === Number(quizId));
    if (qIndex === -1) {
      throw { status: 404, message: 'Quiz not found.' };
    }

    const quiz = quizzes[qIndex];
    if (quiz.status === 'completed') {
      throw { status: 409, message: 'Quiz is already completed.' };
    }

    const rawQuestions = getStorage(`quiz_questions_raw_${quizId}`, []);
    const rawQ = rawQuestions.find(q => q.id === Number(questionId));
    if (!rawQ) {
      throw { status: 404, message: 'Question not found.' };
    }

    // Check if already answered
    const answers = getStorage('quiz_answers', []);
    const existing = answers.find(a => a.quiz_question_id === Number(questionId));
    if (existing) {
      throw { status: 409, message: 'Question already answered.' };
    }

    const isCorrect = rawQ.correct_answer === submittedAnswer;
    const feedback = isCorrect
      ? `Correct! The concept is perfectly applied. ${submittedAnswer} is indeed the right choice.`
      : `Incorrect. The correct answer was ${rawQ.correct_answer}. Let's review this concept to understand why.`;

    // Persist answer
    const newAnswer = {
      id: Date.now(),
      quiz_question_id: Number(questionId),
      submitted_answer: submittedAnswer,
      is_correct: isCorrect,
      ai_feedback: feedback,
      subtopic_id: rawQ.subtopic_id,
      answered_at: new Date().toISOString()
    };
    answers.push(newAnswer);
    setStorage('quiz_answers', answers);

    // Update subtopic mastery score
    // Mastery score formula: cumulative average of is_correct (0 or 100) across ALL answers for this subtopic
    const subtopicAnswers = answers.filter(a => a.subtopic_id === rawQ.subtopic_id);
    const correctCount = subtopicAnswers.filter(a => a.is_correct).length;
    const masteryScore = Math.round((correctCount / subtopicAnswers.length) * 100);

    // Status ranges: <60% needs_review, 60-79% in_progress, >=80% mastered
    let status = 'not_started';
    if (masteryScore < 60) status = 'needs_review';
    else if (masteryScore < 80) status = 'in_progress';
    else status = 'mastered';

    // Find session to locate material ID
    const sessions = getStorage('study_sessions', []);
    const session = sessions.find(s => s.id === quiz.study_session_id);
    let updatedSubtopic = null;

    if (session) {
      const topicsData = await materialService.getTopics(session.material_id);
      topicsData.topics.forEach(t => {
        const sub = t.subtopics.find(s => s.id === rawQ.subtopic_id);
        if (sub) {
          sub.mastery_score = masteryScore;
          sub.status = status;
          updatedSubtopic = sub;
        }
      });
      setStorage(`topics_${session.material_id}`, topicsData);

      // Update overall mastery in material list dynamically
      let totalScore = 0;
      let totalSubs = 0;
      topicsData.topics.forEach(t => {
        t.subtopics.forEach(s => {
          totalScore += s.mastery_score;
          totalSubs++;
        });
      });
      const overallMastery = Math.round(totalScore / totalSubs);
      const materials = getStorage('materials', []);
      const matIndex = materials.findIndex(m => m.id === session.material_id);
      if (matIndex !== -1) {
        materials[matIndex].overall_mastery = overallMastery;
        setStorage('materials', materials);
      }
    }

    // Update Quiz status in lists
    const qInQuiz = quiz.questions.find(q => q.id === Number(questionId));
    if (qInQuiz) {
      qInQuiz.answered = true;
      qInQuiz.is_correct = isCorrect;
    }

    // Check if quiz is fully completed
    const unanswered = quiz.questions.filter(q => !q.answered);
    const isQuizComplete = unanswered.length === 0;

    if (isQuizComplete) {
      // Recalculate quiz scores
      const quizQuestionIds = quiz.questions.map(q => q.id);
      const quizCompletedAnswers = answers.filter(a => quizQuestionIds.includes(a.quiz_question_id));
      const quizCorrects = quizCompletedAnswers.filter(a => a.is_correct).length;

      quiz.status = 'completed';
      quiz.correct_count = quizCorrects;
      quiz.score = Math.round((quizCorrects / quiz.total_questions) * 100);
      quiz.completed_at = new Date().toISOString();
    }

    quizzes[qIndex] = quiz;
    setStorage('quizzes', quizzes);

    return {
      quiz_question_id: Number(questionId),
      submitted_answer: submittedAnswer,
      is_correct: isCorrect,
      ai_feedback: feedback,
      quiz_status: quiz.status,
      quiz_result: isQuizComplete ? { correct_count: quiz.correct_count, total_questions: quiz.total_questions, score: quiz.score } : null,
      subtopic: updatedSubtopic || { id: rawQ.subtopic_id, mastery_score: masteryScore, status }
    };
  }
};
