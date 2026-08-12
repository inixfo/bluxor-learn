import { useEffect, useState } from 'react';
import { Link } from 'react-router-dom';
import { Filter, MoreHorizontal, Plus, Search } from 'lucide-react';
import { Badge, type Tone } from '@/components/ui/Badge';
import { Button } from '@/components/ui/Button';
import { Card, EmptyState } from '@/components/ui/Card';
import { Input, Select } from '@/components/ui/Input';
import { formatBDT } from '@/data/store';
import { displayMinor, getAdminProducts, type AdminProduct } from '@/services/api/admin';

const statusTone: Record<string, Tone> = { published: 'success', draft: 'warning', archived: 'neutral' };

export default function AdminProducts() {
  const [query, setQuery] = useState('');
  const [statusFilter, setStatusFilter] = useState('');
  const [typeFilter, setTypeFilter] = useState('');
  const [products, setProducts] = useState<AdminProduct[]>([]);
  const [loading, setLoading] = useState(true);

  useEffect(() => {
    setLoading(true);
    getAdminProducts({ q: query, status: statusFilter, type: typeFilter })
      .then(setProducts)
      .finally(() => setLoading(false));
  }, [query, statusFilter, typeFilter]);

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
        <Select value={statusFilter} onChange={(e) => setStatusFilter(e.target.value)} className="sm:w-40">
          <option value="">All Status</option>
          <option value="published">Published</option>
          <option value="draft">Draft</option>
          <option value="archived">Archived</option>
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
                  <tr key={product.id} className="border-b border-ink-50 last:border-0 hover:bg-ink-50/30">
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
                    <td className="px-4 py-3"><Badge tone={statusTone[product.status] || 'neutral'}>{product.status}</Badge></td>
                    <td className="px-4 py-3 font-semibold text-ink-900">{formatBDT(displayMinor(product.sale_price_minor || product.regular_price_minor))}</td>
                    <td className="px-4 py-3 text-ink-600">-</td>
                    <td className="px-4 py-3 font-semibold text-ink-900">-</td>
                    <td className="px-4 py-3 text-ink-400">{new Date(product.updated_at).toLocaleDateString()}</td>
                    <td className="px-4 py-3">
                      <Link to={`/admin/products/${product.id}/edit`}>
                        <Button size="icon" variant="ghost"><MoreHorizontal className="h-4 w-4" /></Button>
                      </Link>
                    </td>
                  </tr>
                ))}
              </tbody>
            </table>
          </div>
        )}
      </Card>
    </div>
  );
}
