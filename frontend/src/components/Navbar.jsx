import React from 'react';
import { Link, useLocation } from 'react-router-dom';
import { useAppStore } from '../store/appStore';
import { BookOpen, LogOut, User, Home as HomeIcon } from 'lucide-react';
import { Button } from './Shared';

export default function Navbar() {
  const { user, logout, isAuthenticated } = useAppStore();
  const location = useLocation();

  const isActive = (path) => location.pathname === path;

  return (
    <header className="sticky top-0 z-[100] bg-white/70 backdrop-blur-md border-b border-slate-200/50">
      <div className="max-w-[1280px] mx-auto px-6 h-16 flex items-center justify-between">
        {/* Logo */}
        <Link to="/" className="flex items-center gap-2 group">
          <BookOpen className="h-6 w-6 text-slate-900 group-hover:scale-105 transition-transform" />
          <span className="font-display font-bold text-xl tracking-tight text-slate-900">
            Studyback
          </span>
        </Link>

        {/* Navigation links */}
        {isAuthenticated && (
          <nav className="hidden md:flex items-center gap-1 font-mono text-xs font-semibold uppercase tracking-wider">
            <Link 
              to="/" 
              className={`px-4 py-2 transition-all duration-200 ${
                isActive('/') 
                  ? 'text-slate-900 bg-white/60 border border-white/50 shadow-sm' 
                  : 'text-slate-600 hover:text-slate-900'
              }`}
              style={{ borderRadius: 'var(--radius-pill)' }}
            >
              Home
            </Link>
            <Link 
              to="/materials" 
              className={`px-4 py-2 transition-all duration-200 ${
                isActive('/materials') 
                  ? 'text-slate-900 bg-white/60 border border-white/50 shadow-sm' 
                  : 'text-slate-600 hover:text-slate-900'
              }`}
              style={{ borderRadius: 'var(--radius-pill)' }}
            >
              My Materials
            </Link>
          </nav>
        )}

        {/* Profile / Actions */}
        <div className="flex items-center gap-4">
          {isAuthenticated ? (
            <>
              <div className="flex items-center gap-2">
                <div 
                  className="h-8 w-8 bg-slate-900/10 border border-slate-900/20 flex items-center justify-center text-slate-900"
                  style={{ borderRadius: 'var(--radius-control)' }}
                >
                  <User className="h-4 w-4" />
                </div>
                <span className="hidden sm:inline font-mono text-xs font-semibold uppercase tracking-wider text-slate-700">
                  {user?.name}
                </span>
              </div>
              <Button 
                variant="ghost" 
                onClick={logout}
                className="h-9 px-3 text-slate-500 hover:text-red-600 hover:bg-red-50/50"
                style={{ borderRadius: 'var(--radius-control)' }}
              >
                <LogOut className="h-4 w-4" />
              </Button>
            </>
          ) : (
            <div className="flex gap-2">
              <Link to="/login">
                <Button variant="ghost" className="h-9 px-4">Log In</Button>
              </Link>
              <Link to="/register">
                <Button variant="primary" className="h-9 px-4">Register</Button>
              </Link>
            </div>
          )}
        </div>
      </div>

      {/* Mobile nav indicator bar */}
      {isAuthenticated && (
        <div className="md:hidden flex border-t border-slate-200/40 bg-white/30">
          <Link 
            to="/" 
            className={`flex-1 py-3 text-center font-mono text-[10px] font-bold uppercase tracking-wider flex items-center justify-center gap-1.5 ${
              isActive('/') ? 'text-slate-900 bg-white/40 font-black' : 'text-slate-500'
            }`}
          >
            <HomeIcon className="h-3.5 w-3.5" />
            Home
          </Link>
          <Link 
            to="/materials" 
            className={`flex-1 py-3 text-center font-mono text-[10px] font-bold uppercase tracking-wider flex items-center justify-center gap-1.5 ${
              isActive('/materials') ? 'text-slate-900 bg-white/40 font-black' : 'text-slate-500'
            }`}
          >
            <BookOpen className="h-3.5 w-3.5" />
            Materials
          </Link>
        </div>
      )}
    </header>
  );
}
