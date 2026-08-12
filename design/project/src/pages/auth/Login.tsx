import { Link, useNavigate } from 'react-router-dom';
import { useState } from 'react';
import { ShieldCheck, Mail, Lock, Eye, EyeOff, ArrowRight } from 'lucide-react';
import { Button } from '@/components/ui/Button';
import { Input } from '@/components/ui/Input';
import { useToast } from '@/components/ui/Toast';
import { useAuth } from '@/services/auth-context';

export default function Login() {
  const navigate = useNavigate();
  const toast = useToast();
  const { login } = useAuth();
  const [showPwd, setShowPwd] = useState(false);
  const [loading, setLoading] = useState(false);
  const [form, setForm] = useState({ email: '', password: '', remember: false });

  const submit = async (event: React.FormEvent) => {
    event.preventDefault();
    setLoading(true);
    try {
      await login(form);
      navigate('/account');
    } catch {
      toast({ type: 'error', title: 'Login failed', message: 'Check your email and password.' });
    } finally {
      setLoading(false);
    }
  };

  return (
    <div className="flex min-h-[calc(100vh-4rem)] items-center justify-center px-4 py-12">
      <div className="w-full max-w-md">
        <div className="mb-8 text-center">
          <div className="mx-auto mb-4 flex h-14 w-14 items-center justify-center rounded-2xl bg-gradient-to-br from-brand-600 to-brand-700 text-white shadow-lg shadow-brand-600/30">
            <ShieldCheck className="h-7 w-7" />
          </div>
          <h1 className="font-display text-2xl font-bold text-ink-900">Welcome back</h1>
          <p className="mt-1 text-sm text-ink-500">Log in to access your library and orders.</p>
        </div>

        <div className="card-surface p-6">
          <form onSubmit={submit} className="space-y-4">
            <Input label="Email" type="email" name="email" placeholder="you@example.com" value={form.email} onChange={(e) => setForm((prev) => ({ ...prev, email: e.target.value }))} leftIcon={<Mail className="h-4 w-4" />} required />
            <Input
              label="Password"
              type={showPwd ? 'text' : 'password'}
              name="password"
              placeholder="Password"
              value={form.password}
              onChange={(e) => setForm((prev) => ({ ...prev, password: e.target.value }))}
              leftIcon={<Lock className="h-4 w-4" />}
              rightSlot={
                <button type="button" onClick={() => setShowPwd((v) => !v)} className="text-ink-400 hover:text-ink-600">
                  {showPwd ? <EyeOff className="h-4 w-4" /> : <Eye className="h-4 w-4" />}
                </button>
              }
              required
            />
            <div className="flex items-center justify-between">
              <label className="flex items-center gap-2 text-sm text-ink-600">
                <input type="checkbox" checked={form.remember} onChange={(e) => setForm((prev) => ({ ...prev, remember: e.target.checked }))} className="h-4 w-4 rounded border-ink-300 text-brand-600 focus:ring-brand-500" />
                Remember me
              </label>
              <Link to="/forgot-password" className="text-sm font-medium text-brand-600 hover:text-brand-700">
                Forgot password?
              </Link>
            </div>
            <Button type="submit" className="w-full" size="lg" loading={loading} rightIcon={<ArrowRight className="h-5 w-5" />}>
              Log in
            </Button>
          </form>

          <div className="my-5 flex items-center gap-3">
            <div className="h-px flex-1 bg-ink-100" />
            <span className="text-xs text-ink-400">or</span>
            <div className="h-px flex-1 bg-ink-100" />
          </div>

          <Button variant="outline" className="w-full" size="lg" disabled>
            Continue with Google
          </Button>
        </div>

        <p className="mt-6 text-center text-sm text-ink-500">
          Don't have an account?{' '}
          <Link to="/register" className="font-semibold text-brand-600 hover:text-brand-700">
            Sign up
          </Link>
        </p>
      </div>
    </div>
  );
}
