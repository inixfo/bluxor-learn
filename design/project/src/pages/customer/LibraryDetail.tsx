import { useEffect, useState } from 'react';
import { useParams, Link } from 'react-router-dom';
import { Download, FileText, BookOpen, Check, RefreshCw, Users, ExternalLink } from 'lucide-react';
import { Button } from '@/components/ui/Button';
import { Badge } from '@/components/ui/Badge';
import { Breadcrumb } from '@/components/ui/Card';
import { displaySize, getLibraryDetail, requestDownload, type LibraryItem, type LibraryResource } from '@/services/api/account';
import { useToast } from '@/components/ui/Toast';

export default function CustomerLibraryDetail() {
  const { id } = useParams();
  const toast = useToast();
  const [item, setItem] = useState<LibraryItem | null>(null);
  const [loading, setLoading] = useState(true);

  useEffect(() => {
    if (!id) return;
    getLibraryDetail(id).then(setItem).catch(() => setItem(null)).finally(() => setLoading(false));
  }, [id]);

  const download = async (file: LibraryResource) => {
    try {
      const signed = await requestDownload(file.file_id);
      window.location.href = signed.download_url || '';
    } catch {
      toast({ type: 'error', title: 'Download unavailable', message: 'You do not have access to this file.' });
    }
  };

  if (!item && !loading) {
    return (
      <div className="text-center">
        <h1 className="text-xl font-bold text-ink-900">Item not found</h1>
        <Link to="/account/library" className="mt-4 inline-block text-brand-600">Back to library</Link>
      </div>
    );
  }

  if (!item) return <p className="text-sm text-ink-500">Loading product access...</p>;

  return (
    <div>
      <Breadcrumb items={[{ label: 'Library', to: '/account/library' }, { label: item.title }]} />

      <div className="mt-6 grid gap-8 lg:grid-cols-[300px_1fr]">
        <div className="lg:sticky lg:top-24 lg:self-start">
          <div className="overflow-hidden rounded-2xl border border-ink-200/60 shadow-card">
            {item.cover ? (
              <img src={item.cover} alt={item.title} className="aspect-[3/4] w-full object-cover" />
            ) : (
              <div className="flex aspect-[3/4] w-full items-center justify-center bg-brand-50 text-brand-600">
                <BookOpen className="h-10 w-10" />
              </div>
            )}
          </div>
          <div className="mt-4 space-y-2">
            <Button className="w-full" size="lg"><BookOpen className="h-5 w-5" /> Read Online</Button>
            {item.files[0] && <Button variant="outline" className="w-full" size="lg" onClick={() => download(item.files[0])}><Download className="h-5 w-5" /> Download First File</Button>}
          </div>
        </div>

        <div>
          <Badge tone="success"><Check className="h-3 w-3" /> Owned - Lifetime Access</Badge>
          <h1 className="mt-3 font-display text-2xl font-bold text-ink-900">{item.title}</h1>
          <p className="mt-2 text-sm text-ink-500">Purchased on {item.purchased_at}</p>

          {item.communities.length > 0 && (
            <div className="mt-6 rounded-xl border border-brand-200 bg-brand-50/50 p-4">
              <div className="flex items-start gap-3">
                <Users className="mt-0.5 h-5 w-5 shrink-0 text-brand-600" />
                <div className="flex-1">
                  <h2 className="text-base font-bold text-ink-900">Community Access</h2>
                  <p className="mt-1 text-sm leading-6 text-ink-600">Join other Learn by Bluxor learners, ask questions, share workflows and stay connected.</p>
                  <div className="mt-3 space-y-2">
                    {item.communities.map((community) => (
                      <div key={community.url} className="flex flex-col gap-2 rounded-lg bg-white p-3 sm:flex-row sm:items-center sm:justify-between">
                        <span className="text-sm font-semibold text-ink-900">{community.name}</span>
                        <a href={community.url} target="_blank" rel="noopener noreferrer">
                          <Button size="sm" variant="outline" rightIcon={<ExternalLink className="h-4 w-4" />}>Join Community</Button>
                        </a>
                      </div>
                    ))}
                  </div>
                </div>
              </div>
            </div>
          )}

          <div className="mt-6">
            <h2 className="text-lg font-bold text-ink-900">Files & Resources</h2>
            <div className="mt-3 space-y-2">
              {item.files.map((file) => (
                <div key={file.file_id} className="flex items-center gap-3 rounded-xl border border-ink-200/60 bg-white p-4">
                  <div className="flex h-10 w-10 items-center justify-center rounded-lg bg-brand-100 text-brand-600">
                    <FileText className="h-5 w-5" />
                  </div>
                  <div className="flex-1">
                    <p className="text-sm font-semibold text-ink-900">{file.name}</p>
                    <p className="text-xs text-ink-400">{displaySize(file.file_size_bytes)} · {file.file_type} · v{file.version}</p>
                  </div>
                  <Button size="sm" variant="outline" onClick={() => download(file)}>
                    <Download className="h-4 w-4" /> Download
                  </Button>
                </div>
              ))}
            </div>
          </div>

          <div className="mt-6 flex items-start gap-3 rounded-xl border border-ink-200/60 bg-white p-4">
            <RefreshCw className="mt-0.5 h-5 w-5 text-brand-600" />
            <div>
              <p className="text-sm font-semibold text-ink-900">Lifetime updates included</p>
              <p className="text-xs text-ink-500">When a new version is released, it will appear here automatically.</p>
            </div>
          </div>
        </div>
      </div>
    </div>
  );
}
