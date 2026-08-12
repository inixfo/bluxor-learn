import { useEffect, useState } from 'react';
import { Link, useParams } from 'react-router-dom';
import { ArrowLeft, Download, FileText, Package } from 'lucide-react';
import { CustomerMobileNav } from '@/components/customer/CustomerSidebar';
import { Badge, type Tone } from '@/components/ui/Badge';
import { Button } from '@/components/ui/Button';
import { Card, EmptyState } from '@/components/ui/Card';
import { formatBDT } from '@/data/store';
import { displayMoney, getOrderDetail, type AccountOrder } from '@/services/api/account';

const statusTone: Record<string, Tone> = { paid: 'success', pending: 'warning', refunded: 'danger', completed: 'success', failed: 'danger' };

export default function CustomerOrderDetail() {
  const { orderNumber = '' } = useParams();
  const [order, setOrder] = useState<AccountOrder | null>(null);
  const [loading, setLoading] = useState(true);

  useEffect(() => {
    if (!orderNumber) return;
    getOrderDetail(orderNumber)
      .then(setOrder)
      .finally(() => setLoading(false));
  }, [orderNumber]);

  if (!loading && !order) {
    return (
      <div>
        <CustomerMobileNav />
        <EmptyState
          icon={<Package className="h-7 w-7" />}
          title="Order not found"
          description="This order is unavailable or no longer belongs to your account."
          action={<Link to="/account/orders" className="text-sm font-medium text-brand-600">Back to orders</Link>}
        />
      </div>
    );
  }

  return (
    <div>
      <CustomerMobileNav />
      <div className="mb-6">
        <Link to="/account/orders" className="mb-2 flex items-center gap-1.5 text-sm text-ink-400 hover:text-brand-600">
          <ArrowLeft className="h-4 w-4" /> Orders
        </Link>
        <h1 className="font-display text-2xl font-bold text-ink-900">{loading ? 'Loading order...' : order?.order_number}</h1>
        <p className="mt-1 text-sm text-ink-500">{order ? `Placed on ${order.date}` : 'Fetching your order details.'}</p>
      </div>

      {order && (
        <div className="grid gap-5 lg:grid-cols-3">
          <Card className="p-5 lg:col-span-2">
            <h2 className="mb-4 text-base font-bold text-ink-900">Items</h2>
            <div className="divide-y divide-ink-100">
              {order.items.map((item) => (
                <div key={item.name} className="flex items-center justify-between gap-4 py-4 first:pt-0 last:pb-0">
                  <div className="flex items-center gap-3">
                    <div className="flex h-10 w-10 items-center justify-center rounded-lg bg-brand-100 text-brand-600">
                      <FileText className="h-5 w-5" />
                    </div>
                    <p className="text-sm font-semibold text-ink-900">{item.name}</p>
                  </div>
                  <p className="text-sm font-bold text-ink-900">{formatBDT(displayMoney(item.total_minor))}</p>
                </div>
              ))}
            </div>
          </Card>

          <div className="space-y-4">
            <Card className="p-5">
              <h2 className="mb-4 text-base font-bold text-ink-900">Summary</h2>
              <div className="space-y-3 text-sm">
                <div className="flex justify-between">
                  <span className="text-ink-400">Payment</span>
                  <Badge tone={statusTone[order.payment_status] || 'neutral'}>{order.payment_status}</Badge>
                </div>
                <div className="flex justify-between">
                  <span className="text-ink-400">Order</span>
                  <Badge tone={statusTone[order.order_status] || 'neutral'}>{order.order_status}</Badge>
                </div>
                <div className="flex justify-between border-t border-ink-100 pt-3">
                  <span className="font-bold text-ink-900">Total</span>
                  <span className="font-bold text-ink-900">{formatBDT(displayMoney(order.total_minor))}</span>
                </div>
              </div>
            </Card>

            <Link to="/account/downloads">
              <Button className="w-full" leftIcon={<Download className="h-4 w-4" />}>
                View downloads
              </Button>
            </Link>
          </div>
        </div>
      )}
    </div>
  );
}
