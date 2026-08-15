import React from 'react';
import Navbar from './Navbar';
import { Toast } from './Shared';
import { useAppStore } from '../store/appStore';

export default function AppLayout({ children }) {
  const { toasts, removeToast } = useAppStore();

  return (
    <div className="min-h-[100dvh] flex flex-col bg-slate-200">
      {/* Top Header */}
      <Navbar />

      {/* Main Container */}
      <main className="flex-1 w-full max-w-[1280px] mx-auto px-6 py-8 animate-in fade-in duration-300">
        {children}
      </main>

      {/* Toast Notification Container */}
      <div className="fixed bottom-6 right-6 z-[500] flex flex-col gap-2 w-full max-w-sm pointer-events-none">
        {toasts.map((t) => (
          <Toast 
            key={t.id} 
            message={t.message} 
            type={t.type} 
            onClose={() => removeToast(t.id)} 
          />
        ))}
      </div>
    </div>
  );
}
