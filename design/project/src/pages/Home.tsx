import { useEffect, useState } from 'react';
import { Link } from 'react-router-dom';
import {
  ArrowRight, Sparkles, BookOpen, ShieldCheck, RefreshCw, Star,
  Zap, TrendingUp, CheckCircle2, PackageX,
} from 'lucide-react';
import { Button } from '@/components/ui/Button';
import { Badge } from '@/components/ui/Badge';
import { ProductCard, BundleCard } from '@/components/commerce/ProductCard';
import { EmptyState } from '@/components/ui/Card';
import { formatBDT } from '@/data/store';
import { getHomeCatalog, type ApiBundle, type ApiProduct } from '@/services/api/catalog';
import type { Category } from '@/data/store';

export default function Home() {
  const [featured, setFeatured] = useState<ApiProduct[]>([]);
  const [newArrivals, setNewArrivals] = useState<ApiProduct[]>([]);
  const [categories, setCategories] = useState<Category[]>([]);
  const [bundles, setBundles] = useState<ApiBundle[]>([]);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState('');

  useEffect(() => {
    getHomeCatalog()
      .then((home) => {
        setFeatured(home.featured.length ? home.featured : home.newArrivals);
        setNewArrivals(home.newArrivals);
        setCategories(home.categories);
        setBundles(home.bundles);
      })
      .catch(() => setError('Storefront data is unavailable right now.'))
      .finally(() => setLoading(false));
  }, []);

  const heroProduct = featured[0] || newArrivals[0];
  const heroPrice = heroProduct?.salePrice || heroProduct?.regularPrice || 0;

  return (
    <div>
      <section className="relative overflow-hidden">
        <div className="absolute inset-0 grid-bg opacity-60" />
        <div className="absolute -right-40 -top-40 h-96 w-96 rounded-full bg-brand-200/40 blur-3xl" />
        <div className="absolute -left-40 top-20 h-80 w-80 rounded-full bg-violet-200/30 blur-3xl" />

        <div className="container-page relative py-16 sm:py-20 lg:py-28">
          <div className="grid items-center gap-12 lg:grid-cols-2">
            <div className="animate-fade-in">
              <Badge tone="brand" className="mb-5">
                <Sparkles className="h-3.5 w-3.5" />
                LEARN PRACTICAL SKILLS
              </Badge>
              <h1 className="font-display text-4xl font-extrabold leading-tight tracking-tight text-ink-900 sm:text-5xl lg:text-6xl">
                <span className="bn">শুধু শিখবেন না।</span>
                <br />
                <span className="bn text-gradient">কাজে লাগাতে শিখুন।</span>
              </h1>
              <p className="mt-5 max-w-lg text-base text-ink-600 sm:text-lg">
                Learn by Bluxor publishes practical ebooks, guides, and resources designed to help you
                learn modern skills and actually use them to earn, automate, and build.
              </p>
              <div className="mt-8 flex flex-col gap-3 sm:flex-row">
                <Link to="/products">
                  <Button size="lg" rightIcon={<ArrowRight className="h-5 w-5" />}>
                    <span className="bn">সব প্রোডাক্ট দেখুন</span>
                  </Button>
                </Link>
                <Link to="/products?sort=popular">
                  <Button size="lg" variant="outline">
                    <span className="bn">জনপ্রিয় বইগুলো দেখুন</span>
                  </Button>
                </Link>
              </div>

              <div className="mt-10 flex flex-wrap items-center gap-x-6 gap-y-3 text-sm text-ink-500">
                <div className="flex items-center gap-2">
                  <CheckCircle2 className="h-4 w-4 text-success-600" />
                  <span><strong className="text-ink-800">Instant</strong> protected access after verified payment</span>
                </div>
                <div className="flex items-center gap-1.5">
                  <Star className="h-4 w-4 fill-warning-400 text-warning-400" />
                  <span><strong className="text-ink-800">Lifetime</strong> library access</span>
                </div>
              </div>
            </div>

            <div className="relative animate-fade-in" style={{ animationDelay: '0.1s' }}>
              <div className="relative mx-auto max-w-md">
                <div className="card-surface rotate-2 p-5 transition-transform hover:rotate-0">
                  <div className="flex items-center justify-between">
                    <Badge tone="warning"><Zap className="h-3 w-3" /> Featured</Badge>
                    <Badge tone="brand">Digital access</Badge>
                  </div>
                  {heroProduct ? (
                    <div className="mt-4 flex gap-4">
                      <div className="h-28 w-20 shrink-0 overflow-hidden rounded-lg bg-gradient-to-br from-brand-500 to-violet-600 shadow-lg">
                        {heroProduct.cover ? (
                          <img src={heroProduct.cover} alt={heroProduct.title} className="h-full w-full object-cover" />
                        ) : (
                          <div className="flex h-full w-full items-center justify-center text-white">
                            <BookOpen className="h-8 w-8" />
                          </div>
                        )}
                      </div>
                      <div className="flex-1">
                        <p className="text-xs font-medium text-brand-600">{heroProduct.category}</p>
                        <h3 className="mt-1 text-lg font-bold text-ink-900">{heroProduct.title}</h3>
                        <div className="mt-2 flex items-baseline gap-2">
                          <span className="text-xl font-bold text-ink-900">{formatBDT(heroPrice)}</span>
                          {heroProduct.salePrice && <span className="text-sm text-ink-400 line-through">{formatBDT(heroProduct.regularPrice)}</span>}
                        </div>
                      </div>
                    </div>
                  ) : (
                    <div className="mt-4 rounded-xl border border-ink-100 bg-ink-50 p-5 text-sm text-ink-500">
                      {loading ? 'Loading featured product...' : 'Publish a product to feature it here.'}
                    </div>
                  )}
                </div>

                <div className="absolute -bottom-6 -left-6 card-surface -rotate-3 p-3 w-44 animate-fade-in" style={{ animationDelay: '0.3s' }}>
                  <div className="flex items-center gap-2">
                    <div className="flex h-8 w-8 items-center justify-center rounded-lg bg-success-100 text-success-600">
                      <CheckCircle2 className="h-4 w-4" />
                    </div>
                    <div>
                      <p className="text-xs font-bold text-ink-900">Lifetime Access</p>
                      <p className="text-[10px] text-ink-400">One-time purchase</p>
                    </div>
                  </div>
                </div>

                <div className="absolute -top-4 -right-4 card-surface rotate-6 p-3 w-40 animate-fade-in" style={{ animationDelay: '0.4s' }}>
                  <div className="flex items-center gap-2">
                    <div className="flex h-8 w-8 items-center justify-center rounded-lg bg-brand-100 text-brand-600">
                      <RefreshCw className="h-4 w-4" />
                    </div>
                    <div>
                      <p className="text-xs font-bold text-ink-900">Free Updates</p>
                      <p className="text-[10px] text-ink-400">Always current</p>
                    </div>
                  </div>
                </div>
              </div>
            </div>
          </div>
        </div>
      </section>

      {error && (
        <section className="border-y border-danger-100 bg-danger-50 py-4">
          <div className="container-page text-sm text-danger-700">{error}</div>
        </section>
      )}

      {categories.length > 0 && (
        <section className="border-y border-ink-100 bg-white py-14">
          <div className="container-page">
            <div className="mb-8 flex items-end justify-between">
              <div>
                <h2 className="font-display text-2xl font-bold text-ink-900">Explore by category</h2>
                <p className="mt-1 text-sm text-ink-500">Find resources for the skill you want to master.</p>
              </div>
              <Link to="/products" className="hidden text-sm font-medium text-brand-600 hover:text-brand-700 sm:block">View all &rarr;</Link>
            </div>
            <div className="grid grid-cols-2 gap-4 md:grid-cols-3 lg:grid-cols-6">
              {categories.map((cat) => (
                <Link key={cat.id} to={`/products?category=${cat.id}`} className="group flex flex-col items-center rounded-2xl border border-ink-200/60 bg-white p-5 text-center transition-all hover:-translate-y-1 hover:border-brand-200 hover:shadow-card">
                  <div className={`flex h-12 w-12 items-center justify-center rounded-xl bg-${cat.color}-100 text-${cat.color}-600 transition-transform group-hover:scale-110`}>
                    <cat.icon className="h-6 w-6" />
                  </div>
                  <p className="mt-3 text-sm font-semibold text-ink-900">{cat.name}</p>
                  <p className="text-xs text-ink-400">{cat.count} products</p>
                </Link>
              ))}
            </div>
          </div>
        </section>
      )}

      <section className="py-16">
        <div className="container-page">
          <div className="mb-8 flex items-end justify-between">
            <div>
              <Badge tone="brand" className="mb-2"><TrendingUp className="h-3.5 w-3.5" /> Popular</Badge>
              <h2 className="font-display text-2xl font-bold text-ink-900 sm:text-3xl"><span className="bn">জনপ্রিয় প্রোডাক্ট</span></h2>
            </div>
            <Link to="/products" className="text-sm font-medium text-brand-600 hover:text-brand-700">All products &rarr;</Link>
          </div>
          {featured.length > 0 ? (
            <div className="grid grid-cols-1 gap-5 sm:grid-cols-2 lg:grid-cols-4">
              {featured.slice(0, 4).map((p) => <ProductCard key={p.id} product={p} />)}
            </div>
          ) : (
            <EmptyState icon={<PackageX className="h-7 w-7" />} title={loading ? 'Loading products' : 'No featured products'} description={loading ? 'Fetching storefront products.' : 'Published products will appear here.'} />
          )}
        </div>
      </section>

      <section className="border-y border-ink-100 bg-gradient-to-b from-white to-ink-50 py-16">
        <div className="container-page">
          <div className="mx-auto mb-10 max-w-2xl text-center">
            <h2 className="font-display text-2xl font-bold text-ink-900 sm:text-3xl">Why Learn by Bluxor</h2>
            <p className="mt-2 text-ink-500">We focus on what actually helps you get results.</p>
          </div>
          <div className="grid grid-cols-1 gap-6 sm:grid-cols-2 lg:grid-cols-4">
            {[
              { icon: Sparkles, title: 'Practical First', bn: 'কম theory, বেশি বাস্তব ব্যবহার।', color: 'brand' },
              { icon: BookOpen, title: 'Easy Language', bn: 'জটিল বিষয় সহজভাবে শেখানো।', color: 'violet' },
              { icon: ShieldCheck, title: 'Lifetime Access', bn: 'একবার কিনুন, নিজের library থেকে access করুন।', color: 'success' },
              { icon: RefreshCw, title: 'Updated Resources', bn: 'নতুন resource ও update সহজেই পান।', color: 'warning' },
            ].map((f) => (
              <div key={f.title} className="card-surface p-6 transition-all hover:-translate-y-1 hover:shadow-card">
                <div className={`flex h-12 w-12 items-center justify-center rounded-xl bg-${f.color}-100 text-${f.color}-600`}>
                  <f.icon className="h-6 w-6" />
                </div>
                <h3 className="mt-4 text-lg font-bold text-ink-900">{f.title}</h3>
                <p className="mt-1 bn text-sm text-ink-500">{f.bn}</p>
              </div>
            ))}
          </div>
        </div>
      </section>

      {bundles.length > 0 && (
        <section className="py-16">
          <div className="container-page">
            <div className="mb-8 text-center">
              <Badge tone="violet" className="mb-2"><Zap className="h-3.5 w-3.5" /> Save More</Badge>
              <h2 className="font-display text-2xl font-bold text-ink-900 sm:text-3xl">Premium Bundles</h2>
              <p className="mt-2 text-ink-500">Get more value with curated product bundles.</p>
            </div>
            <div className="grid grid-cols-1 gap-6 lg:grid-cols-2">
              {bundles.map((b) => <BundleCard key={b.id} bundle={b} products={[...featured, ...newArrivals, ...(b.products || [])]} />)}
            </div>
          </div>
        </section>
      )}

      {newArrivals.length > 0 && (
        <section className="border-t border-ink-100 bg-white py-16">
          <div className="container-page">
            <div className="mb-8 flex items-end justify-between">
              <div>
                <Badge tone="brand" className="mb-2"><Sparkles className="h-3.5 w-3.5" /> Fresh</Badge>
                <h2 className="font-display text-2xl font-bold text-ink-900 sm:text-3xl">New Releases</h2>
              </div>
              <Link to="/products?sort=newest" className="text-sm font-medium text-brand-600 hover:text-brand-700">See all new &rarr;</Link>
            </div>
            <div className="grid grid-cols-1 gap-5 sm:grid-cols-2 lg:grid-cols-4">
              {newArrivals.slice(0, 4).map((p) => <ProductCard key={p.id} product={p} />)}
            </div>
          </div>
        </section>
      )}

      <section className="pb-20 pt-16">
        <div className="container-page text-center">
          <h2 className="font-display text-3xl font-bold text-ink-900 sm:text-4xl">Start learning skills that pay off.</h2>
          <p className="mx-auto mt-3 max-w-lg text-ink-500">Browse our library of practical resources. Buy once, own forever.</p>
          <div className="mt-6">
            <Link to="/products">
              <Button size="lg" rightIcon={<ArrowRight className="h-5 w-5" />}>Browse the Library</Button>
            </Link>
          </div>
        </div>
      </section>
    </div>
  );
}
