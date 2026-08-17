import { useEffect, useState } from 'react';
import { Globe, CreditCard, Mail, BarChart3, Shield, HardDrive, FileCode2, Save, MessageCircle } from 'lucide-react';
import { Button } from '@/components/ui/Button';
import { Input, Select, Textarea } from '@/components/ui/Input';
import { Badge } from '@/components/ui/Badge';
import { Card } from '@/components/ui/Card';
import { useToast } from '@/components/ui/Toast';
import {
  getAdminMetaTracking,
  getAdminSettings,
  sendAdminMetaTestEvent,
  sendAdminTestEmail,
  updateAdminMetaTracking,
  updateAdminSettings,
  type AdminMetaTrackingStatus,
} from '@/services/api/admin';

const sections = [
  { id: 'general', label: 'General', icon: Globe },
  { id: 'contact', label: 'Contact', icon: MessageCircle },
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
  const [testEmail, setTestEmail] = useState('');
  const [metaTracking, setMetaTracking] = useState<AdminMetaTrackingStatus | null>(null);
  const [metaForm, setMetaForm] = useState({ pixel_enabled: false, pixel_id: '', capi_enabled: false, graph_api_version: 'v25.0' });
  const [general, setGeneral] = useState({
    site_name: 'Learn by Bluxor',
    site_url: 'https://learn.bluxor.com',
    default_currency: 'BDT',
    timezone: 'Asia/Dhaka',
    support_email: '',
  });
  const [contact, setContact] = useState({
    support_email: '',
    support_phone: '',
    support_whatsapp: '',
    business_name: '',
    business_address: '',
    support_availability_text: '',
  });

  useEffect(() => {
    Promise.all([getAdminSettings(), getAdminMetaTracking()])
      .then((settings) => {
        const [settingsPayload, metaPayload] = settings;
        const contactGroup = settingsPayload.contact || {};
        const generalGroup = settingsPayload.general || {};
        setGeneral((prev) => ({
          ...prev,
          site_name: String(generalGroup.site_name ?? prev.site_name),
          timezone: String(generalGroup.timezone ?? prev.timezone),
          support_email: String(generalGroup.support_email ?? prev.support_email),
          site_url: String(generalGroup.site_url ?? prev.site_url),
          default_currency: String(generalGroup.default_currency ?? prev.default_currency),
        }));
        setContact((prev) => ({
          ...prev,
          support_email: String(contactGroup.support_email ?? generalGroup.support_email ?? prev.support_email),
          support_phone: String(contactGroup.support_phone ?? prev.support_phone),
          support_whatsapp: String(contactGroup.support_whatsapp ?? prev.support_whatsapp),
          business_name: String(contactGroup.business_name ?? prev.business_name),
          business_address: String(contactGroup.business_address ?? prev.business_address),
          support_availability_text: String(contactGroup.support_availability_text ?? prev.support_availability_text),
        }));
        setMetaTracking(metaPayload);
        setMetaForm({
          pixel_enabled: metaPayload.meta.pixel_enabled,
          pixel_id: metaPayload.meta.pixel_id,
          capi_enabled: metaPayload.meta.capi_enabled,
          graph_api_version: metaPayload.meta.graph_api_version,
        });
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

  const saveContact = async () => {
    setSaving(true);
    try {
      await updateAdminSettings('contact', contact);
      toast({ type: 'success', title: 'Contact settings saved' });
    } catch {
      toast({ type: 'error', title: 'Could not save contact settings' });
    } finally {
      setSaving(false);
    }
  };

  const saveMetaTracking = async () => {
    setSaving(true);
    try {
      const status = await updateAdminMetaTracking(metaForm);
      setMetaTracking(status);
      toast({ type: 'success', title: 'Meta tracking settings saved' });
    } catch {
      toast({ type: 'error', title: 'Could not save Meta tracking settings' });
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
        {active === 'contact' && <Button leftIcon={<Save className="h-4 w-4" />} loading={saving} onClick={saveContact}>Save</Button>}
        {active === 'analytics' && <Button leftIcon={<Save className="h-4 w-4" />} loading={saving} onClick={saveMetaTracking}>Save</Button>}
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

          {active === 'contact' && (
            <Card className="p-6">
              <h2 className="mb-4 text-base font-bold text-ink-900">Contact Settings</h2>
              <div className="grid gap-4 sm:grid-cols-2">
                <Input label="Support Email" type="email" value={contact.support_email} onChange={(e) => setContact((prev) => ({ ...prev, support_email: e.target.value }))} />
                <Input label="Support Phone" value={contact.support_phone} onChange={(e) => setContact((prev) => ({ ...prev, support_phone: e.target.value }))} />
                <Input label="WhatsApp Number" value={contact.support_whatsapp} onChange={(e) => setContact((prev) => ({ ...prev, support_whatsapp: e.target.value }))} />
                <Input label="Business Name" value={contact.business_name} onChange={(e) => setContact((prev) => ({ ...prev, business_name: e.target.value }))} />
                <Textarea label="Business Address" value={contact.business_address} onChange={(e) => setContact((prev) => ({ ...prev, business_address: e.target.value }))} rows={4} />
                <Textarea label="Support Availability Text" value={contact.support_availability_text} onChange={(e) => setContact((prev) => ({ ...prev, support_availability_text: e.target.value }))} rows={4} />
              </div>
              <p className="mt-4 text-xs text-ink-400">Empty fields are hidden on the public Contact page.</p>
            </Card>
          )}

          {active === 'payments' && <EnvironmentManaged title="Payments" icon={<CreditCard className="h-5 w-5" />} lines={['PipraPay is configured through server environment variables.', 'API keys are never rendered back to the browser.', 'Success, cancel, and webhook URLs use https://learn.bluxor.com.']} />}
          {active === 'email' && (
            <Card className="p-6">
              <EnvironmentManaged title="Email" icon={<Mail className="h-5 w-5" />} lines={['SMTP credentials are managed in .env.docker or the host secret manager.', 'Purchase confirmation emails are sent by the queue worker.']} embedded />
              <div className="mt-6 border-t border-ink-100 pt-5">
                <h3 className="text-sm font-bold text-ink-900">Send Test Email</h3>
                <div className="mt-3 flex flex-col gap-2 sm:flex-row">
                  <Input type="email" placeholder="owner@example.com" value={testEmail} onChange={(event) => setTestEmail(event.target.value)} />
                  <Button onClick={async () => {
                    try {
                      const message = await sendAdminTestEmail(testEmail);
                      toast({ type: 'success', title: 'Email queued', message });
                    } catch {
                      toast({ type: 'error', title: 'Test email failed', message: 'Check SMTP settings and backend logs.' });
                    }
                  }}>Send Test</Button>
                </div>
              </div>
            </Card>
          )}
          {active === 'analytics' && (
            <Card className="p-6">
              <div className="flex items-start justify-between gap-4">
                <div>
                  <h2 className="text-base font-bold text-ink-900">Tracking & Analytics</h2>
                  <p className="mt-1 text-sm text-ink-500">Configure Meta Pixel browser tracking and server-side Conversions API status.</p>
                </div>
                <Badge tone={metaTracking?.meta.pixel_effective_enabled ? 'success' : 'warning'}>
                  Pixel {metaTracking?.meta.pixel_effective_enabled ? 'Enabled' : 'Disabled'}
                </Badge>
              </div>

              <div className="mt-6 grid gap-4 sm:grid-cols-2">
                <label className="flex items-center justify-between rounded-xl border border-ink-200/60 bg-white px-4 py-3 text-sm font-medium text-ink-800">
                  Enable Meta Pixel
                  <input type="checkbox" checked={metaForm.pixel_enabled} onChange={(event) => setMetaForm((prev) => ({ ...prev, pixel_enabled: event.target.checked }))} />
                </label>
                <label className="flex items-center justify-between rounded-xl border border-ink-200/60 bg-white px-4 py-3 text-sm font-medium text-ink-800">
                  Enable Conversions API
                  <input type="checkbox" checked={metaForm.capi_enabled} onChange={(event) => setMetaForm((prev) => ({ ...prev, capi_enabled: event.target.checked }))} />
                </label>
                <Input label="Pixel / Dataset ID" value={metaForm.pixel_id} onChange={(event) => setMetaForm((prev) => ({ ...prev, pixel_id: event.target.value }))} />
                <Input label="Graph API Version" value={metaForm.graph_api_version} onChange={(event) => setMetaForm((prev) => ({ ...prev, graph_api_version: event.target.value }))} />
              </div>

              {metaTracking && (
                <div className="mt-6 grid gap-3 sm:grid-cols-2 lg:grid-cols-4">
                  <InfoTile label="Pixel env switch" value={metaTracking.meta.pixel_env_enabled ? 'Enabled' : 'Disabled'} tone={metaTracking.meta.pixel_env_enabled ? 'brand' : 'danger'} />
                  <InfoTile label="Pixel ID" value={metaTracking.meta.pixel_id_configured ? 'Configured' : 'Missing'} tone={metaTracking.meta.pixel_id_configured ? 'brand' : 'danger'} />
                  <InfoTile label="CAPI token" value={metaTracking.meta.capi_token_configured ? 'Configured' : 'Missing'} tone={metaTracking.meta.capi_token_configured ? 'brand' : 'danger'} />
                  <InfoTile label="Test event code" value={metaTracking.meta.test_event_code_configured ? 'Configured' : 'Missing'} />
                </div>
              )}

              <div className="mt-6 border-t border-ink-100 pt-5">
                <div className="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                  <div>
                    <h3 className="text-sm font-bold text-ink-900">Meta Test Event</h3>
                    <p className="mt-1 text-xs text-ink-400">Uses the configured server token without exposing it.</p>
                  </div>
                  <Button variant="outline" onClick={async () => {
                    try {
                      const result = await sendAdminMetaTestEvent();
                      toast({ type: result.ok ? 'success' : 'error', title: result.ok ? 'Meta accepted test event' : 'Meta rejected test event', message: result.message });
                    } catch {
                      toast({ type: 'error', title: 'Meta test failed', message: 'Check Pixel ID, token, Graph API version, and backend logs.' });
                    }
                  }}>Send Test Event</Button>
                </div>
              </div>

              {metaTracking?.recent_events.length ? (
                <div className="mt-6 overflow-hidden rounded-xl border border-ink-100">
                  {metaTracking.recent_events.map((event) => (
                    <div key={event.event_id} className="grid gap-2 border-b border-ink-100 px-4 py-3 text-sm last:border-b-0 sm:grid-cols-[1fr_auto]">
                      <div>
                        <p className="font-semibold text-ink-900">{event.event_name} <span className="font-normal text-ink-400">{event.event_id}</span></p>
                        {event.last_error_message && <p className="mt-1 text-xs text-danger-600">{event.last_error_message}</p>}
                      </div>
                      <Badge tone={event.status === 'sent' ? 'success' : event.status === 'failed' ? 'danger' : 'warning'}>{event.status}</Badge>
                    </div>
                  ))}
                </div>
              ) : null}
            </Card>
          )}
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

function EnvironmentManaged({ title, icon, lines, embedded = false }: { title: string; icon: React.ReactNode; lines: string[]; embedded?: boolean }) {
  const content = (
    <>
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
    </>
  );
  if (embedded) return content;

  return (
    <Card className="p-6">
      {content}
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
