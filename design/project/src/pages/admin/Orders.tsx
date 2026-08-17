import { useEffect, useState } from 'react';
import { Ban, Eye, Mail, MessageCircle, Phone, RotateCcw, Save, Search, User } from 'lucide-react';
import { Badge, type Tone } from '@/components/ui/Badge';
import { Button } from '@/components/ui/Button';
import { Card } from '@/components/ui/Card';
import { Input, Select } from '@/components/ui/Input';
import { Modal } from '@/components/ui/Modal';
import { formatBDT } from '@/data/store';
import {
  cancelAdminOrder,
  displayMinor,
  getAdminOrder,
  getAdminOrders,
  refundAdminOrder,
  resendAdminOrderEmail,
  updateAdminOrderNotes,
  type AdminOrder,
} from '@/services/api/admin';
import { useToast } from '@/components/ui/Toast';
import { formatAdminDateTime } from '@/utils/datetime';

const statusTone: Record<string, Tone> = { paid: 'success', pending: 'warning', refunded: 'danger', failed: 'danger', completed: 'success' };

export default function AdminOrders() {
  const [query, setQuery] = useState('');
  const [statusFilter, setStatusFilter] = useState('');
  const [orders, setOrders] = useState<AdminOrder[]>([]);
  const [selected, setSelected] = useState<AdminOrder | null>(null);
  const [notes, setNotes] = useState('');
  const [loading, setLoading] = useState(true);
  const [refunding, setRefunding] = useState(false);
  const [acting, setActing] = useState(false);
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

  const openOrder = async (order: AdminOrder) => {
    setConfirmRefund(false);
    setSelected(order);
    setNotes(order.admin_notes || '');
    try {
      const detail = await getAdminOrder(order.id);
      setSelected(detail);
      setNotes(detail.admin_notes || '');
    } catch {
      toast({ type: 'error', title: 'Could not load order detail' });
    }
  };

  const cancelOrder = async () => {
    if (!selected) return;
    setActing(true);
    try {
      const next = await cancelAdminOrder(selected.id);
      setSelected(next);
      await load();
      toast({ type: 'success', title: 'Order cancelled' });
    } catch {
      toast({ type: 'error', title: 'Could not cancel order', message: 'Only unpaid orders can be cancelled.' });
    } finally {
      setActing(false);
    }
  };

  const resendEmail = async () => {
    if (!selected) return;
    setActing(true);
    try {
      const message = await resendAdminOrderEmail(selected.id);
      toast({ type: 'success', title: 'Email queued', message });
    } catch {
      toast({ type: 'error', title: 'Could not resend email', message: 'Purchase emails are only available for paid orders.' });
    } finally {
      setActing(false);
    }
  };

  const saveNotes = async () => {
    if (!selected) return;
    setActing(true);
    try {
      const next = await updateAdminOrderNotes(selected.id, notes);
      setSelected(next);
      setNotes(next.admin_notes || '');
      toast({ type: 'success', title: 'Notes saved' });
    } catch {
      toast({ type: 'error', title: 'Could not save notes' });
    } finally {
      setActing(false);
    }
  };

  const filtered = orders.filter((order) => {
    const customer = `${order.customer_name || ''} ${order.customer_email} ${order.customer_phone || ''}`.toLowerCase();
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
          <Input placeholder="Search by order ID, customer, email, or phone..." value={query} onChange={(e) => setQuery(e.target.value)} leftIcon={<Search className="h-4 w-4" />} />
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
                <th className="px-4 py-3 font-medium">Checkout</th>
                <th className="px-4 py-3 font-medium">Account</th>
                <th className="px-4 py-3 font-medium">Products</th>
                <th className="px-4 py-3 font-medium">Amount</th>
                <th className="px-4 py-3 font-medium">Payment</th>
                <th className="px-4 py-3 font-medium">Source</th>
                <th className="px-4 py-3 font-medium">Status</th>
                <th className="px-4 py-3 font-medium">Created</th>
                <th className="px-4 py-3"></th>
              </tr>
            </thead>
            <tbody>
              {filtered.map((order) => (
                <tr key={order.id} className="cursor-pointer border-b border-ink-50 last:border-0 hover:bg-ink-50/30" onClick={() => { void openOrder(order); }}>
                  <td className="px-4 py-3 font-semibold text-ink-900">{order.order_number}</td>
                  <td className="px-4 py-3">
                    <div>
                      <p className="text-ink-900">{order.customer_name || 'Guest'}</p>
                      <p className="text-xs text-ink-400">{order.customer_email}</p>
                      <p className="text-xs text-ink-400">{phoneDisplay(order.customer_phone)}</p>
                    </div>
                  </td>
                  <td className="px-4 py-3"><Badge tone={checkoutTone(order.checkout_type)}>{order.checkout_type_label || 'Unknown'}</Badge></td>
                  <td className="px-4 py-3"><Badge tone={accountTone(order.current_account_status)}>{order.current_account_status_label || 'No Account'}</Badge></td>
                  <td className="px-4 py-3 text-ink-600">{order.items.map((item) => item.product_name).join(', ')}</td>
                  <td className="px-4 py-3 font-semibold text-ink-900">{formatBDT(displayMinor(order.total_minor))}</td>
                  <td className="px-4 py-3 text-ink-600">{order.payment_gateway || 'PipraPay'}</td>
                  <td className="px-4 py-3">
                    <p className="text-xs font-semibold text-ink-700">{order.attribution?.source || 'Unknown'}</p>
                    <p className="text-xs text-ink-400">{order.attribution?.campaign || order.attribution?.medium || '-'}</p>
                  </td>
                  <td className="px-4 py-3"><Badge tone={statusTone[order.payment_status] || 'neutral'}>{order.payment_status}</Badge></td>
                  <td className="px-4 py-3 text-xs text-ink-500">{formatAdminDateTime(order.created_at)}</td>
                  <td className="px-4 py-3">
                    <div className="flex items-center gap-1">
                      {order.customer_phone && (
                        <>
                          <a
                            href={phoneHref(order.customer_phone)}
                            onClick={(event) => event.stopPropagation()}
                            className="inline-flex h-9 w-9 items-center justify-center rounded-xl text-ink-700 transition-colors hover:bg-ink-100"
                            title="Call customer"
                          >
                            <Phone className="h-4 w-4" />
                          </a>
                          <a
                            href={whatsappHref(order.customer_phone)}
                            target="_blank"
                            rel="noreferrer"
                            onClick={(event) => event.stopPropagation()}
                            className="inline-flex h-9 w-9 items-center justify-center rounded-xl text-ink-700 transition-colors hover:bg-ink-100"
                            title="Message on WhatsApp"
                          >
                            <MessageCircle className="h-4 w-4" />
                          </a>
                        </>
                      )}
                      <Button size="icon" variant="ghost"><Eye className="h-4 w-4" /></Button>
                    </div>
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
              <Button variant="outline" leftIcon={<Mail className="h-4 w-4" />} loading={acting} disabled={!selected?.actions?.can_resend_email} onClick={resendEmail}>Resend Email</Button>
              <Button variant="outline" leftIcon={<Ban className="h-4 w-4" />} loading={acting} disabled={!selected?.actions?.can_cancel} onClick={cancelOrder}>Cancel Unpaid</Button>
              <Button variant="destructive" leftIcon={<RotateCcw className="h-4 w-4" />} disabled={!selected?.actions?.can_refund} onClick={() => setConfirmRefund(true)}>Refund</Button>
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
                <p className="text-xs text-ink-500">{phoneDisplay(selected.customer_phone)}</p>
                {selected.customer_phone && (
                  <div className="mt-2 flex gap-2">
                    <a href={phoneHref(selected.customer_phone)} className="inline-flex items-center gap-1.5 rounded-lg border border-ink-200 px-2.5 py-1.5 text-xs font-semibold text-ink-700 hover:bg-ink-50">
                      <Phone className="h-3.5 w-3.5" />
                      Call
                    </a>
                    <a href={whatsappHref(selected.customer_phone)} target="_blank" rel="noreferrer" className="inline-flex items-center gap-1.5 rounded-lg border border-ink-200 px-2.5 py-1.5 text-xs font-semibold text-ink-700 hover:bg-ink-50">
                      <MessageCircle className="h-3.5 w-3.5" />
                      WhatsApp
                    </a>
                  </div>
                )}
              </div>
              <div>
                <p className="text-xs text-ink-400">Checkout type</p>
                <Badge tone={checkoutTone(selected.checkout_type)}>{selected.checkout_type_label || 'Unknown'}</Badge>
              </div>
              <div>
                <p className="text-xs text-ink-400">Current account</p>
                <div className="mt-1 flex items-center gap-2">
                  <Badge tone={accountTone(selected.current_account_status)}>{selected.current_account_status_label || 'No Account'}</Badge>
                  {selected.user_id && (
                    <a href={`/admin/customers?customer=${encodeURIComponent(selected.customer_key || '')}`} className="inline-flex items-center gap-1 text-xs font-semibold text-brand-600">
                      <User className="h-3.5 w-3.5" />
                      View customer
                    </a>
                  )}
                </div>
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
                <p className="text-xs text-ink-400">Created</p>
                <p className="text-sm font-semibold text-ink-900">{formatAdminDateTime(selected.created_at)}</p>
              </div>
              <div>
                <p className="text-xs text-ink-400">Payment completed</p>
                <p className="text-sm font-semibold text-ink-900">{formatAdminDateTime(selected.payment_completed_at)}</p>
              </div>
            </div>

            <div className="rounded-xl border border-ink-100 p-4">
              <p className="mb-3 text-xs font-semibold text-ink-400">Order</p>
              <div className="grid gap-3 text-sm sm:grid-cols-4">
                <Info label="Subtotal" value={formatBDT(displayMinor(selected.subtotal_minor ?? selected.total_minor))} />
                <Info label="Discount" value={`-${formatBDT(displayMinor(selected.discount_minor || 0))}`} />
                <Info label="Total" value={formatBDT(displayMinor(selected.total_minor))} />
                <Info label="Currency" value={selected.currency} />
              </div>
            </div>

            <div className="rounded-xl border border-ink-100 p-4">
              <p className="mb-3 text-xs font-semibold text-ink-400">Products</p>
              <div className="space-y-2">
                {selected.items.map((item) => (
                  <div key={`${item.product_name}-${item.id || item.product_slug}`} className="flex justify-between gap-4 text-sm">
                    <span className="font-semibold text-ink-900">{item.product_name} <span className="font-normal text-ink-400">x{item.quantity || 1}</span></span>
                    <span className="text-ink-600">{formatBDT(displayMinor(item.total_minor))}</span>
                  </div>
                ))}
              </div>
            </div>

            <div className="rounded-xl border border-ink-100 p-4">
              <p className="mb-3 text-xs font-semibold text-ink-400">Payment Transactions</p>
              {selected.payment_transactions?.length ? (
                <div className="space-y-2">
                  {selected.payment_transactions.map((payment) => (
                    <div key={payment.id} className="grid gap-2 text-sm sm:grid-cols-4">
                      <Info label="Provider" value={payment.gateway || '—'} />
                      <Info label="Transaction" value={payment.provider_transaction_id || payment.provider_reference || '—'} />
                      <Info label="Status" value={payment.status || '—'} />
                      <Info label="Paid at" value={payment.paid_at ? new Date(payment.paid_at).toLocaleString() : '—'} />
                    </div>
                  ))}
                </div>
              ) : <p className="text-sm text-ink-400">—</p>}
            </div>

            <div className="rounded-xl border border-ink-100 p-4">
              <p className="mb-3 text-xs font-semibold text-ink-400">Attribution</p>
              <div className="grid gap-3 text-sm sm:grid-cols-3">
                <Info label="Source" value={selected.attribution?.source || 'Unknown'} />
                <Info label="Medium" value={selected.attribution?.medium || '-'} />
                <Info label="Campaign" value={selected.attribution?.campaign || '-'} />
                <Info label="Content" value={selected.attribution?.content || '-'} />
                <Info label="Term" value={selected.attribution?.term || '-'} />
                <Info label="Offer" value={selected.attribution?.offer_key || '-'} />
                <Info label="Landing" value={selected.attribution?.path || selected.attribution?.landing_url || '-'} />
                <Info label="Referrer" value={selected.attribution?.referrer_host || selected.attribution?.referrer || '-'} />
                <Info label="Session" value={selected.attribution?.session_id || '-'} />
              </div>
            </div>

            <div className="rounded-xl border border-ink-100 p-4">
              <p className="mb-3 text-xs font-semibold text-ink-400">Entitlements</p>
              {selected.entitlements?.length ? (
                <div className="space-y-2">
                  {selected.entitlements.map((entitlement) => (
                    <div key={entitlement.id} className="flex justify-between text-sm">
                      <span className="font-semibold text-ink-900">{entitlement.product_name || `Product #${entitlement.product_id}`}</span>
                      <Badge tone={entitlement.status === 'active' ? 'success' : 'warning'}>{entitlement.status}</Badge>
                    </div>
                  ))}
                </div>
              ) : <p className="text-sm text-ink-400">—</p>}
            </div>

            <div className="rounded-xl border border-ink-100 p-4">
              <div className="mb-3 flex items-center justify-between gap-3">
                <p className="text-xs font-semibold text-ink-400">Internal Notes</p>
                <Button size="sm" variant="outline" leftIcon={<Save className="h-4 w-4" />} loading={acting} onClick={saveNotes}>Save Notes</Button>
              </div>
              <textarea
                value={notes}
                onChange={(event) => setNotes(event.target.value)}
                className="min-h-24 w-full rounded-xl border border-ink-200 bg-white px-3 py-2 text-sm outline-none focus:border-brand-400 focus:ring-2 focus:ring-brand-100"
                placeholder="Private admin notes..."
              />
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

function phoneDisplay(phone?: string | null): string {
  return phone?.trim() || '—';
}

function phoneHref(phone: string): string {
  return `tel:${phone.replace(/[^\d+]/g, '')}`;
}

function whatsappHref(phone: string): string {
  return `https://wa.me/${phone.replace(/\D/g, '')}`;
}

function checkoutTone(type?: string | null): Tone {
  if (type === 'guest') return 'warning';
  if (type === 'account') return 'brand';
  return 'neutral';
}

function accountTone(status?: string | null): Tone {
  if (status === 'registered' || status === 'claimed') return 'success';
  if (status === 'suspended') return 'danger';
  return 'neutral';
}

function Info({ label, value }: { label: string; value: string }) {
  return (
    <div>
      <p className="text-xs text-ink-400">{label}</p>
      <p className="font-semibold text-ink-900">{value}</p>
    </div>
  );
}
