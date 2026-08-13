import { Link, useNavigate } from 'react-router-dom';
import { useState } from 'react';
import { ShieldCheck, Mail, Lock, User, Eye, EyeOff, ArrowRight } from 'lucide-react';
import { Button } from '@/components/ui/Button';
import { Input } from '@/components/ui/Input';
import { useToast } from '@/components/ui/Toast';
import { useAuth } from '@/services/auth-context';
import { googleRedirectUrl } from '@/services/api/auth';

export default function Register() {
  const navigate = useNavigate();
  const toast = useToast();
  const { register } = useAuth();
  const [showPwd, setShowPwd] = useState(false);
  const [loading, setLoading] = useState(false);
  const [form, setForm] = useState({ name: '', email: '', password: '' });

  const submit = async (event: React.FormEvent) => {
    event.preventDefault();
    setLoading(true);
    try {
      await register({ ...form, password_confirmation: form.password });
      toast({ type: 'success', title: 'Account created', message: 'Check your email to verify your account.' });
      navigate('/account');
    } catch {
      toast({ type: 'error', title: 'Registration failed', message: 'Check the form and try again.' });
    } finally {
      setLoading(false);
    }
  };

  const continueWithGoogle = async () => {
    try {
      window.location.assign(await googleRedirectUrl('/account'));
    } catch {
      toast({ type: 'error', title: 'Google sign-up unavailable', message: 'Check Google OAuth configuration.' });
    }
  };

  return (
    <div className="flex min-h-[calc(100vh-4rem)] items-center justify-center px-4 py-12">
      <div className="w-full max-w-md">
        <div className="mb-8 text-center">
          <div className="mx-auto mb-4 flex h-14 w-14 items-center justify-center rounded-2xl bg-gradient-to-br from-brand-600 to-brand-700 text-white shadow-lg shadow-brand-600/30">
            <ShieldCheck className="h-7 w-7" />
          </div>
          <h1 className="font-display text-2xl font-bold text-ink-900">Create your account</h1>
          <p className="mt-1 text-sm text-ink-500">Access your purchases anytime, from anywhere.</p>
        </div>

        <div className="card-surface p-6">
          <form onSubmit={submit} className="space-y-4">
            <Input label="Full name" name="name" placeholder="Your name" value={form.name} onChange={(e) => setForm((prev) => ({ ...prev, name: e.target.value }))} leftIcon={<User className="h-4 w-4" />} required />
            <Input label="Email" type="email" name="email" placeholder="you@example.com" value={form.email} onChange={(e) => setForm((prev) => ({ ...prev, email: e.target.value }))} leftIcon={<Mail className="h-4 w-4" />} required />
            <Input
              label="Password"
              type={showPwd ? 'text' : 'password'}
              name="password"
              placeholder="At least 8 characters"
              value={form.password}
              onChange={(e) => setForm((prev) => ({ ...prev, password: e.target.value }))}
              leftIcon={<Lock className="h-4 w-4" />}
              rightSlot={
                <button type="button" onClick={() => setShowPwd((v) => !v)} className="text-ink-400 hover:text-ink-600">
                  {showPwd ? <EyeOff className="h-4 w-4" /> : <Eye className="h-4 w-4" />}
                </button>
              }
              hint="Use at least 8 characters with a mix of letters and numbers."
              required
            />
            <label className="flex items-start gap-2 text-sm text-ink-600">
              <input type="checkbox" className="mt-0.5 h-4 w-4 rounded border-ink-300 text-brand-600 focus:ring-brand-500" required />
              <span>I agree to the <Link to="/" className="font-medium text-brand-600">Terms</Link> and <Link to="/" className="font-medium text-brand-600">Privacy Policy</Link>.</span>
            </label>
            <Button type="submit" className="w-full" size="lg" loading={loading} rightIcon={<ArrowRight className="h-5 w-5" />}>
              Create account
            </Button>
          </form>

          <div className="my-5 flex items-center gap-3">
            <div className="h-px flex-1 bg-ink-100" />
            <span className="text-xs text-ink-400">or</span>
            <div className="h-px flex-1 bg-ink-100" />
          </div>

          <Button variant="outline" className="w-full" size="lg" onClick={continueWithGoogle}>
            Continue with Google
          </Button>
        </div>

        <p className="mt-6 text-center text-sm text-ink-500">
          Already have an account?{' '}
          <Link to="/login" className="font-semibold text-brand-600 hover:text-brand-700">
            Log in
          </Link>
        </p>
      </div>
    </div>
  );
}
