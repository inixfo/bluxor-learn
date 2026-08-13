import { useEffect, useRef, useState } from 'react';
import { Edit, Image as ImageIcon, Plus, Save, Trash2 } from 'lucide-react';
import { Button } from '@/components/ui/Button';
import { Card } from '@/components/ui/Card';
import { Input, Select, Textarea } from '@/components/ui/Input';
import { Modal } from '@/components/ui/Modal';
import { useToast } from '@/components/ui/Toast';
import { createAdminCategory, deleteAdminCategory, getAdminCategories, updateAdminCategory, type AdminCategory } from '@/services/api/admin';

type CategoryForm = { name: string; slug: string; description: string; status: 'active' | 'inactive'; sort_order: number; image: File | null };
const empty: CategoryForm = { name: '', slug: '', description: '', status: 'active', sort_order: 0, image: null };

export default function AdminCategories() {
  const toast = useToast();
  const inputRef = useRef<HTMLInputElement>(null);
  const [categories, setCategories] = useState<AdminCategory[]>([]);
  const [editing, setEditing] = useState<AdminCategory | null>(null);
  const [form, setForm] = useState<CategoryForm>(empty);
  const [open, setOpen] = useState(false);

  const load = () => getAdminCategories().then(setCategories);
  useEffect(() => { load(); }, []);

  const start = (category?: AdminCategory) => {
    setEditing(category || null);
    setForm(category ? {
      name: category.name,
      slug: category.slug,
      description: category.description || '',
      status: category.status,
      sort_order: category.sort_order,
      image: null,
    } : empty);
    setOpen(true);
  };

  const save = async () => {
    try {
      if (editing) await updateAdminCategory(editing.id, form);
      else await createAdminCategory(form);
      toast({ type: 'success', title: 'Category saved' });
      setOpen(false);
      load();
    } catch (error) {
      toast({ type: 'error', title: 'Category save failed', message: error instanceof Error ? error.message : 'Check the fields and try again.' });
    }
  };

  const remove = async (category: AdminCategory) => {
    try {
      await deleteAdminCategory(category.id);
      toast({ type: 'success', title: 'Category deleted' });
      load();
    } catch (error) {
      toast({ type: 'error', title: 'Category cannot be deleted', message: error instanceof Error ? error.message : 'Reassign products first.' });
    }
  };

  return (
    <div>
      <div className="mb-6 flex items-center justify-between">
        <div>
          <h1 className="font-display text-2xl font-bold text-ink-900">Categories</h1>
          <p className="mt-1 text-sm text-ink-500">Manage public storefront categories and ordering.</p>
        </div>
        <Button leftIcon={<Plus className="h-4 w-4" />} onClick={() => start()}>New Category</Button>
      </div>

      <Card className="overflow-hidden">
        <table className="w-full text-left text-sm">
          <thead className="border-b border-ink-100 bg-ink-50 text-xs uppercase text-ink-400">
            <tr><th className="px-4 py-3">Category</th><th className="px-4 py-3">Products</th><th className="px-4 py-3">Status</th><th className="px-4 py-3">Order</th><th className="px-4 py-3 text-right">Actions</th></tr>
          </thead>
          <tbody className="divide-y divide-ink-100">
            {categories.map((category) => (
              <tr key={category.id}>
                <td className="px-4 py-3">
                  <div className="flex items-center gap-3">
                    {category.image_path ? <img src={category.image_path} alt="" className="h-10 w-10 rounded-lg object-cover" /> : <div className="flex h-10 w-10 items-center justify-center rounded-lg bg-ink-100 text-ink-400"><ImageIcon className="h-4 w-4" /></div>}
                    <div><p className="font-semibold text-ink-900">{category.name}</p><p className="text-xs text-ink-400">/{category.slug}</p></div>
                  </div>
                </td>
                <td className="px-4 py-3 text-ink-600">{category.products_count || 0}</td>
                <td className="px-4 py-3 text-ink-600">{category.status}</td>
                <td className="px-4 py-3 text-ink-600">{category.sort_order}</td>
                <td className="px-4 py-3">
                  <div className="flex justify-end gap-1">
                    <Button size="icon" variant="ghost" onClick={() => start(category)}><Edit className="h-4 w-4" /></Button>
                    <Button size="icon" variant="ghost" onClick={() => remove(category)}><Trash2 className="h-4 w-4" /></Button>
                  </div>
                </td>
              </tr>
            ))}
          </tbody>
        </table>
      </Card>

      <Modal open={open} onClose={() => setOpen(false)} title={editing ? `Edit ${editing.name}` : 'Create Category'} size="lg" footer={<><Button variant="outline" onClick={() => setOpen(false)}>Cancel</Button><Button leftIcon={<Save className="h-4 w-4" />} onClick={save}>Save</Button></>}>
        <div className="space-y-4">
          <Input label="Name" value={form.name} onChange={(event) => setForm((prev) => ({ ...prev, name: event.target.value }))} />
          <Input label="Slug" value={form.slug} onChange={(event) => setForm((prev) => ({ ...prev, slug: event.target.value }))} />
          <Textarea label="Description" value={form.description} onChange={(event) => setForm((prev) => ({ ...prev, description: event.target.value }))} />
          <div className="grid gap-4 sm:grid-cols-2">
            <Select label="Status" value={form.status} onChange={(event) => setForm((prev) => ({ ...prev, status: event.target.value as 'active' | 'inactive' }))}>
              <option value="active">Active</option>
              <option value="inactive">Inactive</option>
            </Select>
            <Input label="Display order" type="number" value={String(form.sort_order)} onChange={(event) => setForm((prev) => ({ ...prev, sort_order: Number(event.target.value) }))} />
          </div>
          <input ref={inputRef} type="file" accept="image/jpeg,image/png,image/webp" className="hidden" onChange={(event) => setForm((prev) => ({ ...prev, image: event.target.files?.[0] || null }))} />
          <Button variant="outline" onClick={() => inputRef.current?.click()}>{form.image ? form.image.name : 'Choose category image'}</Button>
        </div>
      </Modal>
    </div>
  );
}
