import { useEffect, useState } from 'react';
import { Edit, Plus, Save, Trash2 } from 'lucide-react';
import { Badge } from '@/components/ui/Badge';
import { Button } from '@/components/ui/Button';
import { Card } from '@/components/ui/Card';
import { Input, Select, Textarea } from '@/components/ui/Input';
import { Modal } from '@/components/ui/Modal';
import { useToast } from '@/components/ui/Toast';
import {
  createAdminHelpArticle,
  createAdminHelpCategory,
  deleteAdminHelpArticle,
  deleteAdminHelpCategory,
  getAdminHelpArticles,
  getAdminHelpCategories,
  updateAdminHelpArticle,
  updateAdminHelpCategory,
  type AdminHelpArticle,
  type AdminHelpCategory,
} from '@/services/api/admin';

export default function AdminHelpCenterManager() {
  const toast = useToast();
  const [categories, setCategories] = useState<AdminHelpCategory[]>([]);
  const [articles, setArticles] = useState<AdminHelpArticle[]>([]);
  const [categoryForm, setCategoryForm] = useState<Partial<AdminHelpCategory> | null>(null);
  const [articleForm, setArticleForm] = useState<Partial<AdminHelpArticle> | null>(null);

  const load = () => Promise.all([getAdminHelpCategories(), getAdminHelpArticles()]).then(([nextCategories, nextArticles]) => {
    setCategories(nextCategories);
    setArticles(nextArticles);
  });

  useEffect(() => { load(); }, []);

  const saveCategory = async () => {
    if (!categoryForm) return;
    if (categoryForm.id) await updateAdminHelpCategory(categoryForm.id, categoryForm);
    else await createAdminHelpCategory({ ...categoryForm, status: categoryForm.status || 'active', sort_order: categoryForm.sort_order || 0 });
    toast({ type: 'success', title: 'Help category saved' });
    setCategoryForm(null);
    load();
  };

  const saveArticle = async () => {
    if (!articleForm) return;
    if (articleForm.id) await updateAdminHelpArticle(articleForm.id, articleForm);
    else await createAdminHelpArticle({ ...articleForm, status: articleForm.status || 'draft', sort_order: articleForm.sort_order || 0, is_featured: !!articleForm.is_featured });
    toast({ type: 'success', title: 'Help article saved' });
    setArticleForm(null);
    load();
  };

  return (
    <div>
      <div className="mb-6 flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
        <div>
          <h1 className="font-display text-2xl font-bold text-ink-900">Help Center</h1>
          <p className="mt-1 text-sm text-ink-500">Manage support categories and knowledge base articles.</p>
        </div>
        <div className="flex gap-2">
          <Button variant="outline" leftIcon={<Plus className="h-4 w-4" />} onClick={() => setCategoryForm({ status: 'active', sort_order: categories.length + 1 })}>Category</Button>
          <Button leftIcon={<Plus className="h-4 w-4" />} onClick={() => setArticleForm({ help_category_id: categories[0]?.id, status: 'draft', sort_order: articles.length + 1, is_featured: false })}>Article</Button>
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
                  <p className="text-xs text-ink-400">/{category.slug} · {category.articles_count || 0} articles</p>
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
                <tr><th className="px-4 py-3">Article</th><th className="px-4 py-3">Category</th><th className="px-4 py-3">Status</th><th className="px-4 py-3">Featured</th><th className="px-4 py-3 text-right">Actions</th></tr>
              </thead>
              <tbody className="divide-y divide-ink-100">
                {articles.map((article) => (
                  <tr key={article.id}>
                    <td className="px-4 py-3">
                      <p className="font-semibold text-ink-900">{article.title}</p>
                      <p className="line-clamp-1 text-xs text-ink-500">{article.summary}</p>
                    </td>
                    <td className="px-4 py-3 text-ink-600">{article.category?.name}</td>
                    <td className="px-4 py-3"><Badge tone={article.status === 'published' ? 'success' : 'neutral'}>{article.status}</Badge></td>
                    <td className="px-4 py-3 text-ink-600">{article.is_featured ? 'Yes' : 'No'}</td>
                    <td className="px-4 py-3 text-right">
                      <Button size="sm" variant="ghost" onClick={() => setArticleForm(article)} leftIcon={<Edit className="h-4 w-4" />}>Edit</Button>
                      <Button size="sm" variant="ghost" onClick={async () => { await deleteAdminHelpArticle(article.id); load(); }} leftIcon={<Trash2 className="h-4 w-4" />}>Delete</Button>
                    </td>
                  </tr>
                ))}
              </tbody>
            </table>
          </div>
        </Card>
      </div>

      <Modal open={!!categoryForm} onClose={() => setCategoryForm(null)} title="Help Category" footer={<><Button variant="outline" onClick={() => setCategoryForm(null)}>Cancel</Button><Button leftIcon={<Save className="h-4 w-4" />} onClick={saveCategory}>Save</Button></>}>
        <div className="space-y-4">
          <Input label="Name" value={categoryForm?.name || ''} onChange={(event) => setCategoryForm((prev) => ({ ...prev, name: event.target.value }))} />
          <Input label="Slug" value={categoryForm?.slug || ''} onChange={(event) => setCategoryForm((prev) => ({ ...prev, slug: event.target.value }))} />
          <Input label="Icon key" value={categoryForm?.icon || ''} onChange={(event) => setCategoryForm((prev) => ({ ...prev, icon: event.target.value }))} />
          <Textarea label="Description" value={categoryForm?.description || ''} onChange={(event) => setCategoryForm((prev) => ({ ...prev, description: event.target.value }))} />
          <Input label="Sort order" type="number" value={categoryForm?.sort_order || 0} onChange={(event) => setCategoryForm((prev) => ({ ...prev, sort_order: Number(event.target.value) }))} />
          <Select label="Status" value={categoryForm?.status || 'active'} onChange={(event) => setCategoryForm((prev) => ({ ...prev, status: event.target.value as 'active' | 'inactive' }))}><option value="active">Active</option><option value="inactive">Inactive</option></Select>
          {categoryForm?.id && <Button variant="destructive" leftIcon={<Trash2 className="h-4 w-4" />} onClick={async () => { await deleteAdminHelpCategory(categoryForm.id!); setCategoryForm(null); load(); }}>Delete Category</Button>}
        </div>
      </Modal>

      <Modal open={!!articleForm} onClose={() => setArticleForm(null)} title="Help Article" size="lg" footer={<><Button variant="outline" onClick={() => setArticleForm(null)}>Cancel</Button><Button leftIcon={<Save className="h-4 w-4" />} onClick={saveArticle}>Save</Button></>}>
        <div className="space-y-4">
          <Select label="Category" value={articleForm?.help_category_id || ''} onChange={(event) => setArticleForm((prev) => ({ ...prev, help_category_id: Number(event.target.value) }))}>{categories.map((category) => <option key={category.id} value={category.id}>{category.name}</option>)}</Select>
          <Input label="Title" value={articleForm?.title || ''} onChange={(event) => setArticleForm((prev) => ({ ...prev, title: event.target.value }))} />
          <Input label="Slug" value={articleForm?.slug || ''} onChange={(event) => setArticleForm((prev) => ({ ...prev, slug: event.target.value }))} />
          <Textarea label="Summary" value={articleForm?.summary || ''} onChange={(event) => setArticleForm((prev) => ({ ...prev, summary: event.target.value }))} />
          <Textarea label="Article body" rows={10} value={articleForm?.content || ''} onChange={(event) => setArticleForm((prev) => ({ ...prev, content: event.target.value }))} />
          <div className="grid gap-4 sm:grid-cols-3">
            <Input label="Sort order" type="number" value={articleForm?.sort_order || 0} onChange={(event) => setArticleForm((prev) => ({ ...prev, sort_order: Number(event.target.value) }))} />
            <Select label="Status" value={articleForm?.status || 'draft'} onChange={(event) => setArticleForm((prev) => ({ ...prev, status: event.target.value as 'draft' | 'published' }))}><option value="draft">Draft</option><option value="published">Published</option></Select>
            <label className="flex items-end gap-2 pb-2 text-sm font-medium text-ink-700">
              <input type="checkbox" checked={!!articleForm?.is_featured} onChange={(event) => setArticleForm((prev) => ({ ...prev, is_featured: event.target.checked }))} className="h-4 w-4 rounded border-ink-300 text-brand-600" />
              Featured
            </label>
          </div>
        </div>
      </Modal>
    </div>
  );
}
