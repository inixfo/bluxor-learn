import { useCallback, useEffect, useMemo, useState } from 'react';
import { Copy, Edit, ExternalLink, FileUp, Search, Upload } from 'lucide-react';
import { Badge } from '@/components/ui/Badge';
import { Button } from '@/components/ui/Button';
import { Card, EmptyState } from '@/components/ui/Card';
import { Input, Select, Textarea } from '@/components/ui/Input';
import { Modal } from '@/components/ui/Modal';
import { useToast } from '@/components/ui/Toast';
import {
  archiveAdminResource,
  createAdminResource,
  getAdminProducts,
  getAdminResources,
  updateAdminResource,
  type AdminProduct,
  type AdminResource,
  type AdminResourceAccess,
  type AdminResourcePayload,
  type AdminResourceSource,
  type AdminResourceStatus,
} from '@/services/api/admin';

const resourceTypes = ['n8n Workflow', 'Spreadsheet', 'CSV', 'Template', 'ZIP / Project', 'PDF', 'Document', 'Code / Example', 'Image', 'Other'];

type ResourceForm = AdminResourcePayload & { product_ids: number[]; file: File | null };

const emptyForm: ResourceForm = {
  title: '',
  slug: '',
  description: '',
  resource_type: 'n8n Workflow',
  source_type: 'uploaded_file',
  external_url: '',
  product_ids: [],
  access_type: 'public',
  version: '1.0',
  status: 'draft',
  file: null,
};

export default function AdminResources() {
  const toast = useToast();
  const [resources, setResources] = useState<AdminResource[]>([]);
  const [products, setProducts] = useState<AdminProduct[]>([]);
  const [filters, setFilters] = useState({ q: '', product_id: '', resource_type: '', status: '', access_type: '' });
  const [editing, setEditing] = useState<AdminResource | null>(null);
  const [modalOpen, setModalOpen] = useState(false);
  const [form, setForm] = useState<ResourceForm>(emptyForm);
  const [saving, setSaving] = useState(false);

  const load = useCallback(() => getAdminResources(filters).then(setResources), [filters]);

  useEffect(() => {
    getAdminProducts().then(setProducts).catch(() => undefined);
  }, []);

  useEffect(() => {
    const timeout = window.setTimeout(() => {
      load().catch(() => toast({ type: 'error', title: 'Resources failed to load' }));
    }, 180);
    return () => window.clearTimeout(timeout);
  }, [load, toast]);

  const openCreate = () => {
    setEditing(null);
    setForm(emptyForm);
    setModalOpen(true);
  };

  const openEdit = (resource: AdminResource) => {
    setEditing(resource);
    setForm({
      title: resource.title,
      slug: resource.slug,
      description: resource.description || '',
      resource_type: resource.resource_type,
      source_type: resource.source_type,
      external_url: resource.external_url || '',
      product_ids: resource.products?.map((product) => product.id) || [],
      access_type: resource.access_type,
      version: resource.version,
      status: resource.status,
      file: null,
    });
    setModalOpen(true);
  };

  const copyLink = async (slug: string) => {
    await navigator.clipboard.writeText(`${window.location.origin}/r/${slug}`);
    toast({ type: 'success', title: 'Resource link copied.' });
  };

  const save = async () => {
    setSaving(true);
    try {
      const payload = {
        ...form,
        external_url: form.source_type === 'external_url' ? form.external_url : '',
        file: form.source_type === 'uploaded_file' ? form.file : null,
      };
      const resource = editing ? await updateAdminResource(editing.id, payload) : await createAdminResource(payload);
      toast({ type: 'success', title: editing ? 'Resource updated' : 'Resource uploaded' });
      setModalOpen(false);
      load();
      copyLink(resource.slug).catch(() => undefined);
    } catch {
      toast({ type: 'error', title: 'Save failed', message: 'Check required fields, file type, and slug format.' });
    } finally {
      setSaving(false);
    }
  };

  const archive = async (resource: AdminResource) => {
    await archiveAdminResource(resource.id);
    toast({ type: 'success', title: 'Resource archived' });
    load();
  };

  return (
    <div>
      <div className="mb-6 flex flex-col gap-4 sm:flex-row sm:items-end sm:justify-between">
        <div>
          <h1 className="font-display text-2xl font-bold text-ink-900">Resources</h1>
          <p className="mt-1 text-sm text-ink-500">Upload supporting files and copy stable ebook links.</p>
        </div>
        <Button leftIcon={<Upload className="h-4 w-4" />} onClick={openCreate}>Upload Resource</Button>
      </div>

      <Card className="mb-5 p-4">
        <div className="grid gap-3 md:grid-cols-[1.4fr_repeat(4,1fr)]">
          <Input placeholder="Search resources" value={filters.q} leftIcon={<Search className="h-4 w-4" />} onChange={(event) => setFilters((prev) => ({ ...prev, q: event.target.value }))} />
          <Select value={filters.product_id} onChange={(event) => setFilters((prev) => ({ ...prev, product_id: event.target.value }))}>
            <option value="">All products</option>
            {products.map((product) => <option key={product.id} value={product.id}>{product.name}</option>)}
          </Select>
          <Select value={filters.resource_type} onChange={(event) => setFilters((prev) => ({ ...prev, resource_type: event.target.value }))}>
            <option value="">All types</option>
            {resourceTypes.map((type) => <option key={type} value={type}>{type}</option>)}
          </Select>
          <Select value={filters.status} onChange={(event) => setFilters((prev) => ({ ...prev, status: event.target.value }))}>
            <option value="">Any status</option>
            <option value="published">Published</option>
            <option value="draft">Draft</option>
            <option value="archived">Archived</option>
          </Select>
          <Select value={filters.access_type} onChange={(event) => setFilters((prev) => ({ ...prev, access_type: event.target.value }))}>
            <option value="">Any access</option>
            <option value="public">Public</option>
            <option value="purchase_required">Purchase required</option>
          </Select>
        </div>
      </Card>

      <Card className="overflow-hidden">
        {resources.length === 0 ? (
          <EmptyState icon={<FileUp className="h-7 w-7" />} title="No resources yet" description="Upload your first supporting file and paste its stable link into an ebook." action={<Button onClick={openCreate}>Upload Resource</Button>} />
        ) : (
          <div className="overflow-x-auto">
            <table className="w-full text-left text-sm">
              <thead className="border-b border-ink-100 bg-ink-50 text-xs uppercase text-ink-400">
                <tr>
                  <th className="px-4 py-3">Resource</th>
                  <th className="px-4 py-3">Type</th>
                  <th className="px-4 py-3">Product</th>
                  <th className="px-4 py-3">Version</th>
                  <th className="px-4 py-3">Access</th>
                  <th className="px-4 py-3">Status</th>
                  <th className="px-4 py-3">Downloads</th>
                  <th className="px-4 py-3">Updated</th>
                  <th className="px-4 py-3 text-right">Actions</th>
                </tr>
              </thead>
              <tbody className="divide-y divide-ink-100">
                {resources.map((resource) => (
                  <tr key={resource.id}>
                    <td className="px-4 py-3">
                      <p className="font-semibold text-ink-900">{resource.title}</p>
                      <p className="font-mono text-xs text-ink-400">/r/{resource.slug}</p>
                    </td>
                    <td className="px-4 py-3 text-ink-600">{resource.resource_type}</td>
                    <td className="px-4 py-3 text-ink-600">{resource.products?.map((product) => product.name).join(', ') || 'Unassigned'}</td>
                    <td className="px-4 py-3 text-ink-600">v{resource.version}</td>
                    <td className="px-4 py-3"><Badge tone={resource.access_type === 'public' ? 'success' : 'warning'}>{resource.access_type === 'public' ? 'Public' : 'Purchase'}</Badge></td>
                    <td className="px-4 py-3"><Badge tone={statusTone(resource.status)}>{resource.status}</Badge></td>
                    <td className="px-4 py-3 text-ink-600">{resource.download_count.toLocaleString()}</td>
                    <td className="px-4 py-3 text-ink-500">{new Date(resource.updated_at).toLocaleDateString()}</td>
                    <td className="px-4 py-3">
                      <div className="flex justify-end gap-1">
                        <Button size="icon" variant="ghost" title="Copy link" onClick={() => copyLink(resource.slug)}><Copy className="h-4 w-4" /></Button>
                        <Button size="icon" variant="ghost" title="Open resource" onClick={() => window.open(`/r/${resource.slug}`, '_blank', 'noopener,noreferrer')}><ExternalLink className="h-4 w-4" /></Button>
                        <Button size="icon" variant="ghost" title="Edit resource" onClick={() => openEdit(resource)}><Edit className="h-4 w-4" /></Button>
                        {resource.status !== 'archived' && <Button size="sm" variant="outline" onClick={() => archive(resource)}>Archive</Button>}
                      </div>
                    </td>
                  </tr>
                ))}
              </tbody>
            </table>
          </div>
        )}
      </Card>

      <ResourceModal
        open={modalOpen}
        editing={editing}
        form={form}
        products={products}
        saving={saving}
        setForm={setForm}
        onClose={() => setModalOpen(false)}
        onSave={save}
      />
    </div>
  );
}

function ResourceModal({
  open,
  editing,
  form,
  products,
  saving,
  setForm,
  onClose,
  onSave,
}: {
  open: boolean;
  editing: AdminResource | null;
  form: ResourceForm;
  products: AdminProduct[];
  saving: boolean;
  setForm: (updater: ResourceForm | ((prev: ResourceForm) => ResourceForm)) => void;
  onClose: () => void;
  onSave: () => void;
}) {
  const selectedProducts = useMemo(() => new Set(form.product_ids), [form.product_ids]);
  const setField = <K extends keyof ResourceForm>(key: K, value: ResourceForm[K]) => setForm((prev) => ({ ...prev, [key]: value }));

  return (
    <Modal
      open={open}
      onClose={onClose}
      title={editing ? 'Edit Resource' : 'Upload Resource'}
      description={editing ? `Stable link: /r/${editing.slug}` : 'Create a permanent resource link for ebooks.'}
      size="lg"
      footer={<><Button variant="outline" onClick={onClose}>Cancel</Button><Button loading={saving} onClick={onSave}>{editing ? 'Save Changes' : 'Upload Resource'}</Button></>}
    >
      <div className="space-y-4">
        <div className="grid gap-4 sm:grid-cols-2">
          <Input label="Title" value={form.title} onChange={(event) => setField('title', event.target.value)} />
          <Input label="Slug" hint="Lowercase words separated by hyphens" value={form.slug} onChange={(event) => setField('slug', event.target.value)} />
        </div>
        <Textarea label="Description" value={form.description} onChange={(event) => setField('description', event.target.value)} rows={4} />
        <div className="grid gap-4 sm:grid-cols-2">
          <Select label="Resource Type" value={form.resource_type} onChange={(event) => setField('resource_type', event.target.value)}>
            {resourceTypes.map((type) => <option key={type} value={type}>{type}</option>)}
          </Select>
          <Select label="Source" value={form.source_type} onChange={(event) => setField('source_type', event.target.value as AdminResourceSource)}>
            <option value="uploaded_file">Uploaded file</option>
            <option value="external_url">External URL</option>
          </Select>
        </div>
        {form.source_type === 'external_url' ? (
          <Input label="External URL" placeholder="https://docs.google.com/..." value={form.external_url} onChange={(event) => setField('external_url', event.target.value)} />
        ) : (
          <Input label={editing ? 'Replace file' : 'File'} type="file" onChange={(event) => setField('file', event.target.files?.[0] || null)} hint={editing ? 'Leave empty to keep the current file.' : 'Files are stored privately and delivered through the resource route.'} />
        )}
        <div>
          <label className="mb-1.5 block text-sm font-medium text-ink-700">Associated Products</label>
          <div className="grid max-h-40 gap-2 overflow-y-auto rounded-xl border border-ink-200 bg-white p-3 sm:grid-cols-2">
            {products.map((product) => (
              <label key={product.id} className="flex items-center gap-2 text-sm text-ink-700">
                <input
                  type="checkbox"
                  className="h-4 w-4 rounded border-ink-300 text-brand-600"
                  checked={selectedProducts.has(product.id)}
                  onChange={(event) => {
                    setField('product_ids', event.target.checked
                      ? [...form.product_ids, product.id]
                      : form.product_ids.filter((id) => id !== product.id));
                  }}
                />
                <span className="truncate">{product.name}</span>
              </label>
            ))}
          </div>
        </div>
        <div className="grid gap-4 sm:grid-cols-3">
          <Select label="Access" value={form.access_type} onChange={(event) => setField('access_type', event.target.value as AdminResourceAccess)}>
            <option value="public">Public</option>
            <option value="purchase_required">Purchase Required</option>
          </Select>
          <Input label="Version" value={form.version} onChange={(event) => setField('version', event.target.value)} />
          <Select label="Status" value={form.status} onChange={(event) => setField('status', event.target.value as AdminResourceStatus)}>
            <option value="draft">Draft</option>
            <option value="published">Published</option>
            <option value="archived">Archived</option>
          </Select>
        </div>
      </div>
    </Modal>
  );
}

function statusTone(status: AdminResourceStatus): 'success' | 'warning' | 'neutral' {
  if (status === 'published') return 'success';
  if (status === 'archived') return 'warning';
  return 'neutral';
}
