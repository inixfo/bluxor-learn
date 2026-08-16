import { useCallback, useEffect, useState } from 'react';
import { Link, useSearchParams } from 'react-router-dom';
import { Archive, Edit, Eye, Filter, Plus, RotateCcw, Search, Trash2 } from 'lucide-react';
import { Badge, type Tone } from '@/components/ui/Badge';
import { Button } from '@/components/ui/Button';
import { Card, EmptyState } from '@/components/ui/Card';
import { Input, Select } from '@/components/ui/Input';
import { Modal } from '@/components/ui/Modal';
import { useToast } from '@/components/ui/Toast';
import { formatBDT } from '@/data/store';
import {
  archiveAdminProduct,
  deleteAdminProduct,
  displayMinor,
  getAdminProducts,
  restoreAdminProduct,
  restoreDeletedAdminProduct,
  type AdminProduct,
} from '@/services/api/admin';

const statusTone: Record<string, Tone> = { published: 'success', draft: 'warning', archived: 'neutral', deleted: 'danger' };

export default function AdminProducts() {
  const toast = useToast();
  const [params, setParams] = useSearchParams();
  const [query, setQuery] = useState('');
  const [statusFilter, setStatusFilter] = useState(params.get('status') || '');
  const [typeFilter, setTypeFilter] = useState('');
  const [products, setProducts] = useState<AdminProduct[]>([]);
  const [loading, setLoading] = useState(true);
  const [archiveTarget, setArchiveTarget] = useState<AdminProduct | null>(null);
  const [deleteTarget, setDeleteTarget] = useState<AdminProduct | null>(null);

  const load = useCallback(() => {
    setLoading(true);
    getAdminProducts({ q: query, status: statusFilter, type: typeFilter })
      .then(setProducts)
      .finally(() => setLoading(false));
  }, [query, statusFilter, typeFilter]);

  useEffect(() => {
    load();
  }, [load]);

  useEffect(() => {
    const nextStatus = params.get('status') || '';
    setStatusFilter((current) => (current === nextStatus ? current : nextStatus));
  }, [params]);

  const updateStatus = (status: string) => {
    setStatusFilter(status);
    const next = new URLSearchParams(params);
    if (status) next.set('status', status);
    else next.delete('status');
    setParams(next, { replace: true });
  };

  const archiveProduct = async () => {
    if (!archiveTarget) return;
    try {
      await archiveAdminProduct(String(archiveTarget.id));
      toast({ type: 'success', title: 'Product archived successfully.' });
      setArchiveTarget(null);
      load();
    } catch {
      toast({ type: 'error', title: 'Unable to archive product. Please try again.' });
    }
  };

  const restoreProduct = async (product: AdminProduct) => {
    try {
      if (product.deleted_at) {
        await restoreDeletedAdminProduct(String(product.id));
      } else {
        await restoreAdminProduct(String(product.id));
      }
      toast({ type: 'success', title: product.deleted_at ? 'Product restored from trash.' : 'Product restored to draft.' });
      load();
    } catch {
      toast({ type: 'error', title: 'Unable to restore product. Please try again.' });
    }
  };

  const deleteProduct = async () => {
    if (!deleteTarget) return;
    try {
      const response = await deleteAdminProduct(String(deleteTarget.id));
      toast({
        type: 'success',
        title: 'Product deleted',
        message: response.active_landing_pages ? `It was connected to ${response.active_landing_pages} landing page${response.active_landing_pages === 1 ? '' : 's'}; checkout offers are now unavailable.` : undefined,
      });
      setDeleteTarget(null);
      load();
    } catch (error) {
      const message = error instanceof Error ? error.message : 'Unable to delete product.';
      toast({ type: 'error', title: 'Delete failed', message });
    }
  };

  return (
    <div>
      <div className="mb-6 flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
        <div>
          <h1 className="font-display text-2xl font-bold text-ink-900">Products</h1>
          <p className="mt-1 text-sm text-ink-500">{loading ? 'Loading products...' : 'Manage your digital products.'}</p>
        </div>
        <Link to="/admin/products/new">
          <Button leftIcon={<Plus className="h-4 w-4" />}>Create Product</Button>
        </Link>
      </div>

      <div className="mb-4 flex flex-col gap-3 sm:flex-row">
        <div className="flex-1">
          <Input placeholder="Search products..." value={query} onChange={(e) => setQuery(e.target.value)} leftIcon={<Search className="h-4 w-4" />} />
        </div>
        <Select value={statusFilter} onChange={(e) => updateStatus(e.target.value)} className="sm:w-40">
          <option value="">All Status</option>
          <option value="published">Published</option>
          <option value="draft">Draft</option>
          <option value="archived">Archived</option>
          <option value="deleted">Deleted</option>
        </Select>
        <Select value={typeFilter} onChange={(e) => setTypeFilter(e.target.value)} className="sm:w-40">
          <option value="">All Types</option>
          <option value="ebook">Ebook</option>
          <option value="guide">Guide</option>
          <option value="template">Template</option>
          <option value="toolkit">Toolkit</option>
          <option value="bundle">Bundle</option>
        </Select>
      </div>

      <Card className="overflow-hidden">
        {products.length === 0 ? (
          <div className="p-6">
            <EmptyState
              icon={<Filter className="h-7 w-7" />}
              title={loading ? 'Loading products' : 'No products found'}
              description={loading ? 'Fetching the latest catalog from the backend.' : 'Try adjusting your filters or create a new product.'}
              action={<Link to="/admin/products/new"><Button>Create Product</Button></Link>}
            />
          </div>
        ) : (
          <div className="overflow-x-auto">
            <table className="w-full text-sm">
              <thead className="border-b border-ink-100 bg-ink-50/50 text-left text-xs text-ink-400">
                <tr>
                  <th className="px-4 py-3 font-medium">Product</th>
                  <th className="px-4 py-3 font-medium">Type</th>
                  <th className="px-4 py-3 font-medium">Status</th>
                  <th className="px-4 py-3 font-medium">Price</th>
                  <th className="px-4 py-3 font-medium">Sales</th>
                  <th className="px-4 py-3 font-medium">Revenue</th>
                  <th className="px-4 py-3 font-medium">Updated</th>
                  <th className="px-4 py-3"></th>
                </tr>
              </thead>
              <tbody>
                {products.map((product) => (
                  <tr key={`${product.id}-${product.deleted_at || 'active'}`} className="border-b border-ink-50 last:border-0 hover:bg-ink-50/30">
                    <td className="px-4 py-3">
                      <div className="flex items-center gap-3">
                        {product.cover_image_path ? (
                          <img src={product.cover_image_path} alt="" className="h-10 w-10 rounded-lg object-cover" />
                        ) : (
                          <div className="flex h-10 w-10 items-center justify-center rounded-lg bg-brand-100 text-xs font-bold text-brand-700">
                            {product.name.slice(0, 2).toUpperCase()}
                          </div>
                        )}
                        <div>
                          <p className="font-semibold text-ink-900">{product.name}</p>
                          <p className="text-xs text-ink-400">{product.category?.name || 'Uncategorized'}</p>
                        </div>
                      </div>
                    </td>
                    <td className="px-4 py-3"><Badge tone="neutral">{product.product_type}</Badge></td>
                    <td className="px-4 py-3"><Badge tone={statusTone[product.deleted_at ? 'deleted' : product.status] || 'neutral'}>{product.deleted_at ? 'deleted' : product.status}</Badge></td>
                    <td className="px-4 py-3 font-semibold text-ink-900">{formatBDT(displayMinor(product.sale_price_minor || product.regular_price_minor))}</td>
                    <td className="px-4 py-3 text-ink-600">-</td>
                    <td className="px-4 py-3 font-semibold text-ink-900">-</td>
                    <td className="px-4 py-3 text-ink-400">{new Date(product.updated_at).toLocaleDateString()}</td>
                    <td className="px-4 py-3">
                      <div className="flex justify-end gap-1">
                        {!product.deleted_at && product.status === 'published' && (
                          <Button size="icon" variant="ghost" title="View" onClick={() => window.open(`/p/${product.slug}`, '_blank', 'noopener,noreferrer')}><Eye className="h-4 w-4" /></Button>
                        )}
                        {!product.deleted_at && (
                          <Link to={`/admin/products/${product.id}/edit`}>
                            <Button size="icon" variant="ghost" title="Edit"><Edit className="h-4 w-4" /></Button>
                          </Link>
                        )}
                        {!product.deleted_at && product.status !== 'archived' && (
                          <Button size="icon" variant="ghost" title="Archive" onClick={() => setArchiveTarget(product)}><Archive className="h-4 w-4" /></Button>
                        )}
                        {!product.deleted_at && product.status === 'archived' && (
                          <Button size="icon" variant="ghost" title="Restore to draft" onClick={() => restoreProduct(product)}><RotateCcw className="h-4 w-4" /></Button>
                        )}
                        {product.deleted_at ? (
                          <Button size="sm" variant="outline" leftIcon={<RotateCcw className="h-4 w-4" />} onClick={() => restoreProduct(product)}>Restore</Button>
                        ) : (
                          <Button size="icon" variant="ghost" title="Delete" onClick={() => setDeleteTarget(product)}><Trash2 className="h-4 w-4" /></Button>
                        )}
                      </div>
                    </td>
                  </tr>
                ))}
              </tbody>
            </table>
          </div>
        )}
      </Card>

      <Modal
        open={!!archiveTarget}
        onClose={() => setArchiveTarget(null)}
        title="Archive product?"
        description="This product will be hidden from the public catalog. Existing customer purchases will remain available."
        footer={<><Button variant="outline" onClick={() => setArchiveTarget(null)}>Cancel</Button><Button variant="secondary" leftIcon={<Archive className="h-4 w-4" />} onClick={archiveProduct}>Archive</Button></>}
      >
        <p className="text-sm text-ink-600">{archiveTarget?.name}</p>
      </Modal>

      <Modal
        open={!!deleteTarget}
        onClose={() => setDeleteTarget(null)}
        title="Delete product?"
        description={deleteTarget ? `"${deleteTarget.name}" will be removed from active product management and the public storefront. Historical order/payment records will be preserved.` : undefined}
        footer={<><Button variant="outline" onClick={() => setDeleteTarget(null)}>Cancel</Button><Button variant="destructive" leftIcon={<Trash2 className="h-4 w-4" />} onClick={deleteProduct}>Delete Product</Button></>}
      >
        <p className="text-sm text-ink-600">This is a soft delete. Existing orders, payments, entitlements, and resources are retained.</p>
      </Modal>
    </div>
  );
}
