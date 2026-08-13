import { useEffect, useMemo, useState } from 'react';
import { useParams, useSearchParams } from 'react-router-dom';
import { PackageX, Search } from 'lucide-react';
import { Input, Select } from '@/components/ui/Input';
import { Badge } from '@/components/ui/Badge';
import { ProductCard } from '@/components/commerce/ProductCard';
import { EmptyState } from '@/components/ui/Card';
import { getCategories, getProducts, type ApiProduct } from '@/services/api/catalog';
import type { Category } from '@/data/store';

const sortOptions = [
  { value: 'popular', label: 'Most Popular' },
  { value: 'newest', label: 'Newest' },
  { value: 'price-low', label: 'Price: Low to High' },
  { value: 'price-high', label: 'Price: High to Low' },
];

export default function Products() {
  const [params, setParams] = useSearchParams();
  const { slug: categorySlug = '' } = useParams();
  const [query, setQuery] = useState(params.get('q') || '');
  const [view, setView] = useState<'grid' | 'categories'>(params.get('view') === 'categories' ? 'categories' : 'grid');
  const [products, setProducts] = useState<ApiProduct[]>([]);
  const [categories, setCategories] = useState<Category[]>([]);
  const [loading, setLoading] = useState(true);
  const [loadError, setLoadError] = useState('');

  const sort = params.get('sort') || 'popular';
  const categoryFilter = params.get('category') || categorySlug;
  const typeFilter = params.get('filter') || '';

  useEffect(() => {
    const timer = window.setTimeout(() => {
      const backendParams = new URLSearchParams();
      if (query.trim()) backendParams.set('q', query.trim());
      if (categoryFilter) backendParams.set('category', categoryFilter);
      if (sort) backendParams.set('sort', sort);
      if (typeFilter && typeFilter !== 'bundle') backendParams.set('type', typeFilter);

      setLoading(true);
      setLoadError('');

      Promise.all([getProducts(backendParams), getCategories()])
        .then(([nextProducts, nextCategories]) => {
          setProducts(typeFilter === 'bundle' ? [] : nextProducts);
          setCategories(nextCategories);
        })
        .catch(() => {
          setProducts([]);
          setCategories([]);
          setLoadError('Products are unavailable right now. Please try again in a moment.');
        })
        .finally(() => setLoading(false));
    }, 250);

    return () => window.clearTimeout(timer);
  }, [categoryFilter, query, sort, typeFilter]);

  const visibleProducts = useMemo(() => [...products], [products]);

  const updateParam = (key: string, value: string) => {
    const next = new URLSearchParams(params);
    if (value) next.set(key, value);
    else next.delete(key);
    setParams(next);
  };

  const clearFilters = () => {
    setQuery('');
    setParams(new URLSearchParams());
  };

  if (view === 'categories') {
    return (
      <div className="container-page py-10">
        <h1 className="font-display text-3xl font-bold text-ink-900">All Categories</h1>
        <p className="mt-1 text-ink-500">{loading ? 'Loading categories...' : 'Browse resources by topic.'}</p>
        <div className="mt-8 grid grid-cols-1 gap-5 sm:grid-cols-2 lg:grid-cols-3">
          {categories.map((cat) => (
            <button
              key={cat.id}
              onClick={() => { updateParam('category', cat.id); setView('grid'); }}
              className="group flex items-center gap-4 rounded-2xl border border-ink-200/60 bg-white p-5 text-left transition-all hover:-translate-y-0.5 hover:border-brand-200 hover:shadow-card"
            >
              <div className={`flex h-14 w-14 items-center justify-center rounded-2xl bg-${cat.color}-100 text-${cat.color}-600`}>
                <cat.icon className="h-7 w-7" />
              </div>
              <div className="flex-1">
                <h3 className="text-lg font-bold text-ink-900 group-hover:text-brand-700">{cat.name}</h3>
                <p className="text-sm text-ink-500">{cat.count} products</p>
              </div>
            </button>
          ))}
        </div>
        {!loading && categories.length === 0 && (
          <div className="mt-8">
            <EmptyState icon={<PackageX className="h-7 w-7" />} title="No categories available" description={loadError || 'Categories will appear here after products are published.'} />
          </div>
        )}
      </div>
    );
  }

  return (
    <div className="container-page py-10">
      <div className="mb-8">
        <h1 className="font-display text-3xl font-bold text-ink-900">{categorySlug ? `${categories.find((category) => category.id === categorySlug)?.name || 'Category'} Products` : 'All Products'}</h1>
        <p className="mt-1 text-ink-500">Practical resources to help you learn and earn.</p>
      </div>

      <div className="mb-6 flex flex-col gap-3 sm:flex-row sm:items-center">
        <div className="flex-1">
          <Input placeholder="Search products..." value={query} onChange={(e) => setQuery(e.target.value)} leftIcon={<Search className="h-4 w-4" />} />
        </div>
        <div className="flex gap-3">
          <Select value={categoryFilter} onChange={(e) => updateParam('category', e.target.value)} className="min-w-[160px]">
            <option value="">All Categories</option>
            {categories.map((c) => <option key={c.id} value={c.id}>{c.name}</option>)}
          </Select>
          <Select value={sort} onChange={(e) => updateParam('sort', e.target.value)} className="min-w-[160px]">
            {sortOptions.map((o) => <option key={o.value} value={o.value}>{o.label}</option>)}
          </Select>
        </div>
      </div>

      {(categoryFilter || typeFilter) && (
        <div className="mb-4 flex flex-wrap items-center gap-2">
          <span className="text-sm text-ink-400">Filters:</span>
          {categoryFilter && (
            <Badge tone="brand">
              {categories.find((c) => c.id === categoryFilter)?.name || categoryFilter}
              <button onClick={() => updateParam('category', '')} className="ml-1">x</button>
            </Badge>
          )}
          {typeFilter === 'bundle' && (
            <Badge tone="violet">
              Bundles
              <button onClick={() => updateParam('filter', '')} className="ml-1">x</button>
            </Badge>
          )}
        </div>
      )}

      <div className="mb-4 flex items-center justify-between gap-3">
        <p className="text-sm text-ink-500">{loading ? 'Loading products...' : `${visibleProducts.length} products`}</p>
        {loadError && <p className="text-xs text-danger-600">{loadError}</p>}
      </div>

      {visibleProducts.length === 0 ? (
        <EmptyState
          icon={<PackageX className="h-7 w-7" />}
          title={loadError ? 'Products unavailable' : typeFilter === 'bundle' ? 'Bundle listing is not available yet' : 'No products found'}
          description={loadError || (typeFilter === 'bundle' ? 'Bundles can still be opened from product and landing offers once exposed by the public API.' : "Try adjusting your search or filters.")}
          action={<button onClick={clearFilters} className="text-sm font-medium text-brand-600 hover:text-brand-700">Clear all filters</button>}
        />
      ) : (
        <div className="grid grid-cols-1 gap-5 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4">
          {visibleProducts.map((p) => <ProductCard key={p.id} product={p} />)}
        </div>
      )}
    </div>
  );
}
