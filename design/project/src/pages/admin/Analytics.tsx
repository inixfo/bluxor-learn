import { useEffect, useState } from 'react';
import { DollarSign, ShoppingCart, Users, Eye, Calendar, Package } from 'lucide-react';
import { Badge } from '@/components/ui/Badge';
import { Card, EmptyState } from '@/components/ui/Card';
import { formatBDT } from '@/data/store';
import { displayMinor, getAdminAnalytics, type AdminAnalyticsSummary } from '@/services/api/admin';

export default function AdminAnalytics() {
  const [analytics, setAnalytics] = useState<AdminAnalyticsSummary | null>(null);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState('');

  useEffect(() => {
    getAdminAnalytics()
      .then(setAnalytics)
      .catch(() => setError('Analytics are unavailable right now.'))
      .finally(() => setLoading(false));
  }, []);

  const summary = analytics?.summary;
  const metrics = summary ? [
    { label: 'Net Revenue', value: formatBDT(displayMinor(summary.revenue_minor)), icon: DollarSign, color: 'brand' },
    { label: 'Paid Revenue', value: formatBDT(displayMinor(summary.paid_revenue_minor)), icon: DollarSign, color: 'success' },
    { label: 'Refunded', value: formatBDT(displayMinor(summary.refunded_amount_minor)), icon: DollarSign, color: 'warning' },
    { label: 'Purchases', value: summary.purchases.toLocaleString(), icon: ShoppingCart, color: 'violet' },
    { label: 'Visitors', value: summary.visitors.toLocaleString(), icon: Eye, color: 'brand' },
    { label: 'Customer LTV', value: formatBDT(displayMinor(summary.ltv_minor)), icon: Users, color: 'success' },
  ] : [];

  return (
    <div>
      <div className="mb-6 flex flex-col gap-2 sm:flex-row sm:items-center sm:justify-between">
        <div>
          <h1 className="font-display text-2xl font-bold text-ink-900">Analytics</h1>
          <p className="mt-1 text-sm text-ink-500">{loading ? 'Loading analytics...' : 'Backend-supported store and landing metrics.'}</p>
        </div>
        <div className="flex items-center gap-2 rounded-xl border border-ink-200 bg-white px-3 py-2 text-sm text-ink-500">
          <Calendar className="h-4 w-4 text-ink-400" />
          All time
        </div>
      </div>

      {error && <div className="mb-5 rounded-xl border border-danger-200 bg-danger-50 p-4 text-sm text-danger-700">{error}</div>}

      <div className="grid grid-cols-2 gap-4 lg:grid-cols-3 xl:grid-cols-6">
        {metrics.map((m) => (
          <Card key={m.label} className="p-4">
            <div className={`flex h-9 w-9 items-center justify-center rounded-lg bg-${m.color}-100 text-${m.color}-600`}>
              <m.icon className="h-4 w-4" />
            </div>
            <p className="mt-2 text-xl font-bold text-ink-900">{m.value}</p>
            <p className="text-xs text-ink-400">{m.label}</p>
          </Card>
        ))}
      </div>

      <div className="mt-5 grid gap-5 lg:grid-cols-2">
        <Card className="p-5">
          <h2 className="mb-4 text-sm font-bold text-ink-900">Landing Pages</h2>
          {!analytics?.landing_pages.length ? (
            <EmptyState icon={<Eye className="h-7 w-7" />} title={loading ? 'Loading landing pages' : 'No landing pages'} description="Landing analytics appear after pages are uploaded and receive events." />
          ) : (
            <div className="space-y-2">
              {analytics.landing_pages.map((lp) => (
                <div key={lp.id} className="flex items-center gap-3 rounded-xl border border-ink-100 p-3">
                  <div className="min-w-0 flex-1">
                    <p className="truncate text-sm font-semibold text-ink-900">{lp.name}</p>
                    <p className="text-xs text-ink-400">/go/{lp.slug}</p>
                  </div>
                  <Badge tone={lp.status === 'published' ? 'success' : 'warning'}>{lp.status}</Badge>
                </div>
              ))}
            </div>
          )}
        </Card>

        <Card className="p-5">
          <h2 className="mb-4 text-sm font-bold text-ink-900">Products</h2>
          {!analytics?.products.length ? (
            <EmptyState icon={<Package className="h-7 w-7" />} title={loading ? 'Loading products' : 'No products'} description="Product analytics appear after products are created and purchased." />
          ) : (
            <div className="overflow-x-auto">
              <table className="w-full text-sm">
                <thead className="border-b border-ink-100 text-left text-xs text-ink-400">
                  <tr>
                    <th className="pb-2 font-medium">Product</th>
                    <th className="pb-2 font-medium">Status</th>
                    <th className="pb-2 font-medium">Price</th>
                  </tr>
                </thead>
                <tbody>
                  {analytics.products.map((p) => (
                    <tr key={p.id} className="border-b border-ink-50 last:border-0">
                      <td className="py-2.5 font-medium text-ink-900">{p.name}</td>
                      <td className="py-2.5"><Badge tone={p.status === 'published' ? 'success' : p.status === 'draft' ? 'warning' : 'neutral'}>{p.status}</Badge></td>
                      <td className="py-2.5 font-semibold text-ink-900">{formatBDT(displayMinor(p.sale_price_minor || p.regular_price_minor))}</td>
                    </tr>
                  ))}
                </tbody>
              </table>
            </div>
          )}
        </Card>
      </div>
    </div>
  );
}
