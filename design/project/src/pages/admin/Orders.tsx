import { useEffect, useState } from 'react';
import { Download, Eye, RotateCcw, Search } from 'lucide-react';
import { Badge, type Tone } from '@/components/ui/Badge';
import { Button } from '@/components/ui/Button';
import { Card } from '@/components/ui/Card';
import { Input, Select } from '@/components/ui/Input';
import { Modal } from '@/components/ui/Modal';
import { formatBDT } from '@/data/store';
import { displayMinor, getAdminOrders, refundAdminOrder, type AdminOrder } from '@/services/api/admin';
import { useToast } from '@/components/ui/Toast';

const statusTone: Record<string, Tone> = { paid: 'success', pending: 'warning', refunded: 'danger', failed: 'danger', completed: 'success' };

export default function AdminOrders() {
  const [query, setQuery] = useState('');
  const [statusFilter, setStatusFilter] = useState('');
  const [orders, setOrders] = useState<AdminOrder[]>([]);
  const [selected, setSelected] = useState<AdminOrder | null>(null);
  const [loading, setLoading] = useState(true);
  const [refunding, setRefunding] = useState(false);
  const [confirmRefund, setConfirmRefund] = useState(false);
  const toast = useToast();

  const load = () => getAdminOrders().then(setOrders).finally(() => setLoading(false));

  useEffect(() => {
    load();
  }, []);

  const refund = async () => {
    if (!selected) return;
    setRefunding(true);
    try {
      await refundAdminOrder(selected.id);
      toast({ type: 'success', title: 'Refund recorded', message: 'PipraPay confirmed the full refund.' });
      setSelected(null);
      await load();
    } catch {
      toast({ type: 'error', title: 'Refund failed', message: 'The order was not changed. Check PipraPay status and try again.' });
    } finally {
      setRefunding(false);
    }
  };

  const filtered = orders.filter((order) => {
    const customer = `${order.customer_name || ''} ${order.customer_email}`.toLowerCase();
    if (query && !order.order_number.toLowerCase().includes(query.toLowerCase()) && !customer.includes(query.toLowerCase())) return false;
    if (statusFilter && order.payment_status !== statusFilter) return false;
    return true;
  });

  return (
    <div>
      <div className="mb-6">
        <h1 className="font-display text-2xl font-bold text-ink-900">Orders</h1>
        <p className="mt-1 text-sm text-ink-500">{loading ? 'Loading orders...' : 'Manage and track all customer orders.'}</p>
      </div>

      <div className="mb-4 flex flex-col gap-3 sm:flex-row">
        <div className="flex-1">
          <Input placeholder="Search by order ID or customer..." value={query} onChange={(e) => setQuery(e.target.value)} leftIcon={<Search className="h-4 w-4" />} />
        </div>
        <Select value={statusFilter} onChange={(e) => setStatusFilter(e.target.value)} className="sm:w-44">
          <option value="">All Status</option>
          <option value="paid">Paid</option>
          <option value="pending">Pending</option>
          <option value="refunded">Refunded</option>
          <option value="failed">Failed</option>
        </Select>
      </div>

      <Card className="overflow-hidden">
        <div className="overflow-x-auto">
          <table className="w-full text-sm">
            <thead className="border-b border-ink-100 bg-ink-50/50 text-left text-xs text-ink-400">
              <tr>
                <th className="px-4 py-3 font-medium">Order ID</th>
                <th className="px-4 py-3 font-medium">Customer</th>
                <th className="px-4 py-3 font-medium">Products</th>
                <th className="px-4 py-3 font-medium">Amount</th>
                <th className="px-4 py-3 font-medium">Payment</th>
                <th className="px-4 py-3 font-medium">Status</th>
                <th className="px-4 py-3 font-medium">Date</th>
                <th className="px-4 py-3"></th>
              </tr>
            </thead>
            <tbody>
              {filtered.map((order) => (
                <tr key={order.id} className="cursor-pointer border-b border-ink-50 last:border-0 hover:bg-ink-50/30" onClick={() => { setSelected(order); setConfirmRefund(false); }}>
                  <td className="px-4 py-3 font-semibold text-ink-900">{order.order_number}</td>
                  <td className="px-4 py-3">
                    <div>
                      <p className="text-ink-900">{order.customer_name || 'Guest'}</p>
                      <p className="text-xs text-ink-400">{order.customer_email}</p>
                    </div>
                  </td>
                  <td className="px-4 py-3 text-ink-600">{order.items.map((item) => item.product_name).join(', ')}</td>
                  <td className="px-4 py-3 font-semibold text-ink-900">{formatBDT(displayMinor(order.total_minor))}</td>
                  <td className="px-4 py-3 text-ink-600">{order.payment_gateway || 'PipraPay'}</td>
                  <td className="px-4 py-3"><Badge tone={statusTone[order.payment_status] || 'neutral'}>{order.payment_status}</Badge></td>
                  <td className="px-4 py-3 text-ink-400">{new Date(order.created_at).toLocaleDateString()}</td>
                  <td className="px-4 py-3">
                    <Button size="icon" variant="ghost"><Eye className="h-4 w-4" /></Button>
                  </td>
                </tr>
              ))}
            </tbody>
          </table>
        </div>
      </Card>

      <Modal
        open={!!selected}
        onClose={() => { setSelected(null); setConfirmRefund(false); }}
        title={selected ? `Order ${selected.order_number}` : ''}
        size="lg"
        footer={
          confirmRefund ? (
            <>
              <Button variant="outline" onClick={() => setConfirmRefund(false)}>Cancel</Button>
              <Button variant="destructive" leftIcon={<RotateCcw className="h-4 w-4" />} loading={refunding} onClick={refund}>Confirm Refund</Button>
            </>
          ) : (
            <>
              <Button variant="outline" leftIcon={<Download className="h-4 w-4" />} disabled title="Invoice export is not available yet">Invoice</Button>
              <Button variant="destructive" leftIcon={<RotateCcw className="h-4 w-4" />} disabled={selected?.payment_status !== 'paid'} onClick={() => setConfirmRefund(true)}>Refund</Button>
            </>
          )
        }
      >
        {selected && (
          <div className="space-y-5">
            <div className="grid grid-cols-2 gap-4">
              <div>
                <p className="text-xs text-ink-400">Customer</p>
                <p className="text-sm font-semibold text-ink-900">{selected.customer_name || 'Guest'}</p>
                <p className="text-xs text-ink-500">{selected.customer_email}</p>
              </div>
              <div>
                <p className="text-xs text-ink-400">Payment</p>
                <Badge tone={statusTone[selected.payment_status] || 'neutral'}>{selected.payment_status}</Badge>
              </div>
              <div>
                <p className="text-xs text-ink-400">Order status</p>
                <p className="text-sm font-semibold text-ink-900">{selected.order_status}</p>
              </div>
              <div>
                <p className="text-xs text-ink-400">Date</p>
                <p className="text-sm font-semibold text-ink-900">{new Date(selected.created_at).toLocaleDateString()}</p>
              </div>
            </div>

            <div className="rounded-xl border border-ink-100 p-4">
              <p className="mb-3 text-xs font-semibold text-ink-400">Products</p>
              <div className="space-y-2">
                {selected.items.map((item) => (
                  <div key={item.product_name} className="flex justify-between text-sm">
                    <span className="font-semibold text-ink-900">{item.product_name}</span>
                    <span className="text-ink-600">{formatBDT(displayMinor(item.total_minor))}</span>
                  </div>
                ))}
              </div>
            </div>

            <div className="flex justify-between border-t border-ink-100 pt-4">
              <span className="text-sm font-bold text-ink-900">Total</span>
              <span className="text-lg font-bold text-ink-900">{formatBDT(displayMinor(selected.total_minor))}</span>
            </div>

            {confirmRefund && (
              <div className="rounded-xl border border-danger-200 bg-danger-50 p-4 text-sm text-danger-700">
                This will request a full PipraPay refund for {selected.order_number}. Entitlements are revoked only after the backend confirms the refund.
              </div>
            )}
          </div>
        )}
      </Modal>
    </div>
  );
}
