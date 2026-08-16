import axios from 'axios';

// ---------------------------------------------------------------------------
// Centralized HTTP client for the Laravel backend API (API Design §5, §21).
// - Base URL comes from VITE_API_URL (empty => same-origin "/api" via Nginx
//   reverse proxy, see docker/nginx/default.conf).
// - Bearer token is read from localStorage and attached on every request.
// - Errors are normalized to { status, message, errors? } so callers keep the
//   same contract the mock previously used.
// ---------------------------------------------------------------------------

const TOKEN_KEY = 'studyback_token';

const tokenStore = {
  get: () => {
    try {
      return localStorage.getItem(TOKEN_KEY);
    } catch {
      return null;
    }
  },
  set: (token) => {
    try {
      localStorage.setItem(TOKEN_KEY, token);
    } catch {
      /* storage unavailable */
    }
  },
  remove: () => {
    try {
      localStorage.removeItem(TOKEN_KEY);
    } catch {
      /* storage unavailable */
    }
  },
};

const baseURL = (() => {
  const url = (import.meta.env.VITE_API_URL || '').replace(/\/+$/, '');
  return url ? `${url}/api` : '/api';
})();

const client = axios.create({
  baseURL,
  timeout: 120000,
  headers: {
    Accept: 'application/json',
  },
});

client.interceptors.request.use((config) => {
  const token = tokenStore.get();
  if (token && !config.headers.Authorization) {
    config.headers.Authorization = `Bearer ${token}`;
  }
  return config;
});

function normalizeError(error) {
  if (error?.response) {
    const { status, data } = error.response;

    let message = data?.message;
    // Material processing failure returns the MaterialResource top-level (422)
    // with no `message` field — surface a clear message for the uploader UI.
    if (!message && data && typeof data === 'object' && data.status === 'failed') {
      message = 'Material processing failed. Please try a different file.';
    }

    return {
      status,
      message: message || 'Request failed',
      errors: data?.errors || undefined,
      data: data || undefined,
    };
  }

  return {
    status: 0,
    message: error?.message || 'Network error. Please check your connection and try again.',
    errors: undefined,
  };
}

client.interceptors.response.use(
  (response) => response,
  (error) => {
    const normalized = normalizeError(error);

    // 401 on an authenticated request => token missing/revoked/expired.
    // Clear it and let the app redirect to Login (see SessionWatcher).
    if (normalized.status === 401 && error.config?.headers?.Authorization) {
      tokenStore.remove();
      if (typeof window !== 'undefined') {
        window.dispatchEvent(new Event('studyback:unauthorized'));
      }
    }

    return Promise.reject(normalized);
  }
);

// ---------------------------------------------------------------------------
// AUTH SERVICE
// ---------------------------------------------------------------------------
export const authService = {
  register: async (name, email, password) => {
    const { data } = await client.post('/auth/register', {
      name,
      email,
      password,
      password_confirmation: password,
    });
    tokenStore.set(data.token);
    return data; // { user: { id, name, email }, token }
  },

  login: async (email, password) => {
    const { data } = await client.post('/auth/login', { email, password });
    tokenStore.set(data.token);
    return data; // { user: { id, name, email }, token }
  },

  logout: async () => {
    try {
      await client.post('/auth/logout');
    } finally {
      tokenStore.remove();
    }
    return { message: 'Logged out successfully.' };
  },

  me: async () => {
    const { data } = await client.get('/auth/me');
    return data; // { id, name, email, created_at }
  },
};

// ---------------------------------------------------------------------------
// MATERIALS SERVICE
// ---------------------------------------------------------------------------
export const materialService = {
  upload: async (file, title, description, onProgress) => {
    const formData = new FormData();
    formData.append('file', file);
    if (title) formData.append('title', title);
    if (description) formData.append('description', description);

    const { data } = await client.post('/materials', formData, {
      headers: { 'Content-Type': 'multipart/form-data' },
      onUploadProgress: (e) => {
        if (!onProgress) return;
        const percent = e.total ? Math.round((e.loaded / e.total) * 100) : 0;
        onProgress(
          percent >= 100 ? 'Processing material…' : 'Uploading…',
          Math.min(percent, 100)
        );
      },
    });

    return data; // MaterialResource (status: ready | failed)
  },

  list: async (search = '', status = '', sort = 'recent') => {
    const { data } = await client.get('/materials', {
      params: {
        search: search || undefined,
        status: status || undefined,
        sort: sort || 'recent',
      },
    });
    return data; // { data: MaterialResource[], meta: {...} }
  },

  get: async (id) => {
    const { data } = await client.get(`/materials/${id}`);
    return data; // MaterialResource (withDetail)
  },

  download: async (id) => {
    const { data: blob, headers } = await client.get(`/materials/${id}/download`, {
      responseType: 'blob',
    });

    const disposition = headers['content-disposition'] || '';
    const match = disposition.match(/filename\*?=(?:UTF-8'')?"?([^";]+)"?/i);
    const filename = match
      ? decodeURIComponent(match[1])
      : `material-${id}.pdf`;

    const blobUrl = window.URL.createObjectURL(blob);
    const link = document.createElement('a');
    link.href = blobUrl;
    link.download = filename;
    document.body.appendChild(link);
    link.click();
    document.body.removeChild(link);
    window.URL.revokeObjectURL(blobUrl);

    return true;
  },

  getTopics: async (materialId) => {
    const { data } = await client.get(`/materials/${materialId}/topics`);
    return data; // { material_id, overall_mastery, topics: TopicTreeResource[] }
  },

  delete: async (id) => {
    await client.delete(`/materials/${id}`);
    return true; // 204 No Content on success
  },
};

// ---------------------------------------------------------------------------
// STUDY SESSION SERVICE
// ---------------------------------------------------------------------------
export const studySessionService = {
  create: async (materialId, mode, difficulty, topicIds) => {
    const { data } = await client.post(`/materials/${materialId}/study-sessions`, {
      mode,
      difficulty: difficulty || undefined,
      topic_ids: topicIds || [],
    });
    return data; // StudySessionResource
  },

  get: async (id) => {
    const { data } = await client.get(`/study-sessions/${id}`);
    return data; // StudySessionResource
  },

  complete: async (id) => {
    const { data } = await client.patch(`/study-sessions/${id}/complete`);
    return data; // { id, status, ended_at }
  },

  getExplanation: async (sessionId, subtopicId, intent, message) => {
    const { data } = await client.post(`/study-sessions/${sessionId}/explanations`, {
      subtopic_id: subtopicId,
      intent,
      message: message || undefined,
    });
    return data; // { subtopic_id, explanation }
  },
};

// ---------------------------------------------------------------------------
// QUIZ SERVICE
// ---------------------------------------------------------------------------
export const quizService = {
  create: async (sessionId, topicId, subtopicId, difficulty, questionCount = 3) => {
    const { data } = await client.post(`/study-sessions/${sessionId}/quizzes`, {
      topic_id: topicId,
      subtopic_id: subtopicId || undefined,
      difficulty: difficulty || undefined,
      question_count: questionCount,
    });
    return data; // QuizResource (correct_answer never exposed)
  },

  get: async (id) => {
    const { data } = await client.get(`/quizzes/${id}`);
    return data; // QuizResource
  },

  submitAnswer: async (quizId, questionId, submittedAnswer) => {
    const { data } = await client.post(
      `/quizzes/${quizId}/questions/${questionId}/answer`,
      { submitted_answer: submittedAnswer }
    );
    return data; // { quiz_question_id, submitted_answer, is_correct, ai_feedback, quiz_status, subtopic, quiz_result? }
  },
};