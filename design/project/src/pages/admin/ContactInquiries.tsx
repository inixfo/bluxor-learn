import { useEffect, useState } from 'react';
import { Link, useSearchParams } from 'react-router-dom';
import { Eye, Mail, Search } from 'lucide-react';
import { Badge, type Tone } from '@/components/ui/Badge';
import { Button } from '@/components/ui/Button';
import { Card, EmptyState } from '@/components/ui/Card';
import { Input, Select } from '@/components/ui/Input';
import { getAdminContactInquiries, type AdminContactInquiry, type AdminContactInquiryCounts, type AdminContactInquiryStatus } from '@/services/api/admin';

const statusTone: Record<AdminContactInquiryStatus, Tone> = {
  new: 'warning',
  read: 'brand',
  replied: 'violet',
  resolved: 'success',
  spam: 'danger',
};

const emptyCounts: AdminContactInquiryCounts = { all: 0, new: 0, read: 0, replied: 0, resolved: 0, spam: 0 };
const tabs: { label: string; value: '' | AdminContactInquiryStatus; countKey: keyof AdminContactInquiryCounts }[] = [
  { label: 'All', value: '', countKey: 'all' },
  { label: 'New', value: 'new', countKey: 'new' },
  { label: 'Replied', value: 'replied', countKey: 'replied' },
  { label: 'Resolved', value: 'resolved', countKey: 'resolved' },
];

export default function AdminContactInquiries() {
  const [params] = useSearchParams();
  const [query, setQuery] = useState('');
  const initialStatus = params.get('status') as AdminContactInquiryStatus | null;
  const [status, setStatus] = useState<'' | AdminContactInquiryStatus>(initialStatus && ['new', 'read', 'replied', 'resolved', 'spam'].includes(initialStatus) ? initialStatus : '');
  const [inquiries, setInquiries] = useState<AdminContactInquiry[]>([]);
  const [counts, setCounts] = useState<AdminContactInquiryCounts>(emptyCounts);
  const [loading, setLoading] = useState(true);

  useEffect(() => {
    setLoading(true);
    const timeout = window.setTimeout(() => {
      getAdminContactInquiries({ q: query, status })
        .then((result) => {
          setInquiries(result.items);
          setCounts(result.counts);
        })
        .finally(() => setLoading(false));
    }, 200);

    return () => window.clearTimeout(timeout);
  }, [query, status]);

  return (
    <div>
      <div className="mb-6">
        <h1 className="font-display text-2xl font-bold text-ink-900">Contact Inquiries</h1>
        <p className="mt-1 text-sm text-ink-500">{loading ? 'Loading messages...' : 'Review and respond to contact form submissions.'}</p>
      </div>

      <div className="mb-4 flex flex-wrap gap-2">
        {tabs.map((tab) => (
          <button
            key={tab.label}
            onClick={() => setStatus(tab.value)}
            className={`rounded-xl border px-3 py-2 text-sm font-semibold transition-colors ${
              status === tab.value ? 'border-brand-200 bg-brand-50 text-brand-700' : 'border-ink-200 bg-white text-ink-600 hover:bg-ink-50'
            }`}
          >
            {tab.label} <span className="ml-1 text-xs text-ink-400">{counts[tab.countKey]}</span>
          </button>
        ))}
      </div>

      <div className="mb-4 flex flex-col gap-3 sm:flex-row">
        <div className="flex-1">
          <Input placeholder="Search name, email, or subject..." value={query} onChange={(event) => setQuery(event.target.value)} leftIcon={<Search className="h-4 w-4" />} />
        </div>
        <Select value={status} onChange={(event) => setStatus(event.target.value as '' | AdminContactInquiryStatus)} className="sm:w-44">
          <option value="">All Status</option>
          <option value="new">New</option>
          <option value="read">Read</option>
          <option value="replied">Replied</option>
          <option value="resolved">Resolved</option>
          <option value="spam">Spam</option>
        </Select>
      </div>

      <Card className="overflow-hidden">
        {inquiries.length === 0 ? (
          <EmptyState icon={<Mail className="h-7 w-7" />} title={loading ? 'Loading inquiries' : 'No inquiries found'} description={loading ? 'Fetching contact messages.' : 'Contact form submissions will appear here.'} />
        ) : (
          <div className="overflow-x-auto">
            <table className="w-full text-sm">
              <thead className="border-b border-ink-100 bg-ink-50/50 text-left text-xs text-ink-400">
                <tr>
                  <th className="px-4 py-3 font-medium">Name</th>
                  <th className="px-4 py-3 font-medium">Email</th>
                  <th className="px-4 py-3 font-medium">Subject</th>
                  <th className="px-4 py-3 font-medium">Status</th>
                  <th className="px-4 py-3 font-medium">Received</th>
                  <th className="px-4 py-3 font-medium">Preview</th>
                  <th className="px-4 py-3"></th>
                </tr>
              </thead>
              <tbody>
                {inquiries.map((inquiry) => (
                  <tr key={inquiry.id} className="border-b border-ink-50 last:border-0 hover:bg-ink-50/30">
                    <td className="px-4 py-3 font-semibold text-ink-900">{inquiry.name}</td>
                    <td className="px-4 py-3 text-ink-600">{inquiry.email}</td>
                    <td className="px-4 py-3 font-medium text-ink-900">{inquiry.subject}</td>
                    <td className="px-4 py-3"><Badge tone={statusTone[inquiry.status]}>{inquiry.status}</Badge></td>
                    <td className="px-4 py-3 text-ink-500">{new Date(inquiry.created_at).toLocaleString()}</td>
                    <td className="max-w-xs px-4 py-3 text-ink-500">
                      <span className="line-clamp-2">{inquiry.message}</span>
                    </td>
                    <td className="px-4 py-3 text-right">
                      <Link to={`/admin/contact-inquiries/${inquiry.id}`}>
                        <Button size="sm" variant="ghost" leftIcon={<Eye className="h-4 w-4" />}>View</Button>
                      </Link>
                    </td>
                  </tr>
                ))}
              </tbody>
            </table>
          </div>
        )}
      </Card>
    </div>
  );
}
