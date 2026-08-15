import { useEffect, useState } from 'react';
import { Edit, Plus, Save, Trash2 } from 'lucide-react';
import { Badge } from '@/components/ui/Badge';
import { Button } from '@/components/ui/Button';
import { Card } from '@/components/ui/Card';
import { Input, Select, Textarea } from '@/components/ui/Input';
import { Modal } from '@/components/ui/Modal';
import { useToast } from '@/components/ui/Toast';
import {
  createAdminFaqCategory,
  createAdminFaqItem,
  deleteAdminFaqCategory,
  deleteAdminFaqItem,
  getAdminFaqCategories,
  getAdminFaqItems,
  updateAdminFaqCategory,
  updateAdminFaqItem,
  type AdminFaqCategory,
  type AdminFaqItem,
} from '@/services/api/admin';

export default function AdminFaqManager() {
  const toast = useToast();
  const [categories, setCategories] = useState<AdminFaqCategory[]>([]);
  const [items, setItems] = useState<AdminFaqItem[]>([]);
  const [categoryForm, setCategoryForm] = useState<Partial<AdminFaqCategory> | null>(null);
  const [itemForm, setItemForm] = useState<Partial<AdminFaqItem> | null>(null);

  const load = () => Promise.all([getAdminFaqCategories(), getAdminFaqItems()]).then(([nextCategories, nextItems]) => {
    setCategories(nextCategories);
    setItems(nextItems);
  });

  useEffect(() => { load(); }, []);

  const saveCategory = async () => {
    if (!categoryForm) return;
    if (categoryForm.id) await updateAdminFaqCategory(categoryForm.id, categoryForm);
    else await createAdminFaqCategory({ ...categoryForm, status: categoryForm.status || 'active', sort_order: categoryForm.sort_order || 0 });
    toast({ type: 'success', title: 'FAQ category saved' });
    setCategoryForm(null);
    load();
  };

  const saveItem = async () => {
    if (!itemForm) return;
    if (itemForm.id) await updateAdminFaqItem(itemForm.id, itemForm);
    else await createAdminFaqItem({ ...itemForm, status: itemForm.status || 'active', sort_order: itemForm.sort_order || 0 });
    toast({ type: 'success', title: 'FAQ question saved' });
    setItemForm(null);
    load();
  };

  return (
    <div>
      <div className="mb-6 flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
        <div>
          <h1 className="font-display text-2xl font-bold text-ink-900">FAQ</h1>
          <p className="mt-1 text-sm text-ink-500">Manage public FAQ categories and questions.</p>
        </div>
        <div className="flex gap-2">
          <Button variant="outline" leftIcon={<Plus className="h-4 w-4" />} onClick={() => setCategoryForm({ status: 'active', sort_order: categories.length + 1 })}>Category</Button>
          <Button leftIcon={<Plus className="h-4 w-4" />} onClick={() => setItemForm({ faq_category_id: categories[0]?.id, status: 'active', sort_order: items.length + 1 })}>Question</Button>
        </div>
      </div>

      <div className="grid gap-6 lg:grid-cols-[320px_1fr]">
        <Card className="overflow-hidden">
          <div className="border-b border-ink-100 p-4">
            <h2 className="text-sm font-bold text-ink-900">Categories</h2>
          </div>
          <div className="divide-y divide-ink-100">
            {categories.map((category) => (
              <div key={category.id} className="flex items-center justify-between gap-3 p-4">
                <div>
                  <p className="font-semibold text-ink-900">{category.name}</p>
                  <p className="text-xs text-ink-400">/{category.slug} · {category.items_count || 0} questions</p>
                </div>
                <Button size="icon" variant="ghost" onClick={() => setCategoryForm(category)}><Edit className="h-4 w-4" /></Button>
              </div>
            ))}
          </div>
        </Card>

        <Card className="overflow-hidden">
          <div className="overflow-x-auto">
            <table className="w-full text-sm">
              <thead className="border-b border-ink-100 bg-ink-50 text-left text-xs text-ink-400">
                <tr><th className="px-4 py-3">Question</th><th className="px-4 py-3">Category</th><th className="px-4 py-3">Status</th><th className="px-4 py-3 text-right">Actions</th></tr>
              </thead>
              <tbody className="divide-y divide-ink-100">
                {items.map((item) => (
                  <tr key={item.id}>
                    <td className="px-4 py-3 font-semibold text-ink-900">{item.question}</td>
                    <td className="px-4 py-3 text-ink-600">{item.category?.name}</td>
                    <td className="px-4 py-3"><Badge tone={item.status === 'active' ? 'success' : 'neutral'}>{item.status}</Badge></td>
                    <td className="px-4 py-3 text-right">
                      <Button size="sm" variant="ghost" onClick={() => setItemForm(item)} leftIcon={<Edit className="h-4 w-4" />}>Edit</Button>
                      <Button size="sm" variant="ghost" onClick={async () => { await deleteAdminFaqItem(item.id); load(); }} leftIcon={<Trash2 className="h-4 w-4" />}>Delete</Button>
                    </td>
                  </tr>
                ))}
              </tbody>
            </table>
          </div>
        </Card>
      </div>

      <Modal open={!!categoryForm} onClose={() => setCategoryForm(null)} title="FAQ Category" footer={<><Button variant="outline" onClick={() => setCategoryForm(null)}>Cancel</Button><Button leftIcon={<Save className="h-4 w-4" />} onClick={saveCategory}>Save</Button></>}>
        <div className="space-y-4">
          <Input label="Name" value={categoryForm?.name || ''} onChange={(event) => setCategoryForm((prev) => ({ ...prev, name: event.target.value }))} />
          <Input label="Slug" value={categoryForm?.slug || ''} onChange={(event) => setCategoryForm((prev) => ({ ...prev, slug: event.target.value }))} />
          <Input label="Sort order" type="number" value={categoryForm?.sort_order || 0} onChange={(event) => setCategoryForm((prev) => ({ ...prev, sort_order: Number(event.target.value) }))} />
          <Select label="Status" value={categoryForm?.status || 'active'} onChange={(event) => setCategoryForm((prev) => ({ ...prev, status: event.target.value as 'active' | 'inactive' }))}><option value="active">Active</option><option value="inactive">Inactive</option></Select>
          {categoryForm?.id && <Button variant="destructive" leftIcon={<Trash2 className="h-4 w-4" />} onClick={async () => { await deleteAdminFaqCategory(categoryForm.id!); setCategoryForm(null); load(); }}>Delete Category</Button>}
        </div>
      </Modal>

      <Modal open={!!itemForm} onClose={() => setItemForm(null)} title="FAQ Question" size="lg" footer={<><Button variant="outline" onClick={() => setItemForm(null)}>Cancel</Button><Button leftIcon={<Save className="h-4 w-4" />} onClick={saveItem}>Save</Button></>}>
        <div className="space-y-4">
          <Select label="Category" value={itemForm?.faq_category_id || ''} onChange={(event) => setItemForm((prev) => ({ ...prev, faq_category_id: Number(event.target.value) }))}>{categories.map((category) => <option key={category.id} value={category.id}>{category.name}</option>)}</Select>
          <Input label="Question" value={itemForm?.question || ''} onChange={(event) => setItemForm((prev) => ({ ...prev, question: event.target.value }))} />
          <Textarea label="Answer" rows={8} value={itemForm?.answer || ''} onChange={(event) => setItemForm((prev) => ({ ...prev, answer: event.target.value }))} />
          <Input label="Sort order" type="number" value={itemForm?.sort_order || 0} onChange={(event) => setItemForm((prev) => ({ ...prev, sort_order: Number(event.target.value) }))} />
          <Select label="Status" value={itemForm?.status || 'active'} onChange={(event) => setItemForm((prev) => ({ ...prev, status: event.target.value as 'active' | 'inactive' }))}><option value="active">Active</option><option value="inactive">Inactive</option></Select>
        </div>
      </Modal>
    </div>
  );
}
