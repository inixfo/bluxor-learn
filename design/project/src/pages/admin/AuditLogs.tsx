import { useCallback, useEffect, useState } from 'react';
import { Filter, Search } from 'lucide-react';
import { Badge } from '@/components/ui/Badge';
import { Card } from '@/components/ui/Card';
import { Input } from '@/components/ui/Input';
import { getAdminAuditLogs, type AdminAuditLog } from '@/services/api/admin';

export default function AdminAuditLogs() {
  const [logs, setLogs] = useState<AdminAuditLog[]>([]);
  const [action, setAction] = useState('');
  const [entity, setEntity] = useState('');
  const [loading, setLoading] = useState(true);

  const load = useCallback(() => {
    setLoading(true);
    getAdminAuditLogs({ action, entity }).then(setLogs).finally(() => setLoading(false));
  }, [action, entity]);

  useEffect(() => {
    load();
  }, [load]);

  return (
    <div>
      <div className="mb-6">
        <h1 className="font-display text-2xl font-bold text-ink-900">Audit Logs</h1>
        <p className="mt-1 text-sm text-ink-500">{loading ? 'Loading audit history...' : 'Review sensitive admin and platform mutations.'}</p>
      </div>

      <div className="mb-4 grid gap-3 md:grid-cols-[1fr_1fr_auto]">
        <Input placeholder="Filter action..." value={action} onChange={(event) => setAction(event.target.value)} leftIcon={<Search className="h-4 w-4" />} />
        <Input placeholder="Filter entity..." value={entity} onChange={(event) => setEntity(event.target.value)} leftIcon={<Filter className="h-4 w-4" />} />
        <button onClick={load} className="rounded-lg bg-ink-900 px-4 py-2 text-sm font-semibold text-white">Apply</button>
      </div>

      <Card className="overflow-hidden">
        <div className="overflow-x-auto">
          <table className="w-full text-sm">
            <thead className="border-b border-ink-100 bg-ink-50/50 text-left text-xs text-ink-400">
              <tr>
                <th className="px-4 py-3 font-medium">Time</th>
                <th className="px-4 py-3 font-medium">Actor</th>
                <th className="px-4 py-3 font-medium">Action</th>
                <th className="px-4 py-3 font-medium">Entity</th>
                <th className="px-4 py-3 font-medium">Details</th>
              </tr>
            </thead>
            <tbody>
              {logs.map((log) => (
                <tr key={log.id} className="border-b border-ink-50 align-top last:border-0">
                  <td className="px-4 py-3 text-ink-500">{new Date(log.created_at).toLocaleString()}</td>
                  <td className="px-4 py-3">
                    <p className="font-semibold text-ink-900">{log.actor?.name || 'System'}</p>
                    {log.actor?.email && <p className="text-xs text-ink-400">{log.actor.email}</p>}
                  </td>
                  <td className="px-4 py-3"><Badge tone="brand">{log.action}</Badge></td>
                  <td className="px-4 py-3 text-ink-600">{log.auditable_type?.split('\\').pop() || '-'} {log.auditable_id || ''}</td>
                  <td className="max-w-xl px-4 py-3">
                    <pre className="max-h-32 overflow-auto rounded-lg bg-ink-950 p-3 text-xs text-ink-50">{JSON.stringify(log.metadata || {}, null, 2)}</pre>
                  </td>
                </tr>
              ))}
            </tbody>
          </table>
        </div>
      </Card>
    </div>
  );
}
