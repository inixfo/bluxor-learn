import { useEffect, useState } from 'react';
import { Archive, MoreHorizontal, Pause, Plus, Save, Ticket } from 'lucide-react';
import { Badge, type Tone } from '@/components/ui/Badge';
import { Button } from '@/components/ui/Button';
import { Card } from '@/components/ui/Card';
import { Input, Select } from '@/components/ui/Input';
import { Modal } from '@/components/ui/Modal';
import { useToast } from '@/components/ui/Toast';
import { formatBDT } from '@/data/store';
import { archiveAdminCoupon, createAdminCoupon, displayMinor, getAdminCoupons, pauseAdminCoupon, updateAdminCoupon, type AdminCoupon } from '@/services/api/admin';

const statusTone: Record<string, Tone> = { active: 'success', expired: 'neutral', paused: 'warning', archived: 'neutral' };

export default function AdminCoupons() {
  const toast = useToast();
  const [coupons, setCoupons] = useState<AdminCoupon[]>([]);
  const [loading, setLoading] = useState(true);
  const [editing, setEditing] = useState<AdminCoupon | null>(null);
  const [open, setOpen] = useState(false);
  const [form, setForm] = useState({
    code: '',
    type: 'percent' as 'percent' | 'fixed',
    amount: '',
    percentage: '20',
    starts_at: '',
    expires_at: '',
    usage_limit: '',
    per_customer_limit: '',
    minimum_order: '0',
  });

  const load = () => getAdminCoupons().then(setCoupons).finally(() => setLoading(false));
  useEffect(() => { load(); }, []);

  const edit = (coupon?: AdminCoupon) => {
    setEditing(coupon || null);
    setForm({
      code: coupon?.code || '',
      type: coupon?.type || 'percent',
      amount: coupon?.amount_minor ? String(displayMinor(coupon.amount_minor)) : '',
      percentage: coupon?.percentage_bps ? String(coupon.percentage_bps / 100) : '20',
      starts_at: coupon?.starts_at?.slice(0, 10) || '',
      expires_at: coupon?.expires_at?.slice(0, 10) || '',
      usage_limit: coupon?.usage_limit ? String(coupon.usage_limit) : '',
      per_customer_limit: coupon?.per_customer_limit ? String(coupon.per_customer_limit) : '',
      minimum_order: coupon?.minimum_order_minor ? String(displayMinor(coupon.minimum_order_minor)) : '0',
    });
    setOpen(true);
  };

  const save = async () => {
    const payload = {
      code: form.code,
      type: form.type,
      amount_minor: form.type === 'fixed' ? Math.round(Number(form.amount || 0) * 100) : null,
      percentage_bps: form.type === 'percent' ? Math.round(Number(form.percentage || 0) * 100) : null,
      starts_at: form.starts_at || null,
      expires_at: form.expires_at || null,
      usage_limit: form.usage_limit ? Number(form.usage_limit) : null,
      per_customer_limit: form.per_customer_limit ? Number(form.per_customer_limit) : null,
      minimum_order_minor: Math.round(Number(form.minimum_order || 0) * 100),
      currency: 'BDT',
      status: 'active',
    };

    try {
      if (editing) {
        await updateAdminCoupon(editing.id, payload);
      } else {
        await createAdminCoupon(payload);
      }
      setOpen(false);
      toast({ type: 'success', title: 'Coupon saved' });
      load();
    } catch {
      toast({ type: 'error', title: 'Coupon save failed', message: 'Check the code, discount, and dates.' });
    }
  };

  const pause = async (coupon: AdminCoupon) => {
    await pauseAdminCoupon(coupon.id);
    toast({ type: 'success', title: 'Coupon paused' });
    load();
  };

  const archive = async (coupon: AdminCoupon) => {
    await archiveAdminCoupon(coupon.id);
    toast({ type: 'success', title: 'Coupon archived' });
    load();
  };

  return (
    <div>
      <div className="mb-6 flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
        <div>
          <h1 className="font-display text-2xl font-bold text-ink-900">Coupons</h1>
          <p className="mt-1 text-sm text-ink-500">{loading ? 'Loading coupons...' : 'Create and manage discount codes.'}</p>
        </div>
        <Button leftIcon={<Plus className="h-4 w-4" />} onClick={() => edit()}>Create Coupon</Button>
      </div>

      <Card className="overflow-hidden">
        <div className="overflow-x-auto">
          <table className="w-full text-sm">
            <thead className="border-b border-ink-100 bg-ink-50/50 text-left text-xs text-ink-400">
              <tr>
                <th className="px-4 py-3 font-medium">Code</th>
                <th className="px-4 py-3 font-medium">Discount</th>
                <th className="px-4 py-3 font-medium">Usage</th>
                <th className="px-4 py-3 font-medium">Expiry</th>
                <th className="px-4 py-3 font-medium">Status</th>
                <th className="px-4 py-3"></th>
              </tr>
            </thead>
            <tbody>
              {coupons.map((coupon) => (
                <tr key={coupon.id} className="border-b border-ink-50 last:border-0 hover:bg-ink-50/30">
                  <td className="px-4 py-3"><div className="flex items-center gap-2"><div className="flex h-8 w-8 items-center justify-center rounded-lg bg-brand-100 text-brand-600"><Ticket className="h-4 w-4" /></div><span className="font-mono font-bold text-ink-900">{coupon.code}</span></div></td>
                  <td className="px-4 py-3 font-semibold text-ink-900">{coupon.type === 'percent' ? `${(coupon.percentage_bps || 0) / 100}%` : formatBDT(displayMinor(coupon.amount_minor))}</td>
                  <td className="px-4 py-3 text-ink-600">{coupon.usage_limit ? `0 / ${coupon.usage_limit}` : 'Unlimited'}</td>
                  <td className="px-4 py-3 text-ink-400">{coupon.expires_at ? new Date(coupon.expires_at).toLocaleDateString() : 'No expiry'}</td>
                  <td className="px-4 py-3"><Badge tone={statusTone[coupon.status] || 'neutral'}>{coupon.status}</Badge></td>
                  <td className="px-4 py-3">
                    <div className="flex gap-1">
                      <Button size="icon" variant="ghost" onClick={() => edit(coupon)}><MoreHorizontal className="h-4 w-4" /></Button>
                      <Button size="icon" variant="ghost" onClick={() => pause(coupon)}><Pause className="h-4 w-4" /></Button>
                      <Button size="icon" variant="ghost" onClick={() => archive(coupon)}><Archive className="h-4 w-4" /></Button>
                    </div>
                  </td>
                </tr>
              ))}
            </tbody>
          </table>
        </div>
      </Card>

      <Modal open={open} onClose={() => setOpen(false)} title={editing ? `Edit ${editing.code}` : 'Create Coupon'} size="lg" footer={<><Button variant="outline" onClick={() => setOpen(false)}>Cancel</Button><Button leftIcon={<Save className="h-4 w-4" />} onClick={save}>Save</Button></>}>
        <div className="grid gap-4 sm:grid-cols-2">
          <Input label="Code" value={form.code} onChange={(event) => setForm((prev) => ({ ...prev, code: event.target.value.toUpperCase() }))} placeholder="LAUNCH20" />
          <Select label="Discount type" value={form.type} onChange={(event) => setForm((prev) => ({ ...prev, type: event.target.value as 'percent' | 'fixed' }))}>
            <option value="percent">Percentage</option>
            <option value="fixed">Fixed amount</option>
          </Select>
          {form.type === 'percent' ? (
            <Input label="Percentage" type="number" value={form.percentage} onChange={(event) => setForm((prev) => ({ ...prev, percentage: event.target.value }))} />
          ) : (
            <Input label="Fixed amount (BDT)" type="number" value={form.amount} onChange={(event) => setForm((prev) => ({ ...prev, amount: event.target.value }))} />
          )}
          <Input label="Minimum order (BDT)" type="number" value={form.minimum_order} onChange={(event) => setForm((prev) => ({ ...prev, minimum_order: event.target.value }))} />
          <Input label="Start date" type="date" value={form.starts_at} onChange={(event) => setForm((prev) => ({ ...prev, starts_at: event.target.value }))} />
          <Input label="Expiry date" type="date" value={form.expires_at} onChange={(event) => setForm((prev) => ({ ...prev, expires_at: event.target.value }))} />
          <Input label="Usage limit" type="number" value={form.usage_limit} onChange={(event) => setForm((prev) => ({ ...prev, usage_limit: event.target.value }))} />
          <Input label="Per-customer limit" type="number" value={form.per_customer_limit} onChange={(event) => setForm((prev) => ({ ...prev, per_customer_limit: event.target.value }))} />
        </div>
      </Modal>
    </div>
  );
}
