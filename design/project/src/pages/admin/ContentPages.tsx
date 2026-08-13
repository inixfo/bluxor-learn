import { useEffect, useState } from 'react';
import { Edit, Save } from 'lucide-react';
import { Button } from '@/components/ui/Button';
import { Card } from '@/components/ui/Card';
import { Input, Select, Textarea } from '@/components/ui/Input';
import { Modal } from '@/components/ui/Modal';
import { useToast } from '@/components/ui/Toast';
import { getAdminContentPages, updateAdminContentPage, type AdminContentPage } from '@/services/api/admin';

export default function AdminContentPages() {
  const toast = useToast();
  const [pages, setPages] = useState<AdminContentPage[]>([]);
  const [editing, setEditing] = useState<AdminContentPage | null>(null);
  const [form, setForm] = useState<Partial<AdminContentPage>>({});

  const load = () => getAdminContentPages().then(setPages);
  useEffect(() => { load(); }, []);

  const start = (page: AdminContentPage) => {
    setEditing(page);
    setForm(page);
  };

  const save = async () => {
    if (!editing) return;
    await updateAdminContentPage(editing.id, form);
    toast({ type: 'success', title: 'Page saved' });
    setEditing(null);
    load();
  };

  return (
    <div>
      <div className="mb-6">
        <h1 className="font-display text-2xl font-bold text-ink-900">Content Pages</h1>
        <p className="mt-1 text-sm text-ink-500">Edit footer, support, and legal pages.</p>
      </div>

      <Card className="overflow-hidden">
        <table className="w-full text-left text-sm">
          <thead className="border-b border-ink-100 bg-ink-50 text-xs uppercase text-ink-400">
            <tr><th className="px-4 py-3">Title</th><th className="px-4 py-3">Route</th><th className="px-4 py-3">Status</th><th className="px-4 py-3 text-right">Actions</th></tr>
          </thead>
          <tbody className="divide-y divide-ink-100">
            {pages.map((page) => (
              <tr key={page.id}>
                <td className="px-4 py-3 font-semibold text-ink-900">{page.title}</td>
                <td className="px-4 py-3 font-mono text-xs text-ink-500">/{page.slug}</td>
                <td className="px-4 py-3 text-ink-600">{page.status}</td>
                <td className="px-4 py-3 text-right">
                  <Button size="sm" variant="ghost" leftIcon={<Edit className="h-4 w-4" />} onClick={() => start(page)}>Edit</Button>
                </td>
              </tr>
            ))}
          </tbody>
        </table>
      </Card>

      <Modal open={!!editing} onClose={() => setEditing(null)} title={editing ? `Edit ${editing.title}` : 'Edit Page'} size="lg" footer={<><Button variant="outline" onClick={() => setEditing(null)}>Cancel</Button><Button leftIcon={<Save className="h-4 w-4" />} onClick={save}>Save</Button></>}>
        <div className="space-y-4">
          <Input label="Title" value={form.title || ''} onChange={(event) => setForm((prev) => ({ ...prev, title: event.target.value }))} />
          <Input label="Slug" value={form.slug || ''} onChange={(event) => setForm((prev) => ({ ...prev, slug: event.target.value }))} />
          <Textarea label="Content" value={form.content || ''} onChange={(event) => setForm((prev) => ({ ...prev, content: event.target.value }))} rows={10} />
          <Input label="Meta title" value={form.meta_title || ''} onChange={(event) => setForm((prev) => ({ ...prev, meta_title: event.target.value }))} />
          <Textarea label="Meta description" value={form.meta_description || ''} onChange={(event) => setForm((prev) => ({ ...prev, meta_description: event.target.value }))} />
          <Select label="Status" value={form.status || 'draft'} onChange={(event) => setForm((prev) => ({ ...prev, status: event.target.value as 'draft' | 'published' }))}>
            <option value="published">Published</option>
            <option value="draft">Draft</option>
          </Select>
        </div>
      </Modal>
    </div>
  );
}
