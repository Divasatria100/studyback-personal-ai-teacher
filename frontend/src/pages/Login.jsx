import React, { useState } from 'react';
import { useNavigate, Link } from 'react-router-dom';
import { useAppStore } from '../store/appStore';
import { Card, Input, Button } from '../components/Shared';
import { BookOpen } from 'lucide-react';

export default function Login({ isRegister = false }) {
  const navigate = useNavigate();
  const loginFn = useAppStore((state) => state.login);
  const registerFn = useAppStore((state) => state.register);
  
  const [name, setName] = useState('');
  const [email, setEmail] = useState('');
  const [password, setPassword] = useState('');
  const [passwordConfirm, setPasswordConfirm] = useState('');
  const [errors, setErrors] = useState({});
  const [isLoading, setIsLoading] = useState(false);

  const handleSubmit = async (e) => {
    e.preventDefault();
    setErrors({});
    
    // Validation
    const newErrors = {};
    if (!email) newErrors.email = 'Email is required';
    if (!password) newErrors.password = 'Password is required';
    if (isRegister) {
      if (!name) newErrors.name = 'Name is required';
      if (password !== passwordConfirm) {
        newErrors.passwordConfirm = 'Passwords do not match';
      }
    }
    
    if (Object.keys(newErrors).length > 0) {
      setErrors(newErrors);
      return;
    }

    setIsLoading(true);
    try {
      if (isRegister) {
        await registerFn(name, email, password);
      } else {
        await loginFn(email, password);
      }
      navigate('/');
    } catch (err) {
      if (err.errors) {
        const parsed = {};
        Object.entries(err.errors).forEach(([k, v]) => {
          parsed[k] = v[0];
        });
        setErrors(parsed);
      }
    } finally {
      setIsLoading(false);
    }
  };

  return (
    <div className="min-h-[70vh] flex items-center justify-center py-12 px-4 sm:px-6 lg:px-8">
      <Card glass className="max-w-md w-full p-8 border border-white/30">
        <div className="flex flex-col items-center mb-8">
          <div className="h-12 w-12 bg-slate-900/10 flex items-center justify-center mb-4" style={{ borderRadius: 'var(--radius-control)' }}>
            <BookOpen className="h-6 w-6 text-slate-900" />
          </div>
          <h2 className="text-3xl font-bold font-display text-slate-900 leading-tight text-center">
            {isRegister ? 'Create your account' : 'Welcome back'}
          </h2>
          <p className="mt-2 text-sm text-slate-600 font-body">
            {isRegister ? 'Start turning materials into personal teachers' : 'Sign in to access your study materials'}
          </p>
        </div>

        <form className="space-y-4" onSubmit={handleSubmit}>
          {isRegister && (
            <Input
              id="name"
              label="Full Name"
              type="text"
              placeholder="Jane Doe"
              value={name}
              onChange={(e) => setName(e.target.value)}
              error={errors.name}
            />
          )}

          <Input
            id="email"
            label="Email address"
            type="email"
            placeholder="jane@example.com"
            value={email}
            onChange={(e) => setEmail(e.target.value)}
            error={errors.email}
          />

          <Input
            id="password"
            label="Password"
            type="password"
            placeholder="••••••••"
            value={password}
            onChange={(e) => setPassword(e.target.value)}
            error={errors.password}
          />

          {isRegister && (
            <Input
              id="passwordConfirm"
              label="Confirm Password"
              type="password"
              placeholder="••••••••"
              value={passwordConfirm}
              onChange={(e) => setPasswordConfirm(e.target.value)}
              error={errors.passwordConfirm}
            />
          )}

          <Button
            type="submit"
            className="w-full mt-6"
            loading={isLoading}
          >
            {isRegister ? 'Create Account' : 'Sign In'}
          </Button>
        </form>

        <div className="mt-6 text-center">
          <Link
            to={isRegister ? '/login' : '/register'}
            className="font-mono text-xs font-semibold uppercase tracking-wider text-slate-700 hover:text-slate-900 transition-colors"
          >
            {isRegister ? 'Already have an account? Sign in' : "Don't have an account? Register"}
          </Link>
        </div>
      </Card>
    </div>
  );
}
