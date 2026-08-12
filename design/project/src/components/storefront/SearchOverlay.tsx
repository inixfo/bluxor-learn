import { useEffect, useState } from 'react';
import { Link, useNavigate } from 'react-router-dom';
import { Search, X, TrendingUp } from 'lucide-react';
import { ProductCompactCard } from '@/components/commerce/ProductCard';
import { searchProducts, type ApiProduct } from '@/services/api/catalog';

const trending = ['n8n', 'Bug Bounty', 'Freelancing', 'Cybersecurity'];

export function SearchOverlay({ open, onClose }: { open: boolean; onClose: () => void }) {
  const [query, setQuery] = useState('');
  const [results, setResults] = useState<ApiProduct[]>([]);
  const [loading, setLoading] = useState(false);
  const navigate = useNavigate();

  useEffect(() => {
    if (!query.trim()) {
      setResults([]);
      return;
    }
    const timer = window.setTimeout(() => {
      setLoading(true);
      searchProducts(query)
        .then((items) => setResults(items.slice(0, 6)))
        .catch(() => setResults([]))
        .finally(() => setLoading(false));
    }, 250);
    return () => window.clearTimeout(timer);
  }, [query]);

  useEffect(() => {
    if (!open) return;
    const onKey = (e: KeyboardEvent) => e.key === 'Escape' && onClose();
    document.addEventListener('keydown', onKey);
    document.body.style.overflow = 'hidden';
    return () => {
      document.removeEventListener('keydown', onKey);
      document.body.style.overflow = '';
    };
  }, [open, onClose]);

  useEffect(() => {
    const onKey = (e: KeyboardEvent) => {
      if ((e.metaKey || e.ctrlKey) && e.key === 'k') {
        e.preventDefault();
        if (!open) {
          onClose();
          setTimeout(() => document.getElementById('global-search')?.focus(), 50);
        }
      }
    };
    document.addEventListener('keydown', onKey);
    return () => document.removeEventListener('keydown', onKey);
  }, [open, onClose]);

  if (!open) return null;

  return (
    <div className="fixed inset-0 z-50 flex items-start justify-center p-4 pt-[10vh]">
      <div className="absolute inset-0 bg-ink-950/40 backdrop-blur-sm animate-fade-in" onClick={onClose} />
      <div className="relative w-full max-w-xl animate-scale-in overflow-hidden rounded-2xl bg-white shadow-lift">
        <div className="flex items-center gap-3 border-b border-ink-100 px-4">
          <Search className="h-5 w-5 text-ink-400" />
          <input
            id="global-search"
            autoFocus
            value={query}
            onChange={(e) => setQuery(e.target.value)}
            placeholder="Search products, categories, tags..."
            className="h-14 flex-1 bg-transparent text-base text-ink-900 placeholder:text-ink-400 focus:outline-none"
            onKeyDown={(e) => e.key === 'Enter' && results[0] && navigate(`/p/${results[0].slug}`)}
          />
          <button onClick={onClose} className="rounded-lg p-1.5 text-ink-400 hover:bg-ink-100">
            <X className="h-5 w-5" />
          </button>
        </div>

        <div className="max-h-[60vh] overflow-y-auto p-2">
          {!query.trim() ? (
            <div className="px-3 py-4">
              <p className="mb-2 flex items-center gap-1.5 text-xs font-semibold uppercase tracking-wide text-ink-400">
                <TrendingUp className="h-3.5 w-3.5" /> Trending
              </p>
              <div className="flex flex-wrap gap-2">
                {trending.map((t) => (
                  <button
                    key={t}
                    onClick={() => setQuery(t)}
                    className="rounded-full bg-ink-100 px-3 py-1.5 text-sm text-ink-700 transition-colors hover:bg-brand-100 hover:text-brand-700"
                  >
                    {t}
                  </button>
                ))}
              </div>
            </div>
          ) : loading ? (
            <p className="px-4 py-8 text-center text-sm text-ink-400">Searching products...</p>
          ) : results.length === 0 ? (
            <p className="px-4 py-8 text-center text-sm text-ink-400">
              No products found for "{query}"
            </p>
          ) : (
            <div className="space-y-1">
              {results.map((p) => (
                <div key={p.id} onClick={onClose}>
                  <ProductCompactCard product={p} />
                </div>
              ))}
            </div>
          )}
        </div>

        <div className="flex items-center justify-between border-t border-ink-100 px-4 py-2.5 text-xs text-ink-400">
          <span>Press Enter to open</span>
          <Link to="/products" onClick={onClose} className="font-medium text-brand-600 hover:text-brand-700">
            Browse all products →
          </Link>
        </div>
      </div>
    </div>
  );
}
