import { useEffect, useMemo, useState } from 'react';
import { Link, useSearchParams } from 'react-router-dom';
import { ArrowRight, CreditCard, Download, Package, RefreshCcw, Search, ShoppingCart, UserCircle } from 'lucide-react';
import { Button } from '@/components/ui/Button';
import { Input } from '@/components/ui/Input';
import { getHelpCenter, type HelpArticle, type HelpCategory } from '@/services/api/support';

const icons = { 'shopping-cart': ShoppingCart, download: Download, user: UserCircle, 'credit-card': CreditCard, refresh: RefreshCcw, package: Package };

export default function HelpCenter() {
  const [params, setParams] = useSearchParams();
  const initialQuery = params.get('q') || '';
  const [query, setQuery] = useState(initialQuery);
  const [data, setData] = useState<{ categories: HelpCategory[]; featured_articles: HelpArticle[]; results: HelpArticle[] }>({ categories: [], featured_articles: [], results: [] });

  useEffect(() => {
    document.title = 'Help Center | Learn by Bluxor';
  }, []);

  useEffect(() => {
    const timeout = window.setTimeout(() => {
      getHelpCenter(query).then(setData).catch(() => undefined);
      const next = new URLSearchParams();
      if (query) next.set('q', query);
      setParams(next, { replace: true });
    }, 200);
    return () => window.clearTimeout(timeout);
  }, [query, setParams]);

  const showingResults = query.trim().length > 0;
  const visibleArticles = useMemo(() => showingResults ? data.results : data.featured_articles, [data, showingResults]);

  return (
    <main>
      <section className="border-b border-ink-100 bg-gradient-to-b from-white to-brand-50/50">
        <div className="container-page py-14 text-center lg:py-20">
          <p className="text-sm font-semibold uppercase tracking-wide text-brand-600">Help Center</p>
          <h1 className="mt-3 font-display text-4xl font-bold text-ink-900 lg:text-5xl">How can we help?</h1>
          <p className="mx-auto mt-4 max-w-2xl text-base leading-7 text-ink-600">
            Search our guides for help with purchases, downloads, payments, your account, and Learn by Bluxor products.
          </p>
          <div className="mx-auto mt-8 max-w-2xl">
            <Input value={query} onChange={(event) => setQuery(event.target.value)} placeholder='Try "download", "payment pending", or "guest purchase"' leftIcon={<Search className="h-5 w-5" />} className="h-14 text-base" />
          </div>
        </div>
      </section>

      <section className="container-page py-12 lg:py-16">
        {!showingResults && (
          <>
            <div className="mb-6 flex items-end justify-between">
              <div>
                <h2 className="font-display text-2xl font-bold text-ink-900">Support categories</h2>
                <p className="mt-1 text-sm text-ink-500">Start with the area closest to your question.</p>
              </div>
            </div>
            <div className="grid gap-4 md:grid-cols-2 lg:grid-cols-3">
              {data.categories.map((category) => {
                const Icon = icons[category.icon as keyof typeof icons] || Package;
                return (
                  <article key={category.id} className="rounded-2xl border border-ink-100 bg-white p-6 shadow-soft">
                    <div className="flex h-11 w-11 items-center justify-center rounded-xl bg-brand-50 text-brand-700">
                      <Icon className="h-5 w-5" />
                    </div>
                    <h3 className="mt-4 text-base font-bold text-ink-900">{category.name}</h3>
                    <p className="mt-2 text-sm leading-6 text-ink-600">{category.description}</p>
                    <p className="mt-4 text-xs font-semibold text-ink-400">{category.articles_count || 0} articles</p>
                  </article>
                );
              })}
            </div>
          </>
        )}

        <div className="mt-12">
          <h2 className="font-display text-2xl font-bold text-ink-900">{showingResults ? 'Search results' : 'Popular help articles'}</h2>
          <div className="mt-5 grid gap-4 md:grid-cols-2">
            {visibleArticles.map((article) => <ArticleCard key={article.id} article={article} />)}
            {visibleArticles.length === 0 && <p className="text-sm text-ink-500">No help articles matched your search.</p>}
          </div>
        </div>

        <section className="mt-12 rounded-2xl border border-ink-100 bg-ink-900 p-8 text-white">
          <div className="flex flex-col gap-5 sm:flex-row sm:items-center sm:justify-between">
            <div>
              <h2 className="font-display text-2xl font-bold">Still need help?</h2>
              <p className="mt-2 text-sm text-ink-200">Contact support with account, purchase, and download issues.</p>
            </div>
            <Link to="/contact"><Button>Contact Support</Button></Link>
          </div>
        </section>
      </section>
    </main>
  );
}

function ArticleCard({ article }: { article: HelpArticle }) {
  return (
    <Link to={`/help/${article.category?.slug}/${article.slug}`} className="rounded-2xl border border-ink-100 bg-white p-5 shadow-soft transition-colors hover:border-brand-200 hover:bg-brand-50/30">
      <p className="text-xs font-semibold uppercase tracking-wide text-brand-600">{article.category?.name}</p>
      <h3 className="mt-2 text-base font-bold text-ink-900">{article.title}</h3>
      <p className="mt-2 text-sm leading-6 text-ink-600">{article.summary}</p>
      <span className="mt-4 inline-flex items-center gap-1.5 text-sm font-semibold text-brand-600">Read article <ArrowRight className="h-4 w-4" /></span>
    </Link>
  );
}
