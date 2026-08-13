import { useEffect, useState } from 'react';
import { Link, useLocation } from 'react-router-dom';
import { Button } from '@/components/ui/Button';
import { Input, Textarea } from '@/components/ui/Input';
import { useToast } from '@/components/ui/Toast';
import { getContentPage, submitContact, type ContentPage } from '@/services/api/content';

export default function StaticPage() {
  const location = useLocation();
  const toast = useToast();
  const slug = location.pathname.replace(/^\//, '') || 'help';
  const [page, setPage] = useState<ContentPage | null>(null);
  const [form, setForm] = useState({ name: '', email: '', subject: '', message: '' });
  const [sending, setSending] = useState(false);

  useEffect(() => {
    setPage(null);
    getContentPage(slug).then(setPage).catch(() => undefined);
  }, [slug]);

  const send = async (event: React.FormEvent) => {
    event.preventDefault();
    setSending(true);
    try {
      const message = await submitContact(form);
      toast({ type: 'success', title: 'Message sent', message });
      setForm({ name: '', email: '', subject: '', message: '' });
    } catch {
      toast({ type: 'error', title: 'Message failed', message: 'Check the form and try again.' });
    } finally {
      setSending(false);
    }
  };

  return (
    <div className="container-page py-16">
      <div className="mx-auto max-w-2xl">
        <h1 className="font-display text-3xl font-bold text-ink-900">{page?.title || 'Loading...'}</h1>
        <div className="mt-5 space-y-4 text-sm leading-6 text-ink-600">
          {(page?.content || '').split(/\n\n+/).filter(Boolean).map((paragraph) => <p key={paragraph}>{paragraph}</p>)}
        </div>
        {slug === 'contact' && (
          <form onSubmit={send} className="mt-8 space-y-4 rounded-xl border border-ink-100 bg-white p-5">
            <Input label="Name" value={form.name} onChange={(event) => setForm((prev) => ({ ...prev, name: event.target.value }))} required />
            <Input label="Email" type="email" value={form.email} onChange={(event) => setForm((prev) => ({ ...prev, email: event.target.value }))} required />
            <Input label="Subject" value={form.subject} onChange={(event) => setForm((prev) => ({ ...prev, subject: event.target.value }))} required />
            <Textarea label="Message" value={form.message} onChange={(event) => setForm((prev) => ({ ...prev, message: event.target.value }))} required />
            <Button type="submit" loading={sending}>Send Message</Button>
          </form>
        )}
        <Link to="/products" className="mt-8 inline-block">
          <Button>Browse Products</Button>
        </Link>
      </div>
    </div>
  );
}
