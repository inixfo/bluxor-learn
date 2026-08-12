import { useEffect, useState } from 'react';
import { Link } from 'react-router-dom';
import { Eye, MoreHorizontal, Search, TrendingUp, Upload } from 'lucide-react';
import { Badge, type Tone } from '@/components/ui/Badge';
import { Button } from '@/components/ui/Button';
import { Card } from '@/components/ui/Card';
import { Input } from '@/components/ui/Input';
import { formatBDT } from '@/data/store';
import { displayMinor, getLandingAnalytics, getLandingPages, type LandingPageAdmin } from '@/services/api/landing';

const statusTone: Record<string, Tone> = { published: 'success', draft: 'warning', unpublished: 'neutral', archived: 'neutral' };

export default function AdminLandingPages() {
  const [pages, setPages] = useState<LandingPageAdmin[]>([]);
  const [metrics, setMetrics] = useState<Record<number, { visitors: number; conversion_rate: number; revenue_minor: number }>>({});
  const [query, setQuery] = useState('');
  const [loading, setLoading] = useState(true);

  useEffect(() => {
    getLandingPages()
      .then((items) => {
        setPages(items);
        items.forEach((page) => {
          getLandingAnalytics(page.id).then((analytics) => {
            setMetrics((prev) => ({ ...prev, [page.id]: analytics }));
          });
        });
      })
      .finally(() => setLoading(false));
  }, []);

  const filtered = pages.filter((page) => !query || page.name.toLowerCase().includes(query.toLowerCase()) || page.slug.toLowerCase().includes(query.toLowerCase()));

  return (
    <div>
      <div className="mb-6 flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
        <div>
          <h1 className="font-display text-2xl font-bold text-ink-900">Landing Pages</h1>
          <p className="mt-1 text-sm text-ink-500">{loading ? 'Loading landing pages...' : 'Manage custom landing pages for your products.'}</p>
        </div>
        <Link to="/admin/landing-pages/upload">
          <Button leftIcon={<Upload className="h-4 w-4" />}>Upload Landing Page</Button>
        </Link>
      </div>

      <div className="mb-4 max-w-xs">
        <Input placeholder="Search landing pages..." value={query} onChange={(event) => setQuery(event.target.value)} leftIcon={<Search className="h-4 w-4" />} />
      </div>

      <Card className="overflow-hidden">
        <div className="overflow-x-auto">
          <table className="w-full text-sm">
            <thead className="border-b border-ink-100 bg-ink-50/50 text-left text-xs text-ink-400">
              <tr>
                <th className="px-4 py-3 font-medium">Page Name</th>
                <th className="px-4 py-3 font-medium">Slug</th>
                <th className="px-4 py-3 font-medium">Product</th>
                <th className="px-4 py-3 font-medium">Version</th>
                <th className="px-4 py-3 font-medium">Status</th>
                <th className="px-4 py-3 font-medium">Visitors</th>
                <th className="px-4 py-3 font-medium">CVR</th>
                <th className="px-4 py-3 font-medium">Revenue</th>
                <th className="px-4 py-3 font-medium">Updated</th>
                <th className="px-4 py-3"></th>
              </tr>
            </thead>
            <tbody>
              {filtered.map((page) => {
                const analytics = metrics[page.id] || { visitors: 0, conversion_rate: 0, revenue_minor: 0 };
                return (
                  <tr key={page.id} className="border-b border-ink-50 last:border-0 hover:bg-ink-50/30">
                    <td className="px-4 py-3">
                      <Link to={`/admin/landing-pages/${page.id}`} className="font-semibold text-ink-900 hover:text-brand-700">
                        {page.name}
                      </Link>
                    </td>
                    <td className="px-4 py-3 font-mono text-xs text-ink-500">/go/{page.slug}</td>
                    <td className="px-4 py-3 text-ink-600">{page.product || 'Unassigned'}</td>
                    <td className="px-4 py-3"><Badge tone="neutral">{page.version || 'none'}</Badge></td>
                    <td className="px-4 py-3"><Badge tone={statusTone[page.status] || 'neutral'}>{page.status}</Badge></td>
                    <td className="px-4 py-3 text-ink-600">{analytics.visitors.toLocaleString()}</td>
                    <td className="px-4 py-3">
                      <span className="flex items-center gap-1 text-ink-600">
                        <TrendingUp className="h-3 w-3 text-success-600" />
                        {analytics.conversion_rate}%
                      </span>
                    </td>
                    <td className="px-4 py-3 font-semibold text-ink-900">{formatBDT(displayMinor(analytics.revenue_minor))}</td>
                    <td className="px-4 py-3 text-ink-400">{new Date(page.updated_at).toLocaleDateString()}</td>
                    <td className="px-4 py-3">
                      <div className="flex gap-1">
                        {page.status === 'published' && (
                          <a href={`/go/${page.slug}`} target="_blank" rel="noreferrer">
                            <Button size="icon" variant="ghost"><Eye className="h-4 w-4" /></Button>
                          </a>
                        )}
                        <Link to={`/admin/landing-pages/${page.id}`}>
                          <Button size="icon" variant="ghost"><MoreHorizontal className="h-4 w-4" /></Button>
                        </Link>
                      </div>
                    </td>
                  </tr>
                );
              })}
            </tbody>
          </table>
        </div>
      </Card>
    </div>
  );
}
