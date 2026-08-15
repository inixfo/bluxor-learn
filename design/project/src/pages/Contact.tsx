import { useEffect, useState } from 'react';
import { Link } from 'react-router-dom';
import { CheckCircle2, HelpCircle, Mail, MapPin, MessageCircle, Phone, ShieldCheck } from 'lucide-react';
import { Button } from '@/components/ui/Button';
import { Input, Select, Textarea } from '@/components/ui/Input';
import { submitContact } from '@/services/api/content';
import { getContactSettings, type ContactSettings } from '@/services/api/support';
import { useToast } from '@/components/ui/Toast';

const subjects = ['Product Question', 'Payment Issue', 'Download / Access', 'Account Issue', 'Refund Question', 'Technical Problem', 'Other'];

export default function Contact() {
  const toast = useToast();
  const [settings, setSettings] = useState<ContactSettings>({});
  const [sent, setSent] = useState(false);
  const [sending, setSending] = useState(false);
  const [form, setForm] = useState({ name: '', email: '', category: 'Product Question', subject: '', message: '' });

  useEffect(() => {
    document.title = 'Contact Learn by Bluxor';
    getContactSettings().then(setSettings).catch(() => undefined);
  }, []);

  const send = async (event: React.FormEvent) => {
    event.preventDefault();
    setSending(true);
    try {
      await submitContact({
        name: form.name,
        email: form.email,
        subject: form.subject ? `${form.category}: ${form.subject}` : form.category,
        message: form.message,
      });
      setSent(true);
      setForm({ name: '', email: '', category: 'Product Question', subject: '', message: '' });
    } catch {
      toast({ type: 'error', title: 'Message failed', message: 'Check the form and try again.' });
    } finally {
      setSending(false);
    }
  };

  const methods = [
    settings.support_email ? { icon: Mail, title: 'Email Support', value: settings.support_email, href: `mailto:${settings.support_email}` } : null,
    settings.support_phone ? { icon: Phone, title: 'Phone', value: settings.support_phone, href: `tel:${settings.support_phone}` } : null,
    settings.support_whatsapp ? { icon: MessageCircle, title: 'WhatsApp', value: 'Chat on WhatsApp', href: `https://wa.me/${settings.support_whatsapp.replace(/[^\d]/g, '')}` } : null,
    settings.business_name || settings.business_address ? { icon: MapPin, title: settings.business_name || 'Support Location', value: settings.business_address || '', href: null } : null,
  ].filter(Boolean) as { icon: typeof Mail; title: string; value: string; href?: string | null }[];

  return (
    <main>
      <section className="border-b border-ink-100 bg-gradient-to-b from-white to-ink-50/60">
        <div className="container-page grid gap-10 py-12 lg:grid-cols-[0.45fr_0.55fr] lg:py-16">
          <div>
            <p className="text-sm font-semibold uppercase tracking-wide text-brand-600">Contact</p>
            <h1 className="mt-3 font-display text-4xl font-bold text-ink-900 lg:text-5xl">We're here to help.</h1>
            <p className="mt-5 max-w-xl text-base leading-7 text-ink-600">
              Questions about a product, purchase, payment, download, or your account? Send us a message and our support team can take a look.
            </p>
          </div>
          <div className="rounded-2xl border border-brand-100 bg-white p-5 shadow-soft">
            <div className="flex items-start gap-3">
              <ShieldCheck className="mt-1 h-5 w-5 text-brand-600" />
              <p className="text-sm leading-6 text-ink-600">{settings.support_availability_text || 'Support availability can be configured by the site owner.'}</p>
            </div>
          </div>
        </div>
      </section>

      <section className="container-page grid gap-8 py-12 lg:grid-cols-[0.45fr_0.55fr] lg:py-16">
        <div className="space-y-6">
          {methods.length > 0 && (
            <div className="grid gap-3 sm:grid-cols-2 lg:grid-cols-1 xl:grid-cols-2">
              {methods.map((method) => (
                <div key={method.title} className="rounded-2xl border border-ink-100 bg-white p-5 shadow-soft">
                  <div className="flex h-11 w-11 items-center justify-center rounded-xl bg-brand-50 text-brand-700">
                    <method.icon className="h-5 w-5" />
                  </div>
                  <h2 className="mt-4 text-sm font-bold text-ink-900">{method.title}</h2>
                  {method.href ? (
                    <a href={method.href} className="mt-1 block break-words text-sm font-semibold text-brand-600 hover:text-brand-700">{method.value}</a>
                  ) : (
                    <p className="mt-1 whitespace-pre-wrap text-sm text-ink-600">{method.value}</p>
                  )}
                </div>
              ))}
            </div>
          )}

          <div className="rounded-2xl border border-ink-100 bg-white p-6 shadow-soft">
            <h2 className="text-lg font-bold text-ink-900">Before sending a message</h2>
            <p className="mt-2 text-sm leading-6 text-ink-600">Maybe we've already answered your question.</p>
            <div className="mt-5 grid gap-2 sm:grid-cols-2">
              <QuickLink to="/help">Browse Help Center</QuickLink>
              <QuickLink to="/faq">Read FAQs</QuickLink>
              <QuickLink to="/download-help">Download Help</QuickLink>
              <QuickLink to="/refund-policy">Refund Policy</QuickLink>
            </div>
          </div>
        </div>

        <div className="rounded-2xl border border-ink-100 bg-white p-6 shadow-soft">
          {sent ? (
            <div className="flex min-h-[420px] flex-col items-center justify-center text-center">
              <CheckCircle2 className="h-14 w-14 text-success-600" />
              <h2 className="mt-5 font-display text-2xl font-bold text-ink-900">Message received</h2>
              <p className="mt-2 max-w-sm text-sm leading-6 text-ink-600">Thanks for contacting Learn by Bluxor. Your inquiry has been added to our support inbox.</p>
              <Button className="mt-6" onClick={() => setSent(false)}>Send Another Message</Button>
            </div>
          ) : (
            <form onSubmit={send} className="space-y-4">
              <div>
                <h2 className="text-xl font-bold text-ink-900">Send us a message</h2>
                <p className="mt-1 text-sm text-ink-500">Tell us what you need help with and include any relevant order details.</p>
              </div>
              <div className="grid gap-4 sm:grid-cols-2">
                <Input label="Name" name="name" value={form.name} onChange={(event) => setForm((prev) => ({ ...prev, name: event.target.value }))} required />
                <Input label="Email" name="email" type="email" value={form.email} onChange={(event) => setForm((prev) => ({ ...prev, email: event.target.value }))} required />
              </div>
              <Select label="Issue category" value={form.category} onChange={(event) => setForm((prev) => ({ ...prev, category: event.target.value }))}>
                {subjects.map((subject) => <option key={subject} value={subject}>{subject}</option>)}
              </Select>
              <Input label="Subject details" value={form.subject} onChange={(event) => setForm((prev) => ({ ...prev, subject: event.target.value }))} placeholder="Optional short detail" />
              <Textarea label="Message" rows={8} value={form.message} onChange={(event) => setForm((prev) => ({ ...prev, message: event.target.value }))} required />
              <p className="flex gap-2 text-xs leading-5 text-ink-500"><HelpCircle className="mt-0.5 h-4 w-4 shrink-0" /> For your security, never send passwords, OTP codes, or full payment credentials.</p>
              <Button type="submit" loading={sending} className="w-full">Send Message</Button>
            </form>
          )}
        </div>
      </section>
    </main>
  );
}

function QuickLink({ to, children }: { to: string; children: React.ReactNode }) {
  return <Link to={to} className="rounded-xl border border-ink-100 px-3 py-2 text-sm font-semibold text-ink-700 hover:border-brand-200 hover:bg-brand-50 hover:text-brand-700">{children}</Link>;
}
