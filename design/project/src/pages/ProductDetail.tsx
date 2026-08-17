import { useEffect, useState } from 'react';
import { Link, useNavigate, useParams } from 'react-router-dom';
import {
  ShoppingCart, Zap, Check, ChevronDown, Shield, RefreshCw,
  Download, FileText, Package, Share2,
} from 'lucide-react';
import { Button } from '@/components/ui/Button';
import { Badge, SaleBadge } from '@/components/ui/Badge';
import { Breadcrumb, Card, EmptyState } from '@/components/ui/Card';
import { formatBDT } from '@/data/store';
import { getCatalogItem, getProducts, type ApiBundle, type ApiProduct } from '@/services/api/catalog';
import { ProductCard } from '@/components/commerce/ProductCard';
import { trackMetaViewContent } from '@/services/metaTracking';

export default function ProductDetail() {
  const { slug } = useParams();
  const navigate = useNavigate();
  const [product, setProduct] = useState<ApiProduct | ApiBundle | null>(null);
  const [related, setRelated] = useState<ApiProduct[]>([]);
  const [loading, setLoading] = useState(true);
  const [loadError, setLoadError] = useState('');
  const [openFaq, setOpenFaq] = useState<number | null>(0);

  useEffect(() => {
    if (!slug) return;
    setLoading(true);
    setLoadError('');
    getCatalogItem(slug)
      .then(({ item }) => {
        setProduct(item);
        if (!('productIds' in item) && item.category) {
          const params = new URLSearchParams({ category: item.category.toLowerCase(), per_page: '4' });
          getProducts(params).then((items) => setRelated(items.filter((p) => p.slug !== slug).slice(0, 4))).catch(() => setRelated([]));
        } else {
          setRelated([]);
        }
      })
      .catch(() => {
        setProduct(null);
        setLoadError('This product is unavailable or has not been published.');
      })
      .finally(() => setLoading(false));
  }, [slug]);

  useEffect(() => {
    if (!product) return;
    const isBundle = 'productIds' in product;
    const value = isBundle ? product.bundlePrice : (product.salePrice || product.regularPrice);
    void trackMetaViewContent({
      contentIds: [`${isBundle ? 'bundle' : 'product'}:${product.backendId}`],
      contentName: product.title,
      contentType: 'product',
      value,
      currency: product.currency,
    });
  }, [product]);

  if (loading) {
    return <div className="container-page py-20 text-sm text-ink-500">Loading product...</div>;
  }

  if (!product) {
    return (
      <div className="container-page py-20">
        <EmptyState
          icon={<Package className="h-7 w-7" />}
          title="Product not found"
          description={loadError || 'This product is unavailable.'}
          action={<Link to="/products" className="text-sm font-medium text-brand-600">Browse all products</Link>}
        />
      </div>
    );
  }

  const isBundle = 'productIds' in product;
  const checkoutTarget = isBundle ? `/checkout?bundle_id=${product.backendId}` : `/checkout?product_id=${product.backendId}`;
  const regularPrice = isBundle ? product.regularTotal : product.regularPrice;
  const salePrice = isBundle ? product.bundlePrice : product.salePrice;
  const activePrice = salePrice || regularPrice;
  const onSale = !!salePrice && salePrice < regularPrice;
  const discount = onSale ? Math.round((1 - salePrice / regularPrice) * 100) : 0;
  const bundleItems = isBundle ? product.products || [] : [];

  const faqs = [
    { q: 'How do I access my purchase?', a: 'After verified payment, access appears in your library and download instructions are sent by email.' },
    { q: 'Do I get future updates?', a: 'Yes. When an updated file is published, it appears in your library automatically.' },
    { q: 'What format are the files in?', a: isBundle ? 'Bundle items are delivered as their configured digital resources.' : `This product is delivered as ${product.format}.` },
    { q: 'Can I get a refund?', a: 'Refunds are reviewed through support and processed only after backend/payment-provider confirmation.' },
  ];

  return (
    <div className="py-8">
      <div className="container-page">
        <Breadcrumb items={[{ label: 'Home', to: '/' }, { label: 'Products', to: '/products' }, { label: product.title }]} />

        <div className="mt-6 grid gap-8 lg:grid-cols-2">
          <div className="lg:sticky lg:top-24 lg:self-start">
            <div className="relative overflow-hidden rounded-3xl border border-ink-200/60 bg-white shadow-card">
              {product.cover ? (
                <img src={product.cover} alt={product.title} className="aspect-[4/3] w-full object-cover" />
              ) : (
                <div className="flex aspect-[4/3] items-center justify-center bg-ink-100 text-sm text-ink-400">No cover image</div>
              )}
              <div className="absolute left-4 top-4 flex gap-2">
                {isBundle && <Badge tone="brand"><Zap className="h-3 w-3" /> Bundle</Badge>}
                {!isBundle && product.badges.map((b) => <Badge key={b} tone={b === 'Best Seller' ? 'warning' : 'brand'}>{b}</Badge>)}
                {onSale && <SaleBadge discount={discount} />}
              </div>
            </div>
          </div>

          <div>
            <p className="text-sm font-medium text-brand-600">{isBundle ? 'Bundle' : product.category}</p>
            <h1 className="mt-1 font-display text-3xl font-bold text-ink-900">{product.title}</h1>
            <p className="mt-2 text-ink-500">{isBundle ? product.description : product.shortDescription}</p>

            <div className="mt-6 flex items-baseline gap-3">
              <span className="text-4xl font-bold text-ink-900">{formatBDT(activePrice)}</span>
              {onSale && <span className="text-xl text-ink-400 line-through">{formatBDT(regularPrice)}</span>}
              {onSale && <Badge tone="danger">Save {formatBDT(regularPrice - activePrice)}</Badge>}
            </div>

            <div className="mt-6 flex flex-col gap-3 sm:flex-row">
              <Button size="lg" className="flex-1" onClick={() => navigate(checkoutTarget)}>
                <ShoppingCart className="h-5 w-5" />
                Buy Now
              </Button>
              <Button
                size="lg"
                variant="outline"
                onClick={() => navigator.clipboard?.writeText(window.location.href)}
                title="Copy product link"
              >
                <Share2 className="h-5 w-5" />
              </Button>
            </div>

            <div className="mt-5 grid grid-cols-3 gap-3">
              {[
                { icon: Shield, label: 'Secure payment' },
                { icon: RefreshCw, label: 'Lifetime updates' },
                { icon: Download, label: 'Instant access' },
              ].map((t) => (
                <div key={t.label} className="flex flex-col items-center gap-1.5 rounded-xl border border-ink-200/60 bg-white p-3 text-center">
                  <t.icon className="h-5 w-5 text-brand-600" />
                  <span className="text-xs font-medium text-ink-600">{t.label}</span>
                </div>
              ))}
            </div>

            {!isBundle && (
              <div className="mt-8">
                <h2 className="text-lg font-bold text-ink-900">What's included</h2>
                <ul className="mt-3 space-y-2">
                  {product.whatsIncluded.map((item, i) => (
                    <li key={i} className="flex items-start gap-2.5 text-sm text-ink-700">
                      <Check className="mt-0.5 h-4 w-4 shrink-0 text-success-600" />
                      {item}
                    </li>
                  ))}
                </ul>
                <div className="mt-4 flex gap-4 text-sm text-ink-500">
                  <span className="flex items-center gap-1.5"><FileText className="h-4 w-4" /> {product.format}</span>
                  <span className="flex items-center gap-1.5"><Package className="h-4 w-4" /> {product.fileSize}</span>
                </div>
              </div>
            )}

            {isBundle && (
              <div className="mt-8">
                <h2 className="text-lg font-bold text-ink-900">Products in this bundle</h2>
                <div className="mt-3 space-y-3">
                  {bundleItems.map((p) => (
                    <Link key={p.id} to={`/p/${p.slug}`} className="flex items-center gap-3 rounded-xl border border-ink-200/60 bg-white p-3 transition-colors hover:border-brand-200 hover:bg-brand-50/30">
                      {p.cover ? <img src={p.cover} alt="" className="h-14 w-14 rounded-lg object-cover" /> : <div className="h-14 w-14 rounded-lg bg-ink-100" />}
                      <div className="flex-1">
                        <p className="text-sm font-semibold text-ink-900">{p.title}</p>
                        <p className="text-xs text-ink-400">{formatBDT(p.salePrice || p.regularPrice)}</p>
                      </div>
                      <Badge tone="success"><Check className="h-3 w-3" /> Included</Badge>
                    </Link>
                  ))}
                </div>
              </div>
            )}
          </div>
        </div>

        <div className="mt-12 grid gap-8 lg:grid-cols-3">
          <div className="lg:col-span-2">
            <h2 className="text-xl font-bold text-ink-900">About this product</h2>
            <p className="mt-3 leading-relaxed text-ink-600">{product.description}</p>

            <h2 className="mt-10 text-xl font-bold text-ink-900">Frequently Asked Questions</h2>
            <div className="mt-4 space-y-2">
              {faqs.map((f, i) => (
                <div key={i} className="overflow-hidden rounded-xl border border-ink-200/60 bg-white">
                  <button onClick={() => setOpenFaq(openFaq === i ? null : i)} className="flex w-full items-center justify-between px-5 py-4 text-left">
                    <span className="text-sm font-semibold text-ink-900">{f.q}</span>
                    <ChevronDown className={`h-5 w-5 shrink-0 text-ink-400 transition-transform ${openFaq === i ? 'rotate-180' : ''}`} />
                  </button>
                  {openFaq === i && <div className="px-5 pb-4 text-sm text-ink-600">{f.a}</div>}
                </div>
              ))}
            </div>
          </div>

          <div>
            <Card className="p-5">
              <h3 className="text-sm font-semibold text-ink-900">Product details</h3>
              <dl className="mt-3 space-y-2.5 text-sm">
                <div className="flex justify-between"><dt className="text-ink-400">Category</dt><dd className="font-medium text-ink-800">{isBundle ? 'Bundle' : product.category}</dd></div>
                <div className="flex justify-between"><dt className="text-ink-400">Type</dt><dd className="font-medium text-ink-800">{isBundle ? 'Bundle' : product.type}</dd></div>
                {!isBundle && <div className="flex justify-between"><dt className="text-ink-400">Format</dt><dd className="font-medium text-ink-800">{product.format}</dd></div>}
                <div className="flex justify-between"><dt className="text-ink-400">Updated</dt><dd className="font-medium text-ink-800">{isBundle ? 'Recently' : product.updatedAtLabel}</dd></div>
              </dl>
            </Card>
          </div>
        </div>

        {related.length > 0 && (
          <div className="mt-16">
            <h2 className="text-xl font-bold text-ink-900">Related products</h2>
            <div className="mt-5 grid grid-cols-1 gap-5 sm:grid-cols-2 lg:grid-cols-4">
              {related.map((p) => <ProductCard key={p.id} product={p} />)}
            </div>
          </div>
        )}
      </div>

      <div className="fixed bottom-0 left-0 right-0 z-30 border-t border-ink-100 bg-white/95 p-3 backdrop-blur lg:hidden">
        <div className="container-page flex items-center gap-3">
          <div className="flex-1">
            <span className="text-xl font-bold text-ink-900">{formatBDT(activePrice)}</span>
          </div>
          <Button onClick={() => navigate(checkoutTarget)} className="flex-1">
            <ShoppingCart className="h-5 w-5" />
            Buy Now
          </Button>
        </div>
      </div>
    </div>
  );
}
