import { useEffect, useState } from 'react';
import { Link } from 'react-router-dom';
import { BookOpen, Package, Download, TrendingUp, type LucideIcon } from 'lucide-react';
import { CustomerMobileNav } from '@/components/customer/CustomerSidebar';
import { Card } from '@/components/ui/Card';
import { formatBDT } from '@/data/store';
import { displayMoney, getOverview, type AccountOverview } from '@/services/api/account';

export default function CustomerOverview() {
  const [overview, setOverview] = useState<AccountOverview | null>(null);
  const [loading, setLoading] = useState(true);
  const recentOrder = overview?.recent_orders[0];
  const latestDownload = overview?.recent_library_items[0];

  useEffect(() => {
    getOverview().then(setOverview).finally(() => setLoading(false));
  }, []);

  return (
    <div>
      <CustomerMobileNav />
      <div className="mb-6">
        <h1 className="font-display text-2xl font-bold text-ink-900">Welcome back{overview ? `, ${overview.customer.name.split(' ')[0]}` : ''}</h1>
        <p className="mt-1 text-sm text-ink-500">{loading ? 'Loading your account...' : "Here's what's happening with your account."}</p>
      </div>

      <div className="grid grid-cols-2 gap-4 lg:grid-cols-4">
        <StatCard icon={BookOpen} label="Purchased Products" value={String(overview?.purchased_product_count || 0)} color="brand" />
        <StatCard icon={Package} label="Total Orders" value={String(overview?.recent_orders.length || 0)} color="violet" />
        <StatCard icon={Download} label="Available Downloads" value={String(overview?.recent_library_items.reduce((sum, item) => sum + item.resource_count, 0) || 0)} color="success" />
        <StatCard icon={TrendingUp} label="Recent Spent" value={formatBDT(overview?.recent_orders.reduce((sum, order) => sum + displayMoney(order.total_minor), 0) || 0)} color="warning" />
      </div>

      <div className="mt-6 grid gap-5 lg:grid-cols-2">
        <Card className="p-5">
          <div className="mb-3 flex items-center justify-between">
            <h2 className="text-sm font-bold text-ink-900">Recent Order</h2>
            <Link to="/account/orders" className="text-xs font-medium text-brand-600 hover:text-brand-700">View all</Link>
          </div>
          {recentOrder ? (
            <div className="flex items-center gap-3 rounded-xl border border-ink-200/60 p-3">
              <div className="flex h-10 w-10 items-center justify-center rounded-lg bg-brand-100 text-brand-600">
                <Package className="h-5 w-5" />
              </div>
              <div className="flex-1">
                <p className="text-sm font-semibold text-ink-900">{recentOrder.order_number}</p>
                <p className="text-xs text-ink-400">{recentOrder.items.map((item) => item.name).join(', ')} · {recentOrder.date}</p>
              </div>
              <span className="text-sm font-bold text-ink-900">{formatBDT(displayMoney(recentOrder.total_minor))}</span>
            </div>
          ) : (
            <p className="text-sm text-ink-400">No recent orders.</p>
          )}
        </Card>

        <Card className="p-5">
          <div className="mb-3 flex items-center justify-between">
            <h2 className="text-sm font-bold text-ink-900">Latest Download</h2>
            <Link to="/account/library" className="text-xs font-medium text-brand-600 hover:text-brand-700">Library</Link>
          </div>
          {latestDownload ? (
            <div className="flex items-center gap-3 rounded-xl border border-ink-200/60 p-3">
              {latestDownload.cover ? (
                <img src={latestDownload.cover} alt="" className="h-12 w-10 rounded-lg object-cover" />
              ) : (
                <div className="flex h-12 w-10 items-center justify-center rounded-lg bg-brand-50 text-brand-600">
                  <BookOpen className="h-4 w-4" />
                </div>
              )}
              <div className="flex-1">
                <p className="text-sm font-semibold text-ink-900">{latestDownload.title}</p>
                <p className="text-xs text-ink-400">{latestDownload.resource_count} files available</p>
              </div>
              <Link to={`/account/library/${latestDownload.product_id}`}>
                <button className="rounded-lg bg-brand-600 p-2 text-white transition-colors hover:bg-brand-700">
                  <Download className="h-4 w-4" />
                </button>
              </Link>
            </div>
          ) : (
            <p className="text-sm text-ink-400">No downloads yet.</p>
          )}
        </Card>
      </div>
      <div className="mt-8 rounded-xl border border-brand-200 bg-brand-50/50 p-4 text-sm text-ink-600">
        New purchases and downloads appear here automatically after backend entitlements are created.
      </div>
    </div>
  );
}

function StatCard({ icon: Icon, label, value, color }: { icon: LucideIcon; label: string; value: string; color: 'brand' | 'violet' | 'success' | 'warning' }) {
  return (
    <div className="card-surface p-4">
      <div className={`flex h-10 w-10 items-center justify-center rounded-xl bg-${color}-100 text-${color}-600`}>
        <Icon className="h-5 w-5" />
      </div>
      <p className="mt-3 text-2xl font-bold text-ink-900">{value}</p>
      <p className="text-xs text-ink-400">{label}</p>
    </div>
  );
}
