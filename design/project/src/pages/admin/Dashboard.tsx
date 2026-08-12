import { useEffect, useState } from 'react';
import { Link } from 'react-router-dom';
import { DollarSign, ShoppingCart, Users, Package, Eye, MousePointerClick, CreditCard } from 'lucide-react';
import { Badge } from '@/components/ui/Badge';
import { Card, EmptyState } from '@/components/ui/Card';
import { formatBDT } from '@/data/store';
import { displayMinor, getAdminDashboard, type AdminDashboardSummary } from '@/services/api/admin';

const colors = ['brand', 'violet', 'success', 'warning'] as const;

export default function AdminDashboard() {
  const [dashboard, setDashboard] = useState<AdminDashboardSummary | null>(null);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState('');

  useEffect(() => {
    getAdminDashboard()
      .then(setDashboard)
      .catch(() => setError('Dashboard metrics are unavailable right now.'))
      .finally(() => setLoading(false));
  }, []);

  const metrics = dashboard ? [
    { label: 'Revenue', value: formatBDT(displayMinor(dashboard.metrics.revenue_minor)), icon: DollarSign },
    { label: 'Orders', value: dashboard.metrics.orders.toLocaleString(), icon: ShoppingCart },
    { label: 'Customers', value: dashboard.metrics.customers.toLocaleString(), icon: Users },
    { label: 'Products', value: dashboard.metrics.products.toLocaleString(), icon: Package },
  ] : [];

  const paidOrders = dashboard?.recent_orders.filter((order) => order.payment_status === 'paid').length || 0;
  const pendingOrders = dashboard?.recent_orders.filter((order) => order.payment_status === 'pending').length || 0;
  const funnel = [
    { stage: 'Landing Views', value: 'Use Analytics', icon: Eye },
    { stage: 'CTA Clicks', value: 'Use Analytics', icon: MousePointerClick },
    { stage: 'Checkout Started', value: pendingOrders.toLocaleString(), icon: CreditCard },
    { stage: 'Paid Orders', value: paidOrders.toLocaleString(), icon: Package },
  ];

  return (
    <div>
      <div className="mb-6 flex flex-col gap-2 sm:flex-row sm:items-center sm:justify-between">
        <div>
          <h1 className="font-display text-2xl font-bold text-ink-900">Dashboard</h1>
          <p className="mt-1 text-sm text-ink-500">{loading ? 'Loading store performance...' : 'Overview of your store performance.'}</p>
        </div>
        <div className="rounded-xl border border-ink-200 bg-white px-3 py-2 text-sm text-ink-500">All time</div>
      </div>

      {error && <div className="mb-5 rounded-xl border border-danger-200 bg-danger-50 p-4 text-sm text-danger-700">{error}</div>}

      <div className="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-4">
        {metrics.map((m, index) => (
          <Card key={m.label} className="p-5">
            <div className={`flex h-11 w-11 items-center justify-center rounded-xl bg-${colors[index]}-100 text-${colors[index]}-600`}>
              <m.icon className="h-5 w-5" />
            </div>
            <p className="mt-3 text-2xl font-bold text-ink-900">{m.value}</p>
            <p className="text-xs text-ink-400">{m.label}</p>
          </Card>
        ))}
      </div>

      <div className="mt-6 grid gap-5 lg:grid-cols-3">
        <Card className="p-5 lg:col-span-2">
          <div className="mb-4 flex items-center justify-between">
            <div>
              <h2 className="text-sm font-bold text-ink-900">Recent Orders</h2>
              <p className="text-xs text-ink-400">Latest backend orders</p>
            </div>
            <Link to="/admin/orders" className="text-xs font-medium text-brand-600 hover:text-brand-700">View all &rarr;</Link>
          </div>
          {!dashboard?.recent_orders.length ? (
            <EmptyState icon={<ShoppingCart className="h-7 w-7" />} title={loading ? 'Loading orders' : 'No orders yet'} description={loading ? 'Fetching recent orders.' : 'Orders will appear here after checkout.'} />
          ) : (
            <div className="overflow-x-auto">
              <table className="w-full text-sm">
                <thead>
                  <tr className="border-b border-ink-100 text-left text-xs text-ink-400">
                    <th className="pb-2 font-medium">Order</th>
                    <th className="pb-2 font-medium">Customer</th>
                    <th className="pb-2 font-medium">Amount</th>
                    <th className="pb-2 font-medium">Status</th>
                  </tr>
                </thead>
                <tbody>
                  {dashboard.recent_orders.map((order) => (
                    <tr key={order.id} className="border-b border-ink-50 last:border-0">
                      <td className="py-2.5 font-medium text-ink-900">{order.order_number}</td>
                      <td className="py-2.5 text-ink-600">{order.customer_name || order.customer_email}</td>
                      <td className="py-2.5 font-semibold text-ink-900">{formatBDT(displayMinor(order.total_minor))}</td>
                      <td className="py-2.5"><Badge tone={order.payment_status === 'paid' ? 'success' : order.payment_status === 'pending' ? 'warning' : 'neutral'}>{order.payment_status}</Badge></td>
                    </tr>
                  ))}
                </tbody>
              </table>
            </div>
          )}
        </Card>

        <Card className="p-5">
          <h2 className="mb-1 text-sm font-bold text-ink-900">Operational Funnel</h2>
          <p className="mb-4 text-xs text-ink-400">Real supported metrics only</p>
          <div className="space-y-3">
            {funnel.map((f) => (
              <div key={f.stage} className="flex items-center justify-between rounded-xl border border-ink-100 p-3 text-sm">
                <span className="flex items-center gap-2 font-medium text-ink-700"><f.icon className="h-4 w-4 text-ink-400" />{f.stage}</span>
                <span className="font-semibold text-ink-900">{f.value}</span>
              </div>
            ))}
          </div>
        </Card>
      </div>

      <Card className="mt-5 p-5">
        <div className="mb-4 flex items-center justify-between">
          <h2 className="text-sm font-bold text-ink-900">Top Products</h2>
          <Link to="/admin/products" className="text-xs font-medium text-brand-600 hover:text-brand-700">View all &rarr;</Link>
        </div>
        {!dashboard?.top_products.length ? (
          <EmptyState icon={<Package className="h-7 w-7" />} title={loading ? 'Loading products' : 'No products'} description="Products will appear here after they are created." />
        ) : (
          <div className="space-y-2">
            {dashboard.top_products.map((product, index) => (
              <Link key={product.id} to={`/admin/products/${product.id}/edit`} className="flex items-center gap-3 rounded-xl border border-ink-100 p-3 transition-colors hover:bg-ink-50">
                <span className="text-sm font-bold text-ink-300">#{index + 1}</span>
                {product.cover_image_path ? <img src={product.cover_image_path} alt="" className="h-10 w-10 rounded-lg object-cover" /> : <div className="h-10 w-10 rounded-lg bg-ink-100" />}
                <div className="flex-1">
                  <p className="text-sm font-semibold text-ink-900">{product.name}</p>
                  <p className="text-xs text-ink-400">{product.status}</p>
                </div>
                <span className="text-sm font-bold text-ink-900">{formatBDT(displayMinor(product.sale_price_minor || product.regular_price_minor))}</span>
              </Link>
            ))}
          </div>
        )}
      </Card>
    </div>
  );
}
