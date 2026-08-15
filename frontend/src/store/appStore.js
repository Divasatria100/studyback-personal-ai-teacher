import { create } from 'zustand';
import { authService } from '../services/api';

export const useAppStore = create((set, get) => ({
  user: null,
  isAuthenticated: false,
  toasts: [],
  isLoadingUser: true,

  // Toast actions
  addToast: (message, type = 'success') => {
    const id = Date.now();
    set((state) => ({
      toasts: [...state.toasts, { id, message, type }]
    }));
  },

  removeToast: (id) => {
    set((state) => ({
      toasts: state.toasts.filter((t) => t.id !== id)
    }));
  },

  // Session expired / token invalid -> clear state (SessionWatcher redirects)
  handleUnauthorized: () => {
    set({ user: null, isAuthenticated: false });
    get().addToast('Your session has expired. Please sign in again.', 'error');
  },

  // Auth actions
  checkAuth: async () => {
    set({ isLoadingUser: true });
    try {
      const user = await authService.me();
      set({ user, isAuthenticated: true, isLoadingUser: false });
    } catch (err) {
      set({ user: null, isAuthenticated: false, isLoadingUser: false });
    }
  },

  login: async (email, password) => {
    try {
      const data = await authService.login(email, password);
      set({ user: data.user, isAuthenticated: true });
      get().addToast('Welcome back, ' + data.user.name + '!', 'success');
      return data;
    } catch (err) {
      get().addToast(err.message || 'Login failed', 'error');
      throw err;
    }
  },

  register: async (name, email, password) => {
    try {
      const data = await authService.register(name, email, password);
      set({ user: data.user, isAuthenticated: true });
      get().addToast('Account created! Welcome, ' + data.user.name + '!', 'success');
      return data;
    } catch (err) {
      get().addToast(err.message || 'Registration failed', 'error');
      throw err;
    }
  },

  logout: async () => {
    try {
      await authService.logout();
      set({ user: null, isAuthenticated: false });
      get().addToast('Logged out successfully', 'info');
    } catch (err) {
      get().addToast(err.message || 'Logout failed', 'error');
    }
  }
}));