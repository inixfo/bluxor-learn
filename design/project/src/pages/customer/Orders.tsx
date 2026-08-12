import { useEffect, useState } from 'react';
import { Link } from 'react-router-dom';
import { ChevronRight, Package } from 'lucide-react';
import { CustomerMobileNav } from '@/components/customer/CustomerSidebar';
import { Badge, type Tone } from '@/components/ui/Badge';
import { Card, EmptyState } from '@/components/ui/Card';
import { formatBDT } from '@/data/store';
import { displayMoney, getOrders, type AccountOrder } from '@/services/api/account';

const statusTone: Record<string, Tone> = { paid: 'success', pending: 'warning', refunded: 'danger', completed: 'success', failed: 'danger' };

export default function CustomerOrders() {
  const [orders, setOrders] = useState<AccountOrder[]>([]);
  const [loading, setLoading] = useState(true);

  useEffect(() => {
    getOrders().then(setOrders).finally(() => setLoading(false));
  }, []);

  return (
    <div>
      <CustomerMobileNav />
      <div className="mb-6">
        <h1 className="font-display text-2xl font-bold text-ink-900">Orders</h1>
        <p className="mt-1 text-sm text-ink-500">{loading ? 'Loading your order history...' : 'Your purchase history.'}</p>
      </div>

      {orders.length === 0 ? (
        <EmptyState
          icon={<Package className="h-7 w-7" />}
          title="No orders yet"
          description="Your orders will appear here."
          action={<Link to="/products" className="text-sm font-medium text-brand-600">Browse products</Link>}
        />
      ) : (
        <div className="space-y-3">
          {orders.map((order) => (
            <Card key={order.id} className="flex items-center gap-4 p-4">
              <div className="flex h-12 w-12 items-center justify-center rounded-xl bg-brand-100 text-brand-600">
                <Package className="h-6 w-6" />
              </div>
              <div className="min-w-0 flex-1">
                <div className="flex items-center gap-2">
                  <p className="text-sm font-bold text-ink-900">{order.order_number}</p>
                  <Badge tone={statusTone[order.payment_status] || 'neutral'}>{order.payment_status}</Badge>
                </div>
                <p className="truncate text-xs text-ink-400">
                  {order.items.map((item) => item.name).join(', ')} / {order.date}
                </p>
              </div>
              <div className="text-right">
                <p className="text-sm font-bold text-ink-900">{formatBDT(displayMoney(order.total_minor))}</p>
              </div>
              <Link to={`/account/orders/${order.order_number}`} className="rounded-lg p-2 text-ink-400 transition-colors hover:bg-ink-100 hover:text-ink-700">
                <ChevronRight className="h-5 w-5" />
              </Link>
            </Card>
          ))}
        </div>
      )}
    </div>
  );
}
