import React from 'react';
import { Loader2, X } from 'lucide-react';
import { clsx } from 'clsx';
import { twMerge } from 'tailwind-merge';
import { formatPercentage } from '../utils/format';

export function cn(...inputs) {
  return twMerge(clsx(inputs));
}

// Button Component
export const Button = React.forwardRef(({ 
  className, 
  variant = 'primary', 
  loading = false, 
  disabled = false, 
  children, 
  ...props 
}, ref) => {
  return (
    <button
      ref={ref}
      disabled={disabled || loading}
      className={cn(
        "inline-flex items-center justify-center font-mono text-xs font-semibold uppercase tracking-wider h-10 px-6 transition-all duration-200 focus:outline-none focus:ring-2 focus:ring-offset-2 disabled:opacity-50 disabled:cursor-not-allowed select-none active:scale-[0.98]",
        variant === 'primary' && "bg-slate-900 text-white hover:bg-slate-800 border border-slate-900 focus:ring-slate-950",
        variant === 'secondary' && "bg-transparent text-slate-900 border border-slate-900 hover:bg-slate-100 focus:ring-slate-500",
        variant === 'ghost' && "bg-transparent text-slate-700 hover:bg-slate-200/50 focus:ring-slate-500",
        variant === 'danger' && "bg-red-600 text-white hover:bg-red-700 border border-red-600 focus:ring-red-500",
        variant === 'glass' && "bg-white/30 backdrop-blur-md text-slate-900 border border-white/40 hover:bg-white/40 shadow-sm focus:ring-white",
        className
      )}
      style={{ borderRadius: 'var(--radius-control)' }}
      {...props}
    >
      {loading && <Loader2 className="mr-2 h-4 w-4 animate-spin" />}
      {children}
    </button>
  );
});

// Card Component
export const Card = ({ className, glass = false, children, ...props }) => {
  return (
    <div
      className={cn(
        glass ? "glass-card p-6" : "bg-white/80 border border-slate-200/60 p-6 shadow-sm",
        className
      )}
      style={{ borderRadius: glass ? 'var(--radius-glass)' : 'var(--radius-card)' }}
      {...props}
    >
      {children}
    </div>
  );
};

// Input Component
export const Input = React.forwardRef(({ 
  className, 
  label, 
  error, 
  id, 
  ...props 
}, ref) => {
  return (
    <div className="w-full mb-4">
      {label && (
        <label htmlFor={id} className="block font-mono text-xs font-semibold text-slate-800 uppercase tracking-wider mb-2">
          {label}
        </label>
      )}
      <input
        id={id}
        ref={ref}
        className={cn(
          "w-full h-10 px-3 bg-white/70 border border-slate-300 font-body text-base text-slate-900 focus:outline-none focus:border-slate-800 focus:ring-1 focus:ring-slate-800 transition-colors placeholder-slate-400",
          error && "border-red-500 focus:border-red-500 focus:ring-red-500",
          className
        )}
        style={{ borderRadius: 'var(--radius-control)' }}
        {...props}
      />
      {error && (
        <p className="mt-1 font-mono text-xs text-red-600 font-semibold">{error}</p>
      )}
    </div>
  );
});

// Badge Component
export const Badge = ({ variant = 'neutral', children, className }) => {
  return (
    <span
      className={cn(
        "inline-flex items-center font-mono text-[10px] font-semibold uppercase tracking-wider px-2.5 py-0.5 border select-none",
        variant === 'neutral' && "bg-slate-100 text-slate-800 border-slate-200",
        variant === 'success' && "bg-emerald-100 text-emerald-800 border-emerald-200",
        variant === 'warning' && "bg-amber-100 text-amber-800 border-amber-200",
        variant === 'error' && "bg-red-100 text-red-800 border-red-200",
        variant === 'info' && "bg-blue-100 text-blue-800 border-blue-200",
        className
      )}
      style={{ borderRadius: 'var(--radius-pill)' }}
    >
      {children}
    </span>
  );
};

// Modal Component
export const Modal = ({ isOpen, onClose, title, children }) => {
  if (!isOpen) return null;

  return (
    <div className="fixed inset-0 z-[300] flex items-center justify-center p-4">
      {/* Backdrop */}
      <div 
        className="fixed inset-0 bg-slate-900/40 backdrop-blur-sm transition-opacity" 
        onClick={onClose}
      />
      
      {/* Modal Surface */}
      <div 
        className="relative w-full max-w-lg glass-card p-6 overflow-hidden flex flex-col max-h-[85vh] animate-in fade-in zoom-in-95 duration-200"
        style={{ borderRadius: 'var(--radius-glass)' }}
      >
        <div className="flex items-center justify-between border-b border-white/20 pb-3 mb-4">
          <h2 className="text-xl font-bold font-display text-slate-900 leading-none">{title}</h2>
          <button 
            onClick={onClose}
            className="p-1 text-slate-500 hover:text-slate-900 hover:bg-white/30 transition-colors"
            style={{ borderRadius: 'var(--radius-control)' }}
          >
            <X className="h-5 w-5" />
          </button>
        </div>
        <div className="overflow-y-auto pr-1 flex-1">
          {children}
        </div>
      </div>
    </div>
  );
};

// Progress Bar (Linear)
export const ProgressBar = ({ value, className }) => {
  const percent = Math.min(Math.max(value || 0, 0), 100);
  return (
    <div className={cn("w-full bg-slate-200/70 h-2 overflow-hidden", className)} style={{ borderRadius: 'var(--radius-pill)' }}>
      <div 
        className="h-full bg-slate-800 transition-all duration-300"
        style={{ width: `${percent}%` }}
      />
    </div>
  );
};

// Progress Ring (Overall Mastery Circle)
export const ProgressRing = ({ value, size = 64, strokeWidth = 6, className }) => {
  const radius = (size - strokeWidth) / 2;
  const circumference = radius * 2 * Math.PI;
  const percent = Math.min(Math.max(value || 0, 0), 100);
  const strokeDashoffset = circumference - (percent / 100) * circumference;

  return (
    <div className={cn("relative flex items-center justify-center", className)} style={{ width: size, height: size }}>
      <svg className="w-full h-full transform -rotate-90">
        {/* Track */}
        <circle
          className="text-slate-200/50"
          strokeWidth={strokeWidth}
          stroke="currentColor"
          fill="transparent"
          r={radius}
          cx={size / 2}
          cy={size / 2}
        />
        {/* Fill */}
        <circle
          className="text-slate-800 transition-all duration-300"
          strokeWidth={strokeWidth}
          strokeDasharray={circumference}
          strokeDashoffset={strokeDashoffset}
          strokeLinecap="round"
          stroke="currentColor"
          fill="transparent"
          r={radius}
          cx={size / 2}
          cy={size / 2}
        />
      </svg>
      <span className="absolute font-mono text-xs font-bold text-slate-800">
        {formatPercentage(percent)}%
      </span>
    </div>
  );
};

// Skeleton Loader Component
export const Skeleton = ({ className, variant = 'rect', ...props }) => {
  return (
    <div
      className={cn(
        "animate-pulse bg-slate-300/60",
        variant === 'circle' && "rounded-full",
        variant === 'text' && "h-4 w-3/4 rounded-sm",
        className
      )}
      {...props}
    />
  );
};

// Toast Component
export const Toast = ({ message, type = 'success', onClose }) => {
  React.useEffect(() => {
    const timer = setTimeout(() => {
      onClose();
    }, 4000);
    return () => clearTimeout(timer);
  }, [onClose]);

  return (
    <div 
      className={cn(
        "flex items-center justify-between gap-3 p-4 border shadow-md font-mono text-xs font-semibold uppercase tracking-wider animate-in slide-in-from-bottom duration-300 pointer-events-auto",
        type === 'success' && "bg-emerald-50 text-emerald-800 border-emerald-200",
        type === 'error' && "bg-red-50 text-red-800 border-red-200",
        type === 'info' && "bg-blue-50 text-blue-800 border-blue-200"
      )}
      style={{ borderRadius: 'var(--radius-card)' }}
    >
      <span>{message}</span>
      <button onClick={onClose} className="text-slate-500 hover:text-slate-850">
        <X className="h-4 w-4" />
      </button>
    </div>
  );
};
