import { useEffect, useState } from 'react';
import { User, Mail, Phone, Lock, ShieldCheck } from 'lucide-react';
import { CustomerMobileNav } from '@/components/customer/CustomerSidebar';
import { Input } from '@/components/ui/Input';
import { Button } from '@/components/ui/Button';
import { Card } from '@/components/ui/Card';
import { useToast } from '@/components/ui/Toast';
import { getProfile, updatePassword, updateProfile } from '@/services/api/account';

export default function CustomerProfile() {
  const toast = useToast();
  const [showPwd] = useState(false);
  const [profile, setProfile] = useState({ name: '', phone: '', email: '', email_verified_at: null as string | null });
  const [passwords, setPasswords] = useState({ current_password: '', password: '', password_confirmation: '' });
  const [saving, setSaving] = useState(false);

  useEffect(() => {
    getProfile().then((user) => setProfile({
      name: user.name || '',
      phone: user.phone || '',
      email: user.email || '',
      email_verified_at: user.email_verified_at || null,
    }));
  }, []);

  const saveProfile = async () => {
    setSaving(true);
    try {
      const user = await updateProfile({ name: profile.name, phone: profile.phone });
      setProfile((prev) => ({ ...prev, name: user.name, phone: user.phone || '' }));
      toast({ type: 'success', title: 'Profile updated' });
    } catch {
      toast({ type: 'error', title: 'Could not update profile' });
    } finally {
      setSaving(false);
    }
  };

  const savePassword = async () => {
    try {
      await updatePassword(passwords);
      setPasswords({ current_password: '', password: '', password_confirmation: '' });
      toast({ type: 'success', title: 'Password changed' });
    } catch {
      toast({ type: 'error', title: 'Could not update password', message: 'Check your current password and confirmation.' });
    }
  };

  return (
    <div>
      <CustomerMobileNav />
      <div className="mb-6">
        <h1 className="font-display text-2xl font-bold text-ink-900">Profile</h1>
        <p className="mt-1 text-sm text-ink-500">Manage your account information.</p>
      </div>

      <div className="space-y-6">
        <Card className="p-6">
          <h2 className="mb-4 text-base font-bold text-ink-900">Account information</h2>
          <div className="grid gap-4 sm:grid-cols-2">
            <Input label="Full name" value={profile.name} onChange={(e) => setProfile((prev) => ({ ...prev, name: e.target.value }))} leftIcon={<User className="h-4 w-4" />} />
            <Input label="Phone" value={profile.phone} onChange={(e) => setProfile((prev) => ({ ...prev, phone: e.target.value }))} leftIcon={<Phone className="h-4 w-4" />} />
            <Input label="Email" type="email" value={profile.email} disabled leftIcon={<Mail className="h-4 w-4" />} className="sm:col-span-2" hint={profile.email_verified_at ? 'Verified email address' : 'Check your email to verify this address'} />
          </div>
          <div className="mt-4">
            <Button onClick={saveProfile} loading={saving}>Save changes</Button>
          </div>
        </Card>

        <Card className="p-6">
          <h2 className="mb-4 text-base font-bold text-ink-900">Change password</h2>
          <div className="grid gap-4 sm:max-w-md">
            <Input label="Current password" type="password" value={passwords.current_password} onChange={(e) => setPasswords((prev) => ({ ...prev, current_password: e.target.value }))} leftIcon={<Lock className="h-4 w-4" />} />
            <Input label="New password" type={showPwd ? 'text' : 'password'} value={passwords.password} onChange={(e) => setPasswords((prev) => ({ ...prev, password: e.target.value }))} leftIcon={<Lock className="h-4 w-4" />} />
            <Input label="Confirm new password" type="password" value={passwords.password_confirmation} onChange={(e) => setPasswords((prev) => ({ ...prev, password_confirmation: e.target.value }))} leftIcon={<Lock className="h-4 w-4" />} />
          </div>
          <div className="mt-4">
            <Button variant="secondary" onClick={savePassword}>Update password</Button>
          </div>
        </Card>

        <Card className="p-6">
          <h2 className="mb-4 text-base font-bold text-ink-900">Security</h2>
          <div className="flex items-center justify-between rounded-xl border border-ink-200/60 p-4">
            <div className="flex items-center gap-3">
              <div className="flex h-10 w-10 items-center justify-center rounded-lg bg-ink-100 text-ink-500">
                <ShieldCheck className="h-5 w-5" />
              </div>
              <div>
                <p className="text-sm font-semibold text-ink-900">Two-factor authentication</p>
                <p className="text-xs text-ink-400">Admin-ready architecture is in place; setup UI can be enabled later.</p>
              </div>
            </div>
            <Button variant="outline" size="sm" disabled>Enable</Button>
          </div>
        </Card>
      </div>
    </div>
  );
}
