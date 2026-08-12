import { useEffect, useState } from 'react';
import { Globe, CreditCard, Mail, BarChart3, Shield, HardDrive, FileCode2, Save } from 'lucide-react';
import { Button } from '@/components/ui/Button';
import { Input, Select } from '@/components/ui/Input';
import { Badge } from '@/components/ui/Badge';
import { Card } from '@/components/ui/Card';
import { useToast } from '@/components/ui/Toast';
import { getAdminSettings, updateAdminSettings } from '@/services/api/admin';

const sections = [
  { id: 'general', label: 'General', icon: Globe },
  { id: 'payments', label: 'Payments', icon: CreditCard },
  { id: 'email', label: 'Email', icon: Mail },
  { id: 'analytics', label: 'Analytics', icon: BarChart3 },
  { id: 'security', label: 'Security', icon: Shield },
  { id: 'storage', label: 'Storage', icon: HardDrive },
  { id: 'landing-platform', label: 'Landing Page Platform', icon: FileCode2 },
];

export default function AdminSettings() {
  const toast = useToast();
  const [active, setActive] = useState('general');
  const [loading, setLoading] = useState(true);
  const [saving, setSaving] = useState(false);
  const [general, setGeneral] = useState({
    site_name: 'Learn by Bluxor',
    site_url: 'https://learn.bluxor.com',
    default_currency: 'BDT',
    timezone: 'Asia/Dhaka',
    support_email: '',
  });

  useEffect(() => {
    getAdminSettings()
      .then((settings) => {
        const group = settings.general || {};
        setGeneral((prev) => ({
          ...prev,
          site_name: String(group.site_name ?? prev.site_name),
          timezone: String(group.timezone ?? prev.timezone),
          support_email: String(group.support_email ?? prev.support_email),
          site_url: String(group.site_url ?? prev.site_url),
          default_currency: String(group.default_currency ?? prev.default_currency),
        }));
      })
      .finally(() => setLoading(false));
  }, []);

  const saveGeneral = async () => {
    setSaving(true);
    try {
      await updateAdminSettings('general', general);
      toast({ type: 'success', title: 'Settings saved' });
    } catch {
      toast({ type: 'error', title: 'Could not save settings' });
    } finally {
      setSaving(false);
    }
  };

  return (
    <div>
      <div className="mb-6 flex items-center justify-between">
        <div>
          <h1 className="font-display text-2xl font-bold text-ink-900">Settings</h1>
          <p className="mt-1 text-sm text-ink-500">{loading ? 'Loading settings...' : 'Configure safe store settings.'}</p>
        </div>
        {active === 'general' && <Button leftIcon={<Save className="h-4 w-4" />} loading={saving} onClick={saveGeneral}>Save</Button>}
      </div>

      <div className="grid gap-6 lg:grid-cols-[220px_1fr]">
        <aside className="lg:sticky lg:top-24 lg:self-start">
          <nav className="flex gap-1 overflow-x-auto lg:flex-col no-scrollbar">
            {sections.map((s) => (
              <button
                key={s.id}
                onClick={() => setActive(s.id)}
                className={`flex shrink-0 items-center gap-2.5 rounded-xl px-3 py-2.5 text-sm font-medium transition-colors ${active === s.id ? 'bg-brand-50 text-brand-700' : 'text-ink-600 hover:bg-ink-100'}`}
              >
                <s.icon className="h-4 w-4" />
                {s.label}
              </button>
            ))}
          </nav>
        </aside>

        <div>
          {active === 'general' && (
            <Card className="p-6">
              <h2 className="mb-4 text-base font-bold text-ink-900">General Settings</h2>
              <div className="grid gap-4 sm:grid-cols-2">
                <Input label="Site name" value={general.site_name} onChange={(e) => setGeneral((prev) => ({ ...prev, site_name: e.target.value }))} />
                <Input label="Site URL" value={general.site_url} onChange={(e) => setGeneral((prev) => ({ ...prev, site_url: e.target.value }))} />
                <Select label="Default currency" value={general.default_currency} onChange={(e) => setGeneral((prev) => ({ ...prev, default_currency: e.target.value }))}>
                  <option value="BDT">BDT</option>
                  <option value="USD">USD</option>
                </Select>
                <Select label="Timezone" value={general.timezone} onChange={(e) => setGeneral((prev) => ({ ...prev, timezone: e.target.value }))}>
                  <option value="Asia/Dhaka">Asia/Dhaka</option>
                  <option value="UTC">UTC</option>
                </Select>
                <Input label="Support email" type="email" value={general.support_email} onChange={(e) => setGeneral((prev) => ({ ...prev, support_email: e.target.value }))} className="sm:col-span-2" />
              </div>
            </Card>
          )}

          {active === 'payments' && <EnvironmentManaged title="Payments" icon={<CreditCard className="h-5 w-5" />} lines={['PipraPay is configured through server environment variables.', 'API keys are never rendered back to the browser.', 'Success, cancel, and webhook URLs use https://learn.bluxor.com.']} />}
          {active === 'email' && <EnvironmentManaged title="Email" icon={<Mail className="h-5 w-5" />} lines={['SMTP credentials are managed in .env.docker or the host secret manager.', 'Purchase confirmation emails are sent by the queue worker.']} />}
          {active === 'analytics' && <EnvironmentManaged title="Analytics" icon={<BarChart3 className="h-5 w-5" />} lines={['Internal analytics are captured by backend events.', 'External pixels should be added only after privacy/legal review.']} />}
          {active === 'security' && <EnvironmentManaged title="Security" icon={<Shield className="h-5 w-5" />} lines={['Admin access is enforced by backend roles.', '2FA policy is a planned hardening item and is not exposed as an inactive toggle.']} />}
          {active === 'storage' && <EnvironmentManaged title="Storage" icon={<HardDrive className="h-5 w-5" />} lines={['Private product files and original landing ZIPs stay outside the public web root.', 'Local Docker volumes or S3/R2-compatible storage are configured server-side.']} />}
          {active === 'landing-platform' && (
            <Card className="p-6">
              <h2 className="mb-4 text-base font-bold text-ink-900">Landing Page Platform</h2>
              <div className="grid gap-4 sm:grid-cols-2">
                <InfoTile label="Native route" value="/go/{slug}" />
                <InfoTile label="Schema" value="V2 only" />
                <InfoTile label="Runtime" value="lbx-runtime.v2.js" />
                <InfoTile label="Uploaded JS" value="Blocked" tone="danger" />
              </div>
              <div className="mt-4 flex flex-wrap gap-2">
                {['HTML', 'CSS', 'Images', 'Fonts', 'Safe SVG', 'Trusted runtime'].map((item) => <Badge key={item} tone="brand">{item}</Badge>)}
              </div>
            </Card>
          )}
        </div>
      </div>
    </div>
  );
}

function EnvironmentManaged({ title, icon, lines }: { title: string; icon: React.ReactNode; lines: string[] }) {
  return (
    <Card className="p-6">
      <div className="flex items-center gap-3">
        <div className="flex h-10 w-10 items-center justify-center rounded-xl bg-ink-100 text-ink-600">{icon}</div>
        <div>
          <h2 className="text-base font-bold text-ink-900">{title}</h2>
          <p className="text-xs text-ink-400">Environment managed</p>
        </div>
      </div>
      <ul className="mt-5 space-y-2 text-sm text-ink-600">
        {lines.map((line) => <li key={line}>- {line}</li>)}
      </ul>
    </Card>
  );
}

function InfoTile({ label, value, tone = 'brand' }: { label: string; value: string; tone?: 'brand' | 'danger' }) {
  return (
    <div className="rounded-xl bg-ink-50 p-4">
      <p className="text-xs text-ink-400">{label}</p>
      <p className={`mt-1 text-sm font-bold ${tone === 'danger' ? 'text-danger-700' : 'text-ink-900'}`}>{value}</p>
    </div>
  );
}
