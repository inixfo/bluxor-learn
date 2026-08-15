import { useCallback, useEffect, useState } from 'react';
import { Link, useParams } from 'react-router-dom';
import { ArrowLeft, CheckCircle2, Mail, ShieldAlert, Undo2 } from 'lucide-react';
import { Badge, type Tone } from '@/components/ui/Badge';
import { Button } from '@/components/ui/Button';
import { Card } from '@/components/ui/Card';
import { Input, Textarea } from '@/components/ui/Input';
import { useToast } from '@/components/ui/Toast';
import {
  getAdminContactInquiry,
  replyToAdminContactInquiry,
  updateAdminContactInquiry,
  type AdminContactInquiry,
  type AdminContactInquiryStatus,
} from '@/services/api/admin';

const statusTone: Record<AdminContactInquiryStatus, Tone> = {
  new: 'warning',
  read: 'brand',
  replied: 'violet',
  resolved: 'success',
  spam: 'danger',
};

export default function AdminContactInquiryDetail() {
  const { id = '' } = useParams();
  const toast = useToast();
  const [inquiry, setInquiry] = useState<AdminContactInquiry | null>(null);
  const [loading, setLoading] = useState(true);
  const [saving, setSaving] = useState(false);
  const [replying, setReplying] = useState(false);
  const [notes, setNotes] = useState('');
  const [reply, setReply] = useState({ subject: '', message: '' });

  const load = useCallback(() => getAdminContactInquiry(id)
    .then((next) => {
      setInquiry(next);
      setNotes(next.admin_notes || '');
      setReply((prev) => ({ ...prev, subject: prev.subject || `Re: ${next.subject}` }));
    })
    .finally(() => setLoading(false)), [id]);

  useEffect(() => {
    load();
  }, [load]);

  const updateStatus = async (status: AdminContactInquiryStatus) => {
    if (!inquiry) return;
    setSaving(true);
    try {
      const next = await updateAdminContactInquiry(inquiry.id, { status });
      setInquiry(next);
      toast({ type: 'success', title: 'Inquiry updated', message: `Marked as ${status}.` });
    } catch {
      toast({ type: 'error', title: 'Update failed', message: 'The inquiry status was not changed.' });
    } finally {
      setSaving(false);
    }
  };

  const saveNotes = async () => {
    if (!inquiry) return;
    setSaving(true);
    try {
      const next = await updateAdminContactInquiry(inquiry.id, { admin_notes: notes });
      setInquiry(next);
      toast({ type: 'success', title: 'Notes saved' });
    } catch {
      toast({ type: 'error', title: 'Notes failed', message: 'Admin notes could not be saved.' });
    } finally {
      setSaving(false);
    }
  };

  const sendReply = async (event: React.FormEvent) => {
    event.preventDefault();
    if (!inquiry) return;
    setReplying(true);
    try {
      const next = await replyToAdminContactInquiry(inquiry.id, reply);
      setInquiry(next);
      setReply({ subject: `Re: ${next.subject}`, message: '' });
      toast({ type: 'success', title: 'Reply sent', message: 'The reply was sent and saved to history.' });
    } catch {
      toast({ type: 'error', title: 'Reply failed', message: 'The message was not sent or marked replied.' });
    } finally {
      setReplying(false);
    }
  };

  if (loading) return <div className="text-sm text-ink-500">Loading inquiry...</div>;

  if (!inquiry) {
    return (
      <div>
        <Link to="/admin/contact-inquiries" className="mb-5 inline-flex items-center gap-2 text-sm font-semibold text-brand-600">
          <ArrowLeft className="h-4 w-4" /> Back to inquiries
        </Link>
        <Card className="p-8 text-sm text-ink-500">Inquiry not found.</Card>
      </div>
    );
  }

  return (
    <div>
      <div className="mb-6 flex flex-col gap-4 sm:flex-row sm:items-start sm:justify-between">
        <div>
          <Link to="/admin/contact-inquiries" className="mb-3 inline-flex items-center gap-2 text-sm font-semibold text-brand-600">
            <ArrowLeft className="h-4 w-4" /> Contact Inquiries
          </Link>
          <div className="flex flex-wrap items-center gap-3">
            <h1 className="font-display text-2xl font-bold text-ink-900">{inquiry.subject}</h1>
            <Badge tone={statusTone[inquiry.status]}>{inquiry.status}</Badge>
          </div>
          <p className="mt-1 text-sm text-ink-500">Received {new Date(inquiry.created_at).toLocaleString()}</p>
        </div>
        <div className="flex flex-wrap gap-2">
          <Button size="sm" variant="outline" disabled={saving} leftIcon={<Undo2 className="h-4 w-4" />} onClick={() => updateStatus(inquiry.status === 'new' ? 'read' : 'new')}>
            {inquiry.status === 'new' ? 'Mark read' : 'Mark unread'}
          </Button>
          <Button size="sm" variant="outline" disabled={saving} leftIcon={<CheckCircle2 className="h-4 w-4" />} onClick={() => updateStatus('resolved')}>Mark resolved</Button>
          <Button size="sm" variant="outline" disabled={saving} leftIcon={<ShieldAlert className="h-4 w-4" />} onClick={() => updateStatus('spam')}>Mark spam</Button>
        </div>
      </div>

      <div className="grid gap-6 lg:grid-cols-[1fr_360px]">
        <div className="space-y-6">
          <Card className="p-6">
            <h2 className="text-sm font-bold uppercase tracking-wide text-ink-400">Customer message</h2>
            <div className="mt-4 grid gap-4 sm:grid-cols-2">
              <Field label="Name" value={inquiry.name} />
              <Field label="Email" value={inquiry.email} />
              <Field label="Subject" value={inquiry.subject} className="sm:col-span-2" />
            </div>
            <div className="mt-5 rounded-2xl border border-ink-100 bg-ink-50/60 p-4">
              <p className="whitespace-pre-wrap text-sm leading-6 text-ink-700">{inquiry.message}</p>
            </div>
          </Card>

          <Card className="p-6">
            <h2 className="text-sm font-bold uppercase tracking-wide text-ink-400">Conversation history</h2>
            <div className="mt-5 space-y-5">
              <TimelineItem author="Customer" date={inquiry.created_at} message={inquiry.message} />
              {(inquiry.replies || []).map((item) => (
                <TimelineItem key={item.id} author={item.admin?.name || 'Admin'} date={item.created_at} message={item.message} subject={item.subject} admin />
              ))}
            </div>
          </Card>
        </div>

        <div className="space-y-6">
          <Card className="p-6">
            <h2 className="mb-4 text-sm font-bold uppercase tracking-wide text-ink-400">Reply</h2>
            <form onSubmit={sendReply} className="space-y-4">
              <Input label="To" value={inquiry.email} readOnly />
              <Input label="Subject" value={reply.subject} onChange={(event) => setReply((prev) => ({ ...prev, subject: event.target.value }))} required />
              <Textarea label="Message" rows={8} value={reply.message} onChange={(event) => setReply((prev) => ({ ...prev, message: event.target.value }))} required />
              <Button type="submit" className="w-full" loading={replying} leftIcon={<Mail className="h-4 w-4" />}>Send Reply</Button>
            </form>
          </Card>

          <Card className="p-6">
            <h2 className="mb-4 text-sm font-bold uppercase tracking-wide text-ink-400">Admin notes</h2>
            <Textarea label="Private notes" rows={6} value={notes} onChange={(event) => setNotes(event.target.value)} />
            <Button className="mt-4 w-full" variant="outline" loading={saving} onClick={saveNotes}>Save Notes</Button>
          </Card>
        </div>
      </div>
    </div>
  );
}

function Field({ label, value, className = '' }: { label: string; value: string; className?: string }) {
  return (
    <div className={className}>
      <p className="text-xs text-ink-400">{label}</p>
      <p className="mt-1 text-sm font-semibold text-ink-900">{value}</p>
    </div>
  );
}

function TimelineItem({ author, date, message, subject, admin = false }: { author: string; date: string; message: string; subject?: string; admin?: boolean }) {
  return (
    <div className="flex gap-3">
      <div className={`mt-1 h-3 w-3 rounded-full ${admin ? 'bg-brand-600' : 'bg-ink-300'}`} />
      <div className="min-w-0 flex-1 rounded-2xl border border-ink-100 bg-white p-4">
        <div className="flex flex-wrap items-center justify-between gap-2">
          <p className="text-sm font-bold text-ink-900">{author}</p>
          <p className="text-xs text-ink-400">{new Date(date).toLocaleString()}</p>
        </div>
        {subject && <p className="mt-2 text-sm font-semibold text-ink-800">{subject}</p>}
        <p className="mt-2 whitespace-pre-wrap text-sm leading-6 text-ink-600">{message}</p>
      </div>
    </div>
  );
}
