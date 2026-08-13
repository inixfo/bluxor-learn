import { useEffect, useRef, useState } from 'react';
import { Link, useNavigate, useParams } from 'react-router-dom';
import { ArrowLeft, Eye, FileText, Image as ImageIcon, Save, Upload } from 'lucide-react';
import { Badge } from '@/components/ui/Badge';
import { Button } from '@/components/ui/Button';
import { Card } from '@/components/ui/Card';
import { Input, Select, Textarea } from '@/components/ui/Input';
import { useToast } from '@/components/ui/Toast';
import { createAdminProduct, getAdminCategories, getAdminProduct, publishAdminProduct, updateAdminProduct, uploadAdminProductFile, type AdminCategory, type AdminProductFile } from '@/services/api/admin';

const tabs = ['Basic Info', 'Pricing', 'Media', 'Digital Files', 'Bundles', 'SEO', 'Status'];

export default function AdminProductEditor() {
  const toast = useToast();
  const navigate = useNavigate();
  const { id } = useParams();
  const [activeTab, setActiveTab] = useState('Basic Info');
  const [saving, setSaving] = useState(false);
  const [files, setFiles] = useState<AdminProductFile[]>([]);
  const [categories, setCategories] = useState<AdminCategory[]>([]);
  const [coverFile, setCoverFile] = useState<File | null>(null);
  const [removeCover, setRemoveCover] = useState(false);
  const [uploading, setUploading] = useState(false);
  const [form, setForm] = useState({
    name: '',
    slug: '',
    product_type: 'ebook',
    regular_price: '1490',
    sale_price: '',
    currency: 'BDT',
    status: 'draft' as 'draft' | 'published' | 'archived',
    short_description: '',
    description: '',
    cover_image_path: '',
    category_id: '',
  });

  useEffect(() => {
    getAdminCategories().then(setCategories).catch(() => undefined);
  }, []);

  useEffect(() => {
    if (!id) return;
    getAdminProduct(id).then((product) => {
      setForm({
        name: product.name,
        slug: product.slug,
        product_type: product.product_type,
        regular_price: String(Math.round(product.regular_price_minor / 100)),
        sale_price: product.sale_price_minor ? String(Math.round(product.sale_price_minor / 100)) : '',
        currency: product.currency,
        status: product.status,
        short_description: product.short_description || '',
        description: product.description || '',
        cover_image_path: product.cover_image_path || '',
        category_id: product.category_id ? String(product.category_id) : '',
      });
      setFiles(product.files || []);
    });
  }, [id]);

  const updateField = (field: keyof typeof form, value: string) => {
    setForm((prev) => ({ ...prev, [field]: value }));
  };

  const payload = () => ({
    name: form.name,
    slug: form.slug,
    product_type: form.product_type,
    regular_price_minor: Math.round(Number(form.regular_price || 0) * 100),
    sale_price_minor: form.sale_price ? Math.round(Number(form.sale_price) * 100) : null,
    currency: form.currency,
    status: form.status,
    short_description: form.short_description,
    description: form.description,
    cover_image_path: form.cover_image_path,
    category_id: form.category_id ? Number(form.category_id) : null,
    cover_image: coverFile,
    remove_cover_image: removeCover,
  });

  const save = async () => {
    setSaving(true);
    try {
      const product = id ? await updateAdminProduct(id, payload()) : await createAdminProduct(payload());
      setForm((prev) => ({ ...prev, cover_image_path: product.cover_image_path || '' }));
      setCoverFile(null);
      setRemoveCover(false);
      toast({ type: 'success', title: 'Product saved' });
      if (!id) navigate(`/admin/products/${product.id}/edit`, { replace: true });
    } catch {
      toast({ type: 'error', title: 'Save failed', message: 'Check the required fields and try again.' });
    } finally {
      setSaving(false);
    }
  };

  const publish = async () => {
    setSaving(true);
    try {
      const product = id
        ? await publishAdminProduct(id)
        : await createAdminProduct({ ...payload(), status: 'published' });
      setForm((prev) => ({ ...prev, status: product.status }));
      toast({ type: 'success', title: 'Product published' });
      if (!id) navigate(`/admin/products/${product.id}/edit`, { replace: true });
    } catch {
      toast({ type: 'error', title: 'Publish failed' });
    } finally {
      setSaving(false);
    }
  };

  const uploadFile = async (file: File) => {
    if (!id) {
      toast({ type: 'error', title: 'Save product first', message: 'Create the product before uploading protected files.' });
      return;
    }

    setUploading(true);
    try {
      const uploaded = await uploadAdminProductFile(id, file);
      setFiles((prev) => [...prev, uploaded]);
      toast({ type: 'success', title: 'File uploaded' });
    } catch {
      toast({ type: 'error', title: 'Upload failed', message: 'Check file size and type, then try again.' });
    } finally {
      setUploading(false);
    }
  };

  return (
    <div>
      <div className="mb-6 flex items-center justify-between">
        <div>
          <Link to="/admin/products" className="mb-2 flex items-center gap-1.5 text-sm text-ink-400 hover:text-brand-600">
            <ArrowLeft className="h-4 w-4" /> Products
          </Link>
          <h1 className="font-display text-2xl font-bold text-ink-900">{id ? 'Edit Product' : 'Create Product'}</h1>
        </div>
        <div className="flex gap-2">
          <Button variant="outline" leftIcon={<Eye className="h-4 w-4" />} disabled={!form.slug} onClick={() => window.open(`/p/${form.slug}`, '_blank', 'noopener,noreferrer')}>Preview</Button>
          <Button leftIcon={<Save className="h-4 w-4" />} loading={saving} onClick={save}>Save</Button>
        </div>
      </div>

      <div className="mb-6 flex gap-1 overflow-x-auto border-b border-ink-100 no-scrollbar">
        {tabs.map((tab) => (
          <button
            key={tab}
            onClick={() => setActiveTab(tab)}
            className={`shrink-0 border-b-2 px-4 py-2.5 text-sm font-medium transition-colors ${
              activeTab === tab ? 'border-brand-600 text-brand-700' : 'border-transparent text-ink-500 hover:text-ink-800'
            }`}
          >
            {tab}
          </button>
        ))}
      </div>

      <div className="grid gap-5 lg:grid-cols-3">
        <div className="lg:col-span-2">
          {activeTab === 'Basic Info' && (
            <Card className="p-6">
              <h2 className="mb-4 text-base font-bold text-ink-900">Basic Information</h2>
              <div className="space-y-4">
                <Input label="Product name" placeholder="e.g. AI Automation with n8n" value={form.name} onChange={(event) => updateField('name', event.target.value)} />
                <Input label="Slug" placeholder="ai-automation-n8n" hint="URL-friendly identifier" value={form.slug} onChange={(event) => updateField('slug', event.target.value)} />
                <Select label="Type" value={form.product_type} onChange={(event) => updateField('product_type', event.target.value)}>
                  <option value="ebook">Ebook</option>
                  <option value="guide">Guide</option>
                  <option value="template">Template</option>
                  <option value="toolkit">Toolkit</option>
                  <option value="bundle">Bundle</option>
                </Select>
                <Input label="Short description" placeholder="One-line summary shown on cards" value={form.short_description} onChange={(event) => updateField('short_description', event.target.value)} />
                <Select label="Category" value={form.category_id} onChange={(event) => updateField('category_id', event.target.value)}>
                  <option value="">Uncategorized</option>
                  {categories.map((category) => <option key={category.id} value={category.id}>{category.name}</option>)}
                </Select>
                <Textarea label="Full description" placeholder="Detailed product description" value={form.description} onChange={(event) => updateField('description', event.target.value)} />
              </div>
            </Card>
          )}

          {activeTab === 'Pricing' && (
            <Card className="p-6">
              <h2 className="mb-4 text-base font-bold text-ink-900">Pricing</h2>
              <div className="grid gap-4 sm:grid-cols-2">
                <Input label="Regular price (BDT)" type="number" placeholder="1490" value={form.regular_price} onChange={(event) => updateField('regular_price', event.target.value)} />
                <Input label="Sale price (BDT)" type="number" placeholder="990" hint="Leave empty for no sale" value={form.sale_price} onChange={(event) => updateField('sale_price', event.target.value)} />
              </div>
              <div className="mt-4 rounded-xl bg-ink-50 p-4">
                <p className="text-sm text-ink-600">Final price: <span className="font-bold text-ink-900">BDT {form.sale_price || form.regular_price || 0}</span></p>
                <p className="text-xs text-ink-400">Sale price is optional.</p>
              </div>
            </Card>
          )}

          {activeTab === 'Media' && (
            <Card className="p-6">
              <h2 className="mb-4 text-base font-bold text-ink-900">Media</h2>
              <ProductCoverUpload
                currentUrl={removeCover ? '' : form.cover_image_path}
                file={coverFile}
                onPick={(file) => {
                  setCoverFile(file);
                  setRemoveCover(false);
                }}
                onRemove={() => {
                  setCoverFile(null);
                  setRemoveCover(true);
                  updateField('cover_image_path', '');
                }}
              />
            </Card>
          )}

          {activeTab === 'Digital Files' && (
            <Card className="p-6">
              <h2 className="mb-4 text-base font-bold text-ink-900">Digital Files</h2>
              <ProductFileUploadZone disabled={!id || uploading} uploading={uploading} onUpload={uploadFile} />
              <div className="mt-5 space-y-2">
                {files.map((file) => (
                  <div key={file.id} className="flex items-center justify-between gap-3 rounded-xl border border-ink-100 p-3">
                    <div className="flex items-center gap-3">
                      <div className="flex h-9 w-9 items-center justify-center rounded-lg bg-brand-100 text-brand-600">
                        <FileText className="h-4 w-4" />
                      </div>
                      <div>
                        <p className="text-sm font-semibold text-ink-900">{file.name}</p>
                        <p className="text-xs text-ink-400">{file.file_type} / {formatBytes(file.file_size_bytes)} / v{file.version}</p>
                      </div>
                    </div>
                    <Badge tone={file.status === 'active' ? 'success' : 'neutral'}>{file.status}</Badge>
                  </div>
                ))}
              </div>
            </Card>
          )}

          {activeTab === 'Bundles' && (
            <Card className="p-6">
              <h2 className="mb-4 text-base font-bold text-ink-900">Bundle Associations</h2>
              <p className="text-sm text-ink-500">Bundle editing will be connected in the next admin slice.</p>
            </Card>
          )}

          {activeTab === 'SEO' && (
            <Card className="p-6">
              <h2 className="mb-4 text-base font-bold text-ink-900">SEO Settings</h2>
              <div className="space-y-4">
                <Input label="SEO title" placeholder="Page title for search engines" value={form.name} onChange={(event) => updateField('name', event.target.value)} />
                <Textarea label="SEO description" placeholder="Meta description" value={form.short_description} onChange={(event) => updateField('short_description', event.target.value)} />
                <Input label="OG Image URL" placeholder="https://..." value={form.cover_image_path} onChange={(event) => updateField('cover_image_path', event.target.value)} />
              </div>
            </Card>
          )}

          {activeTab === 'Status' && (
            <Card className="p-6">
              <h2 className="mb-4 text-base font-bold text-ink-900">Status</h2>
              <div className="space-y-3">
                <StatusOption label="Published" hint="Visible to customers" checked={form.status === 'published'} onChange={() => updateField('status', 'published')} />
                <StatusOption label="Draft" hint="Hidden from storefront" checked={form.status === 'draft'} onChange={() => updateField('status', 'draft')} />
                <StatusOption label="Archived" hint="Retained for records, hidden from storefront" checked={form.status === 'archived'} onChange={() => updateField('status', 'archived')} />
              </div>
            </Card>
          )}
        </div>

        <div className="space-y-4">
          <Card className="p-5">
            <h3 className="text-sm font-bold text-ink-900">Publish</h3>
            <div className="mt-3 space-y-2 text-sm">
              <div className="flex justify-between"><span className="text-ink-400">Status</span><Badge tone={form.status === 'published' ? 'success' : 'warning'}>{form.status}</Badge></div>
              <div className="flex justify-between"><span className="text-ink-400">Visibility</span><span className="text-ink-700">Public</span></div>
              <div className="flex justify-between"><span className="text-ink-400">Currency</span><span className="text-ink-700">{form.currency}</span></div>
            </div>
            <Button className="mt-4 w-full" loading={saving} onClick={publish}>Publish</Button>
          </Card>
          <Card className="p-5">
            <h3 className="text-sm font-bold text-ink-900">Product URL</h3>
            <p className="mt-2 truncate rounded-lg bg-ink-50 px-3 py-2 text-xs text-ink-500">learn.bluxor.com/p/{form.slug || 'product-slug'}</p>
          </Card>
        </div>
      </div>
    </div>
  );
}

function StatusOption({ label, hint, checked, onChange }: { label: string; hint: string; checked: boolean; onChange: () => void }) {
  return (
    <label className="flex cursor-pointer items-center gap-3 rounded-xl border border-ink-200 p-4 hover:bg-ink-50">
      <input type="radio" name="status" checked={checked} onChange={onChange} className="h-4 w-4 text-brand-600" />
      <div><p className="text-sm font-semibold text-ink-900">{label}</p><p className="text-xs text-ink-400">{hint}</p></div>
    </label>
  );
}

function ProductCoverUpload({ currentUrl, file, onPick, onRemove }: { currentUrl: string; file: File | null; onPick: (file: File) => void; onRemove: () => void }) {
  const inputRef = useRef<HTMLInputElement>(null);
  const preview = file ? URL.createObjectURL(file) : currentUrl;

  return (
    <div>
      <label className="mb-1.5 block text-sm font-medium text-ink-700">Primary cover image</label>
      <div className="flex flex-col items-center justify-center rounded-xl border-2 border-dashed border-ink-200 bg-ink-50/50 px-6 py-8 text-center transition-colors hover:border-brand-300 hover:bg-brand-50/30">
        <input ref={inputRef} type="file" accept="image/jpeg,image/png,image/webp" className="hidden" onChange={(event) => {
          const next = event.target.files?.[0];
          if (next) onPick(next);
        }} />
        <div className="flex h-12 w-12 items-center justify-center rounded-xl bg-white text-brand-600 shadow-sm">
          {preview ? <img src={preview} alt="" className="h-12 w-12 rounded-xl object-cover" /> : <ImageIcon className="h-6 w-6" />}
        </div>
        <p className="mt-3 text-sm font-medium text-ink-700">{file ? file.name : preview ? 'Cover image selected' : 'Upload JPEG, PNG, or WEBP'}</p>
        <p className="mt-1 text-xs text-ink-400">Maximum 5 MB. Stored on persistent public media storage.</p>
        <div className="mt-3 flex gap-2">
          <Button size="sm" variant="outline" leftIcon={<Upload className="h-4 w-4" />} onClick={() => inputRef.current?.click()}>Choose image</Button>
          {preview && <Button size="sm" variant="ghost" onClick={onRemove}>Remove</Button>}
        </div>
      </div>
    </div>
  );
}

function ProductFileUploadZone({ disabled, uploading, onUpload }: { disabled: boolean; uploading: boolean; onUpload: (file: File) => void }) {
  const inputRef = useRef<HTMLInputElement>(null);

  const pick = (file?: File) => {
    if (file) onUpload(file);
  };

  return (
    <div>
      <label className="mb-1.5 block text-sm font-medium text-ink-700">Protected downloadable files</label>
      <div
        onDragOver={(event) => event.preventDefault()}
        onDrop={(event) => {
          event.preventDefault();
          if (!disabled) pick(event.dataTransfer.files[0]);
        }}
        className="flex flex-col items-center justify-center rounded-xl border-2 border-dashed border-ink-200 bg-ink-50/50 px-6 py-8 text-center transition-colors hover:border-brand-300 hover:bg-brand-50/30"
      >
        <input ref={inputRef} type="file" className="hidden" onChange={(event) => pick(event.target.files?.[0])} />
        <div className="flex h-12 w-12 items-center justify-center rounded-xl bg-white text-brand-600 shadow-sm">
          <Upload className="h-6 w-6" />
        </div>
        <p className="mt-3 text-sm font-medium text-ink-700">{uploading ? 'Uploading...' : 'Drag and drop or browse'}</p>
        <p className="mt-1 text-xs text-ink-400">Files are stored on the private disk and delivered only after entitlement checks.</p>
        <Button size="sm" variant="outline" className="mt-3" disabled={disabled} loading={uploading} onClick={() => inputRef.current?.click()}>
          Browse files
        </Button>
        {!disabled || uploading ? null : <p className="mt-2 text-xs text-warning-600">Save the product before uploading files.</p>}
      </div>
    </div>
  );
}

function formatBytes(bytes: number): string {
  if (bytes >= 1024 * 1024) return `${Math.round(bytes / 1024 / 1024)} MB`;
  if (bytes >= 1024) return `${Math.round(bytes / 1024)} KB`;
  return `${bytes} B`;
}
