import { Link } from 'react-router-dom';
import { useState } from 'react';
import { ShieldCheck, Mail, ArrowRight } from 'lucide-react';
import { Button } from '@/components/ui/Button';
import { Input } from '@/components/ui/Input';
import { useToast } from '@/components/ui/Toast';
import { forgotPassword } from '@/services/api/auth';

export default function ForgotPassword() {
  const toast = useToast();
  const [email, setEmail] = useState('');
  const [loading, setLoading] = useState(false);

  const submit = async (event: React.FormEvent) => {
    event.preventDefault();
    setLoading(true);
    try {
      const message = await forgotPassword(email);
      toast({ type: 'success', title: 'Reset link requested', message });
    } catch {
      toast({ type: 'error', title: 'Could not request reset', message: 'Please try again.' });
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
          <h1 className="font-display text-2xl font-bold text-ink-900">Reset your password</h1>
          <p className="mt-1 text-sm text-ink-500">Enter your email and we'll send you a reset link.</p>
        </div>
        <div className="card-surface p-6">
          <form onSubmit={submit} className="space-y-4">
            <Input label="Email" type="email" name="email" placeholder="you@example.com" value={email} onChange={(e) => setEmail(e.target.value)} leftIcon={<Mail className="h-4 w-4" />} required />
            <Button type="submit" className="w-full" size="lg" loading={loading} rightIcon={<ArrowRight className="h-5 w-5" />}>
              Send reset link
            </Button>
          </form>
        </div>
        <p className="mt-6 text-center text-sm text-ink-500">
          Remember your password?{' '}
          <Link to="/login" className="font-semibold text-brand-600 hover:text-brand-700">Log in</Link>
        </p>
      </div>
    </div>
  );
}
