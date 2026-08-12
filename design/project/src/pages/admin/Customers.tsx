import { useEffect, useState } from 'react';
import { Search, User, UserCheck } from 'lucide-react';
import { Badge } from '@/components/ui/Badge';
import { Card } from '@/components/ui/Card';
import { Input } from '@/components/ui/Input';
import { getAdminCustomers, type AdminCustomer } from '@/services/api/admin';

export default function AdminCustomers() {
  const [query, setQuery] = useState('');
  const [customers, setCustomers] = useState<AdminCustomer[]>([]);
  const [loading, setLoading] = useState(true);

  useEffect(() => {
    getAdminCustomers().then(setCustomers).finally(() => setLoading(false));
  }, []);

  const filtered = customers.filter((customer) =>
    !query || customer.name.toLowerCase().includes(query.toLowerCase()) || customer.email.toLowerCase().includes(query.toLowerCase())
  );

  return (
    <div>
      <div className="mb-6">
        <h1 className="font-display text-2xl font-bold text-ink-900">Customers</h1>
        <p className="mt-1 text-sm text-ink-500">{loading ? 'Loading customers...' : 'Registered customers.'}</p>
      </div>

      <div className="mb-4 max-w-xs">
        <Input placeholder="Search customers..." value={query} onChange={(e) => setQuery(e.target.value)} leftIcon={<Search className="h-4 w-4" />} />
      </div>

      <Card className="overflow-hidden">
        <div className="overflow-x-auto">
          <table className="w-full text-sm">
            <thead className="border-b border-ink-100 bg-ink-50/50 text-left text-xs text-ink-400">
              <tr>
                <th className="px-4 py-3 font-medium">Customer</th>
                <th className="px-4 py-3 font-medium">Type</th>
                <th className="px-4 py-3 font-medium">Orders</th>
                <th className="px-4 py-3 font-medium">Joined</th>
                <th className="px-4 py-3 font-medium">Last Activity</th>
              </tr>
            </thead>
            <tbody>
              {filtered.map((customer) => {
                const isAdmin = customer.roles?.some((role) => role.name === 'admin');
                return (
                  <tr key={customer.id} className="border-b border-ink-50 last:border-0 hover:bg-ink-50/30">
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
                    <td className="px-4 py-3">
                      <Badge tone={isAdmin ? 'violet' : 'brand'}>
                        {isAdmin ? <UserCheck className="h-3 w-3" /> : <User className="h-3 w-3" />}
                        {isAdmin ? 'admin' : 'customer'}
                      </Badge>
                    </td>
                    <td className="px-4 py-3 text-ink-600">{customer.orders_count || 0}</td>
                    <td className="px-4 py-3 text-ink-400">{new Date(customer.created_at).toLocaleDateString()}</td>
                    <td className="px-4 py-3 text-ink-400">{new Date(customer.updated_at).toLocaleDateString()}</td>
                  </tr>
                );
              })}
            </tbody>
          </table>
        </div>
      </Card>

      <div className="mt-4 flex items-center gap-2 rounded-xl border border-brand-200 bg-brand-50/50 p-4 text-sm text-ink-600">
        <User className="h-4 w-4 text-brand-600" />
        <span><strong className="text-ink-800">Guest purchases</strong> are claimed automatically when a buyer verifies an account with the same email.</span>
      </div>
    </div>
  );
}
