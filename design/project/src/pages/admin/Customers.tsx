import { useEffect, useMemo, useState } from 'react';
import { Link, useSearchParams } from 'react-router-dom';
import { Ban, CheckCircle2, Eye, MessageCircle, Phone, Plus, Search, User, XCircle } from 'lucide-react';
import { Badge, type Tone } from '@/components/ui/Badge';
import { Button } from '@/components/ui/Button';
import { Card } from '@/components/ui/Card';
import { Input, Select } from '@/components/ui/Input';
import { Modal } from '@/components/ui/Modal';
import { formatBDT } from '@/data/store';
import {
  displayMinor,
  getAdminCustomer,
  getAdminCustomers,
  getAdminProducts,
  getAdminResources,
  grantAdminProductAccess,
  grantAdminResourceAccess,
  reactivateAdminCustomer,
  revokeAdminProductAccess,
  revokeAdminResourceAccess,
  suspendAdminCustomer,
  type AdminCustomer,
  type AdminCustomerDetail,
  type AdminProduct,
  type AdminResource,
} from '@/services/api/admin';
import { useToast } from '@/components/ui/Toast';

export default function AdminCustomers() {
  const [params, setParams] = useSearchParams();
  const [query, setQuery] = useState('');
  const [filter, setFilter] = useState('');
  const [customers, setCustomers] = useState<AdminCustomer[]>([]);
  const [selected, setSelected] = useState<AdminCustomerDetail | null>(null);
  const [products, setProducts] = useState<AdminProduct[]>([]);
  const [resources, setResources] = useState<AdminResource[]>([]);
  const [grant, setGrant] = useState({ type: 'product', product_id: '', resource_id: '', expires_at: '', reason: '' });
  const [revokeReason, setRevokeReason] = useState('');
  const [loading, setLoading] = useState(true);
  const [acting, setActing] = useState(false);
  const toast = useToast();

  const load = () => getAdminCustomers().then(setCustomers).finally(() => setLoading(false));

  useEffect(() => {
    load();
    getAdminProducts({ status: 'published' }).then(setProducts).catch(() => undefined);
    getAdminResources({ status: 'published' }).then(setResources).catch(() => undefined);
  }, []);

  useEffect(() => {
    const key = params.get('customer');
    if (key && customers.length > 0) void openCustomer(key);
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [customers, params]);

  const filtered = useMemo(() => customers.filter((customer) => {
    const haystack = `${customer.name} ${customer.email} ${customer.phone || ''} ${customer.last_order_number || ''}`.toLowerCase();
    if (query && !haystack.includes(query.toLowerCase())) return false;
    if (filter === 'registered' && !customer.has_account) return false;
    if (filter === 'guest' && customer.has_account) return false;
    if (filter === 'paid' && !(customer.paid_orders_count || 0)) return false;
    if (filter === 'unpaid' && !(customer.unpaid_orders_count || 0)) return false;
    if (filter === 'suspended' && customer.account_status !== 'suspended') return false;
    if (filter === 'active' && customer.account_status === 'suspended') return false;
    return true;
  }), [customers, filter, query]);

  const openCustomer = async (key: string) => {
    try {
      const detail = await getAdminCustomer(key);
      setSelected(detail);
      setParams({ customer: key });
    } catch {
      toast({ type: 'error', title: 'Could not load customer detail' });
    }
  };

  const close = () => {
    setSelected(null);
    setParams({});
  };

  const updateStatus = async (status: 'suspended' | 'active') => {
    const userId = selected?.summary.has_account ? selected.summary.id : null;
    const customerKey = selected?.summary.customer_key;
    if (!userId) return;
    setActing(true);
    try {
      await (status === 'suspended' ? suspendAdminCustomer(userId) : reactivateAdminCustomer(userId));
      const detail = await getAdminCustomer(customerKey || '');
      setSelected(detail);
      await load();
      toast({ type: 'success', title: status === 'suspended' ? 'Customer suspended' : 'Customer reactivated' });
    } catch {
      toast({ type: 'error', title: 'Could not update customer' });
    } finally {
      setActing(false);
    }
  };

  const refreshSelected = async () => {
    if (!selected) return;
    setSelected(await getAdminCustomer(selected.summary.customer_key));
    await load();
  };

  const grantAccess = async () => {
    if (!selected?.summary.has_account) return;
    setActing(true);
    try {
      if (grant.type === 'product') {
        await grantAdminProductAccess(selected.summary.id, { product_id: Number(grant.product_id), expires_at: grant.expires_at || undefined, reason: grant.reason });
      } else {
        await grantAdminResourceAccess(selected.summary.id, { resource_id: Number(grant.resource_id), expires_at: grant.expires_at || undefined, reason: grant.reason });
      }
      setGrant({ type: 'product', product_id: '', resource_id: '', expires_at: '', reason: '' });
      await refreshSelected();
      toast({ type: 'success', title: 'Access granted' });
    } catch {
      toast({ type: 'error', title: 'Grant failed', message: 'Select an item and provide an internal reason.' });
    } finally {
      setActing(false);
    }
  };

  const revokeProductGrant = async (entitlementId: number) => {
    setActing(true);
    try {
      await revokeAdminProductAccess(entitlementId, revokeReason || 'Manual grant revoked by admin');
      setRevokeReason('');
      await refreshSelected();
      toast({ type: 'success', title: 'Product access revoked' });
    } catch {
      toast({ type: 'error', title: 'Revoke failed', message: 'Only manual product grants can be revoked here.' });
    } finally {
      setActing(false);
    }
  };

  const revokeResourceGrant = async (grantId: number) => {
    setActing(true);
    try {
      await revokeAdminResourceAccess(grantId, revokeReason || 'Manual resource grant revoked by admin');
      setRevokeReason('');
      await refreshSelected();
      toast({ type: 'success', title: 'Resource access revoked' });
    } catch {
      toast({ type: 'error', title: 'Revoke failed' });
    } finally {
      setActing(false);
    }
  };

  return (
    <div>
      <div className="mb-6">
        <h1 className="font-display text-2xl font-bold text-ink-900">Customers</h1>
        <p className="mt-1 text-sm text-ink-500">{loading ? 'Loading customers...' : 'Registered and guest customer reporting.'}</p>
      </div>

      <div className="mb-4 flex flex-col gap-3 sm:flex-row">
        <div className="flex-1">
          <Input placeholder="Search by name, email, phone, or order..." value={query} onChange={(e) => setQuery(e.target.value)} leftIcon={<Search className="h-4 w-4" />} />
        </div>
        <Select value={filter} onChange={(e) => setFilter(e.target.value)} className="sm:w-56">
          <option value="">All Customers</option>
          <option value="registered">Registered accounts</option>
          <option value="guest">Guest-only customers</option>
          <option value="paid">Has paid orders</option>
          <option value="unpaid">Has unpaid orders</option>
          <option value="suspended">Suspended</option>
          <option value="active">Active</option>
        </Select>
      </div>

      <Card className="overflow-hidden">
        <div className="overflow-x-auto">
          <table className="w-full text-sm">
            <thead className="border-b border-ink-100 bg-ink-50/50 text-left text-xs text-ink-400">
              <tr>
                <th className="px-4 py-3 font-medium">Customer</th>
                <th className="px-4 py-3 font-medium">Phone</th>
                <th className="px-4 py-3 font-medium">Account</th>
                <th className="px-4 py-3 font-medium">Verified</th>
                <th className="px-4 py-3 font-medium">Orders</th>
                <th className="px-4 py-3 font-medium">Paid</th>
                <th className="px-4 py-3 font-medium">Total Spent</th>
                <th className="px-4 py-3 font-medium">Last Order</th>
                <th className="px-4 py-3 font-medium">Created</th>
                <th className="px-4 py-3"></th>
              </tr>
            </thead>
            <tbody>
              {filtered.map((customer) => (
                <tr key={customer.customer_key} className="cursor-pointer border-b border-ink-50 last:border-0 hover:bg-ink-50/30" onClick={() => { void openCustomer(customer.customer_key); }}>
                  <td className="px-4 py-3">
                    <div className="flex items-center gap-3">
                      <div className="flex h-9 w-9 items-center justify-center rounded-lg bg-gradient-to-br from-brand-500 to-violet-500 text-xs font-bold text-white">
                        {customer.name[0]}
                      </div>
                      <div>
                        <p className="font-semibold text-ink-900">{customer.name}</p>
                        <p className="text-xs text-ink-400">{customer.email}</p>
                      </div>
                    </div>
                  </td>
                  <td className="px-4 py-3 text-ink-600">{phoneDisplay(customer.phone)}</td>
                  <td className="px-4 py-3"><Badge tone={accountTone(customer)}>{customer.account_status_label || 'No Account'}</Badge></td>
                  <td className="px-4 py-3"><Badge tone={customer.verified ? 'success' : 'neutral'}>{customer.verified ? 'Verified' : 'Unverified'}</Badge></td>
                  <td className="px-4 py-3 text-ink-600">{customer.orders_count || 0}</td>
                  <td className="px-4 py-3 text-ink-600">{customer.paid_orders_count || 0}</td>
                  <td className="px-4 py-3 font-semibold text-ink-900">{formatBDT(displayMinor(customer.net_revenue_minor || 0))}</td>
                  <td className="px-4 py-3 text-ink-500">{customer.last_order_number || '—'}</td>
                  <td className="px-4 py-3 text-ink-400">{customer.created_at ? new Date(customer.created_at).toLocaleDateString() : '—'}</td>
                  <td className="px-4 py-3"><Button size="icon" variant="ghost"><Eye className="h-4 w-4" /></Button></td>
                </tr>
              ))}
            </tbody>
          </table>
        </div>
      </Card>

      <Modal
        open={!!selected}
        onClose={close}
        title={selected ? selected.summary.name : ''}
        size="lg"
        footer={selected?.summary.has_account ? (
          selected.summary.account_status === 'suspended'
            ? <Button leftIcon={<CheckCircle2 className="h-4 w-4" />} loading={acting} onClick={() => updateStatus('active')}>Reactivate</Button>
            : <Button variant="destructive" leftIcon={<Ban className="h-4 w-4" />} loading={acting} onClick={() => updateStatus('suspended')}>Suspend</Button>
        ) : undefined}
      >
        {selected && (
          <div className="space-y-5">
            <div className="grid gap-4 sm:grid-cols-2">
              <Info label="Email" value={selected.summary.email} />
              <div>
                <p className="text-xs text-ink-400">Phone</p>
                <p className="text-sm font-semibold text-ink-900">{phoneDisplay(selected.summary.phone)}</p>
                {selected.summary.phone && (
                  <div className="mt-2 flex gap-2">
                    <a href={phoneHref(selected.summary.phone)} className="inline-flex items-center gap-1.5 rounded-lg border border-ink-200 px-2.5 py-1.5 text-xs font-semibold text-ink-700 hover:bg-ink-50">
                      <Phone className="h-3.5 w-3.5" />
                      Call
                    </a>
                    <a href={whatsappHref(selected.summary.phone)} target="_blank" rel="noreferrer" className="inline-flex items-center gap-1.5 rounded-lg border border-ink-200 px-2.5 py-1.5 text-xs font-semibold text-ink-700 hover:bg-ink-50">
                      <MessageCircle className="h-3.5 w-3.5" />
                      WhatsApp
                    </a>
                  </div>
                )}
              </div>
              <Info label="Account" value={selected.summary.account_status_label || 'No Account'} />
              <Info label="Email verified" value={selected.summary.verified ? 'Yes' : 'No'} />
              <Info label="Auth provider" value={selected.summary.has_account ? (selected.summary.auth_provider || 'password') : '—'} />
              <Info label="Account created" value={selected.summary.has_account && selected.summary.created_at ? new Date(selected.summary.created_at).toLocaleString() : '—'} />
            </div>

            <div className="grid gap-3 sm:grid-cols-4">
              <Stat label="Orders" value={selected.summary.orders_count || 0} />
              <Stat label="Paid" value={selected.summary.paid_orders_count || 0} />
              <Stat label="Unpaid" value={selected.summary.unpaid_orders_count || 0} />
              <Stat label="Spent" value={formatBDT(displayMinor(selected.summary.net_revenue_minor || 0))} />
            </div>

            <div className="rounded-xl border border-ink-100 p-4">
              <p className="mb-3 text-xs font-semibold text-ink-400">Orders</p>
              <div className="space-y-2">
                {selected.orders.map((order) => (
                  <Link key={order.id} to="/admin/orders" className="flex items-center justify-between rounded-lg border border-ink-100 px-3 py-2 text-sm hover:bg-ink-50">
                    <span>
                      <span className="font-semibold text-ink-900">{order.order_number}</span>
                      <span className="ml-2 text-ink-400">{order.checkout_type_label || 'Unknown'}</span>
                    </span>
                    <span className="font-semibold text-ink-900">{formatBDT(displayMinor(order.total_minor))}</span>
                  </Link>
                ))}
                {selected.orders.length === 0 && <p className="text-sm text-ink-400">No orders found.</p>}
              </div>
            </div>

            <div className="rounded-xl border border-ink-100 p-4">
              <p className="mb-3 text-xs font-semibold text-ink-400">Product Access</p>
              {selected.entitlements.length ? (
                <div className="space-y-2">
                  {selected.entitlements.map((entitlement) => (
                    <div key={entitlement.id} className="flex justify-between text-sm">
                      <span className="font-semibold text-ink-900">{entitlement.product_name || `Product #${entitlement.product_id}`} <span className="font-normal text-ink-400">({entitlement.access_label || 'Purchased Access'})</span></span>
                      <span className="flex items-center gap-2">
                        <Badge tone={entitlement.status === 'active' ? 'success' : 'warning'}>{entitlement.status}</Badge>
                        {entitlement.can_revoke && <Button size="sm" variant="outline" loading={acting} leftIcon={<XCircle className="h-4 w-4" />} onClick={() => revokeProductGrant(entitlement.id)}>Revoke</Button>}
                      </span>
                    </div>
                  ))}
                </div>
              ) : <p className="text-sm text-ink-400">—</p>}
            </div>

            <div className="rounded-xl border border-ink-100 p-4">
              <p className="mb-3 text-xs font-semibold text-ink-400">Manual Resource Access</p>
              {selected.resource_grants?.length ? (
                <div className="space-y-2">
                  {selected.resource_grants.map((resource) => (
                    <div key={resource.id} className="flex justify-between gap-3 text-sm">
                      <span className="font-semibold text-ink-900">{resource.resource_title || `Resource #${resource.resource_id}`}</span>
                      <span className="flex items-center gap-2">
                        <Badge tone={resource.status === 'active' ? 'success' : 'warning'}>{resource.status}</Badge>
                        {resource.can_revoke && <Button size="sm" variant="outline" loading={acting} leftIcon={<XCircle className="h-4 w-4" />} onClick={() => revokeResourceGrant(resource.id)}>Revoke</Button>}
                      </span>
                    </div>
                  ))}
                </div>
              ) : <p className="text-sm text-ink-400">-</p>}
            </div>

            {selected.summary.has_account && (
              <div className="rounded-xl border border-brand-100 bg-brand-50/50 p-4">
                <p className="mb-3 text-xs font-semibold text-brand-700">Grant Access</p>
                <div className="grid gap-3 sm:grid-cols-2">
                  <Select value={grant.type} onChange={(event) => setGrant((prev) => ({ ...prev, type: event.target.value }))}>
                    <option value="product">Product</option>
                    <option value="resource">Resource</option>
                  </Select>
                  {grant.type === 'product' ? (
                    <Select value={grant.product_id} onChange={(event) => setGrant((prev) => ({ ...prev, product_id: event.target.value }))}>
                      <option value="">Select product</option>
                      {products.map((product) => <option key={product.id} value={product.id}>{product.name}</option>)}
                    </Select>
                  ) : (
                    <Select value={grant.resource_id} onChange={(event) => setGrant((prev) => ({ ...prev, resource_id: event.target.value }))}>
                      <option value="">Select resource</option>
                      {resources.map((resource) => <option key={resource.id} value={resource.id}>{resource.title}</option>)}
                    </Select>
                  )}
                  <Input type="datetime-local" value={grant.expires_at} onChange={(event) => setGrant((prev) => ({ ...prev, expires_at: event.target.value }))} />
                  <Input placeholder="Internal reason" value={grant.reason} onChange={(event) => setGrant((prev) => ({ ...prev, reason: event.target.value }))} />
                </div>
                <Input className="mt-3" placeholder="Revocation reason used by revoke buttons" value={revokeReason} onChange={(event) => setRevokeReason(event.target.value)} />
                <Button className="mt-3" loading={acting} leftIcon={<Plus className="h-4 w-4" />} disabled={!grant.reason || (grant.type === 'product' ? !grant.product_id : !grant.resource_id)} onClick={grantAccess}>
                  Grant Access
                </Button>
              </div>
            )}
          </div>
        )}
      </Modal>

      <div className="mt-4 flex items-center gap-2 rounded-xl border border-brand-200 bg-brand-50/50 p-4 text-sm text-ink-600">
        <User className="h-4 w-4 text-brand-600" />
        <span><strong className="text-ink-800">Guest customers</strong> remain guests. Claimed purchases show Registered/Claimed without changing the historical checkout type.</span>
      </div>
    </div>
  );
}

function accountTone(customer: AdminCustomer): Tone {
  if (customer.account_status === 'suspended') return 'danger';
  return customer.has_account ? 'success' : 'neutral';
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

function Info({ label, value }: { label: string; value: string }) {
  return (
    <div>
      <p className="text-xs text-ink-400">{label}</p>
      <p className="text-sm font-semibold text-ink-900">{value}</p>
    </div>
  );
}

function Stat({ label, value }: { label: string; value: string | number }) {
  return (
    <div className="rounded-xl bg-ink-50 p-4">
      <p className="text-xs text-ink-400">{label}</p>
      <p className="mt-1 text-lg font-bold text-ink-900">{value}</p>
    </div>
  );
}
