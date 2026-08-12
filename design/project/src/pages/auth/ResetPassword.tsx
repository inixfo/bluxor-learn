import { Link, useNavigate, useSearchParams } from 'react-router-dom';
import { useState } from 'react';
import { ShieldCheck, Lock, Eye, EyeOff, ArrowRight } from 'lucide-react';
import { Button } from '@/components/ui/Button';
import { Input } from '@/components/ui/Input';
import { useToast } from '@/components/ui/Toast';
import { resetPassword } from '@/services/api/auth';

export default function ResetPassword() {
  const navigate = useNavigate();
  const [params] = useSearchParams();
  const toast = useToast();
  const [showPwd, setShowPwd] = useState(false);
  const [loading, setLoading] = useState(false);
  const [form, setForm] = useState({ password: '', confirm: '' });

  const submit = async (event: React.FormEvent) => {
    event.preventDefault();
    setLoading(true);
    try {
      await resetPassword({
        token: params.get('token') || '',
        email: params.get('email') || '',
        password: form.password,
        password_confirmation: form.confirm,
      });
      toast({ type: 'success', title: 'Password reset', message: 'You can now log in.' });
      navigate('/login');
    } catch {
      toast({ type: 'error', title: 'Reset failed', message: 'The reset link may be invalid or expired.' });
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
          <h1 className="font-display text-2xl font-bold text-ink-900">Set new password</h1>
          <p className="mt-1 text-sm text-ink-500">Choose a strong password for your account.</p>
        </div>
        <div className="card-surface p-6">
          <form onSubmit={submit} className="space-y-4">
            <Input
              label="New password"
              type={showPwd ? 'text' : 'password'}
              name="password"
              value={form.password}
              onChange={(e) => setForm((prev) => ({ ...prev, password: e.target.value }))}
              leftIcon={<Lock className="h-4 w-4" />}
              rightSlot={<button type="button" onClick={() => setShowPwd((v) => !v)} className="text-ink-400 hover:text-ink-600">{showPwd ? <EyeOff className="h-4 w-4" /> : <Eye className="h-4 w-4" />}</button>}
              required
            />
            <Input label="Confirm password" type={showPwd ? 'text' : 'password'} name="confirm" value={form.confirm} onChange={(e) => setForm((prev) => ({ ...prev, confirm: e.target.value }))} leftIcon={<Lock className="h-4 w-4" />} required />
            <Button type="submit" className="w-full" size="lg" loading={loading} rightIcon={<ArrowRight className="h-5 w-5" />}>
              Reset password
            </Button>
          </form>
        </div>
        <p className="mt-6 text-center text-sm text-ink-500">
          <Link to="/login" className="font-semibold text-brand-600 hover:text-brand-700">Back to login</Link>
        </p>
      </div>
    </div>
  );
}
