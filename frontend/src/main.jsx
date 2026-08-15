import React, { useEffect } from 'react';
import ReactDOM from 'react-dom/client';
import { BrowserRouter, Routes, Route, Navigate } from 'react-router-dom';
import AppLayout from './components/AppLayout';
import Home from './pages/Home';
import MyMaterials from './pages/MyMaterials';
import MaterialDetail from './pages/MaterialDetail';
import Workspace from './pages/Workspace';
import Login from './pages/Login';
import { useAppStore } from './store/appStore';
import './index.css';

// Protected Route Wrapper
function ProtectedRoute({ children }) {
  const { isAuthenticated, isLoadingUser } = useAppStore();

  if (isLoadingUser) {
    return (
      <div className="min-h-screen flex items-center justify-center bg-slate-200">
        <div className="animate-spin rounded-full h-8 w-8 border-b-2 border-slate-900" />
      </div>
    );
  }

  if (!isAuthenticated) {
    return <Navigate to="/login" replace />;
  }

  return children;
}

// Main App component
function App() {
  const checkAuth = useAppStore((state) => state.checkAuth);

  useEffect(() => {
    checkAuth();
  }, [checkAuth]);

  return (
    <BrowserRouter>
      <Routes>
        {/* Public Routes */}
        <Route 
          path="/login" 
          element={
            <AppLayout>
              <Login />
            </AppLayout>
          } 
        />
        <Route 
          path="/register" 
          element={
            <AppLayout>
              <Login isRegister />
            </AppLayout>
          } 
        />

        {/* Protected Routes */}
        <Route 
          path="/" 
          element={
            <ProtectedRoute>
              <AppLayout>
                <Home />
              </AppLayout>
            </ProtectedRoute>
          } 
        />
        <Route 
          path="/materials" 
          element={
            <ProtectedRoute>
              <AppLayout>
                <MyMaterials />
              </AppLayout>
            </ProtectedRoute>
          } 
        />
        <Route 
          path="/materials/:id" 
          element={
            <ProtectedRoute>
              <AppLayout>
                <MaterialDetail />
              </AppLayout>
            </ProtectedRoute>
          } 
        />
        <Route 
          path="/workspace/:sessionId" 
          element={
            <ProtectedRoute>
              <AppLayout>
                <Workspace />
              </AppLayout>
            </ProtectedRoute>
          } 
        />

        {/* Catch-all redirect */}
        <Route path="*" element={<Navigate to="/" replace />} />
      </Routes>
    </BrowserRouter>
  );
}

ReactDOM.createRoot(document.getElementById('root')).render(
  <React.StrictMode>
    <App />
  </React.StrictMode>,
);
