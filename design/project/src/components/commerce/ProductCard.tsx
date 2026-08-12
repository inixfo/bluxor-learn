import { Link } from 'react-router-dom';
import { BookOpen, Star, ShoppingCart, Zap } from 'lucide-react';
import { Button } from '@/components/ui/Button';
import { Badge, SaleBadge, type Tone } from '@/components/ui/Badge';
import { formatBDT, type Bundle, type Product } from '@/data/store';

const badgeTone = (b: string): Tone =>
  b === 'Best Seller' ? 'warning' : b === 'New' ? 'brand' : b === 'Popular' ? 'violet' : 'neutral';

export function ProductCard({ product }: { product: Product }) {
  const onSale = product.salePrice && product.salePrice < product.regularPrice;
  const discount = onSale
    ? Math.round((1 - product.salePrice! / product.regularPrice) * 100)
    : 0;

  return (
    <Link
      to={`/p/${product.slug}`}
      className="group flex flex-col overflow-hidden rounded-2xl border border-ink-200/70 bg-white shadow-soft transition-all duration-300 hover:-translate-y-1 hover:border-brand-200 hover:shadow-lift"
    >
      <div className="relative aspect-[4/3] overflow-hidden bg-ink-100">
        {product.cover ? (
          <img
            src={product.cover}
            alt={product.title}
            loading="lazy"
            className="h-full w-full object-cover transition-transform duration-500 group-hover:scale-105"
          />
        ) : (
          <div className="flex h-full w-full items-center justify-center bg-gradient-to-br from-brand-50 to-violet-50 text-brand-600">
            <BookOpen className="h-10 w-10" />
          </div>
        )}
        <div className="absolute left-3 top-3 flex flex-wrap gap-1.5">
          {product.badges.map((b) => (
            <Badge key={b} tone={badgeTone(b)}>
              {b}
            </Badge>
          ))}
          {onSale && <SaleBadge discount={discount} />}
        </div>
      </div>

      <div className="flex flex-1 flex-col p-4">
        <p className="text-xs font-medium text-brand-600">{product.category}</p>
        <h3 className="mt-1 line-clamp-2 text-base font-bold text-ink-900 group-hover:text-brand-700">
          {product.title}
        </h3>
        <p className="mt-1 line-clamp-2 text-sm text-ink-500">{product.shortDescription}</p>

        <div className="mt-2 flex items-center gap-1 text-xs text-ink-400">
          <Star className="h-3.5 w-3.5 fill-warning-400 text-warning-400" />
          <span className="font-semibold text-ink-700">{product.rating}</span>
          <span>({product.reviewCount})</span>
        </div>

        <div className="mt-3 flex items-end justify-between">
          <div className="flex items-baseline gap-2">
            {onSale ? (
              <>
                <span className="text-lg font-bold text-ink-900">
                  {formatBDT(product.salePrice!)}
                </span>
                <span className="text-sm text-ink-400 line-through">
                  {formatBDT(product.regularPrice)}
                </span>
              </>
            ) : (
              <span className="text-lg font-bold text-ink-900">
                {formatBDT(product.regularPrice)}
              </span>
            )}
          </div>
          <Button size="sm" className="opacity-0 transition-opacity group-hover:opacity-100">
            <ShoppingCart className="h-4 w-4" />
          </Button>
        </div>
      </div>
    </Link>
  );
}

export function ProductCompactCard({ product }: { product: Product }) {
  return (
    <Link
      to={`/p/${product.slug}`}
      className="flex items-center gap-3 rounded-xl border border-ink-200/60 bg-white p-3 transition-colors hover:border-brand-200 hover:bg-brand-50/30"
    >
      {product.cover ? (
        <img
          src={product.cover}
          alt={product.title}
          loading="lazy"
          className="h-14 w-14 shrink-0 rounded-lg object-cover"
        />
      ) : (
        <div className="flex h-14 w-14 shrink-0 items-center justify-center rounded-lg bg-brand-50 text-brand-600">
          <BookOpen className="h-5 w-5" />
        </div>
      )}
      <div className="min-w-0 flex-1">
        <p className="truncate text-sm font-semibold text-ink-900">{product.title}</p>
        <p className="truncate text-xs text-ink-500">{product.category}</p>
      </div>
      <span className="text-sm font-bold text-ink-900">{formatBDT(product.salePrice || product.regularPrice)}</span>
    </Link>
  );
}

export function BundleCard({ bundle, products }: { bundle: Bundle; products: Product[] }) {
  const items = bundle.productIds.map((id: string) => products.find((p) => p.id === id)!).filter(Boolean);
  return (
    <Link
      to={`/p/${bundle.slug}`}
      className="group relative flex flex-col overflow-hidden rounded-3xl border border-brand-200 bg-gradient-to-br from-brand-50 via-white to-violet-50 shadow-card transition-all duration-300 hover:-translate-y-1 hover:shadow-lift"
    >
      <div className="relative aspect-[16/9] overflow-hidden">
        {bundle.cover ? (
          <img src={bundle.cover} alt={bundle.title} loading="lazy" className="h-full w-full object-cover transition-transform duration-500 group-hover:scale-105" />
        ) : (
          <div className="flex h-full w-full items-center justify-center bg-gradient-to-br from-brand-600 to-violet-600 text-white">
            <Zap className="h-12 w-12" />
          </div>
        )}
        <div className="absolute inset-0 bg-gradient-to-t from-ink-950/60 to-transparent" />
        <div className="absolute left-4 top-4 flex gap-2">
          <Badge tone="brand"><Zap className="h-3 w-3" /> Bundle</Badge>
          <Badge tone="warning">Save {formatBDT(bundle.savings)}</Badge>
        </div>
        <div className="absolute bottom-4 left-4 right-4">
          <h3 className="text-xl font-bold text-white drop-shadow">{bundle.title}</h3>
          <p className="mt-1 line-clamp-1 text-sm text-white/80">{bundle.description}</p>
        </div>
      </div>
      <div className="flex flex-1 flex-col p-5">
        <p className="mb-2 text-xs font-semibold uppercase tracking-wide text-ink-400">Includes</p>
        <div className="space-y-2">
          {items.map((p: Product) => (
            <div key={p.id} className="flex items-center gap-2 text-sm text-ink-700">
              <span className="h-1.5 w-1.5 rounded-full bg-brand-500" />
              {p.title}
            </div>
          ))}
        </div>
        <div className="mt-5 flex items-end justify-between border-t border-ink-100 pt-4">
          <div>
            <p className="text-xs text-ink-400 line-through">{formatBDT(bundle.regularTotal)}</p>
            <p className="text-2xl font-bold text-brand-700">{formatBDT(bundle.bundlePrice)}</p>
          </div>
          <Button size="sm" variant="primary">Bundle নিন</Button>
        </div>
      </div>
    </Link>
  );
}
