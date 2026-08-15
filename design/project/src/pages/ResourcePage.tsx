import { useEffect, useMemo, useState } from 'react';
import { Link, useParams, useSearchParams } from 'react-router-dom';
import { AlertCircle, ArrowRight, Download, ExternalLink, FileArchive, Lock, RefreshCw } from 'lucide-react';
import { Badge } from '@/components/ui/Badge';
import { Button } from '@/components/ui/Button';
import { Card, Skeleton } from '@/components/ui/Card';
import { getPublicResource, publicResourceDownloadUrl, type PublicResource } from '@/services/api/resources';

export default function ResourcePage() {
  const { slug = '' } = useParams();
  const [searchParams] = useSearchParams();
  const [resource, setResource] = useState<PublicResource | null>(null);
  const [loading, setLoading] = useState(true);
  const [missing, setMissing] = useState(false);

  useEffect(() => {
    setLoading(true);
    setMissing(false);
    getPublicResource(slug, searchParams)
      .then(setResource)
      .catch(() => setMissing(true))
      .finally(() => setLoading(false));
  }, [slug, searchParams]);

  const downloadUrl = useMemo(() => (resource ? publicResourceDownloadUrl(resource) : null), [resource]);

  if (loading) {
    return (
      <main className="mx-auto max-w-5xl px-4 py-12 sm:px-6 lg:px-8">
        <Skeleton className="h-8 w-56" />
        <Skeleton className="mt-6 h-72 w-full" />
      </main>
    );
  }

  if (missing || !resource) {
    return (
      <main className="mx-auto max-w-3xl px-4 py-16 text-center sm:px-6 lg:px-8">
        <div className="mx-auto flex h-14 w-14 items-center justify-center rounded-2xl bg-danger-50 text-danger-600">
          <AlertCircle className="h-7 w-7" />
        </div>
        <h1 className="mt-5 font-display text-3xl font-bold text-ink-950">Resource not found</h1>
        <p className="mt-3 text-ink-500">The resource link may be unpublished or no longer available.</p>
        <Button className="mt-6" variant="outline" onClick={() => window.history.back()}>Go back</Button>
      </main>
    );
  }

  const archived = resource.status === 'archived';
  const protectedResource = resource.access_type === 'purchase_required';

  return (
    <main className="bg-ink-50">
      <section className="mx-auto grid max-w-6xl gap-8 px-4 py-12 sm:px-6 lg:grid-cols-[1fr_360px] lg:px-8 lg:py-16">
        <div>
          <div className="mb-4 flex flex-wrap items-center gap-2">
            <Badge tone={archived ? 'warning' : 'brand'}>{archived ? 'Archived' : resource.resource_type}</Badge>
            <Badge tone={protectedResource ? 'warning' : 'success'}>{protectedResource ? 'Purchase Required' : 'Public Resource'}</Badge>
          </div>
          <h1 className="font-display text-4xl font-bold tracking-normal text-ink-950 sm:text-5xl">{resource.title}</h1>
          <p className="mt-4 max-w-3xl text-lg leading-8 text-ink-600">
            {resource.description || 'Supplementary material from Learn by Bluxor.'}
          </p>

          {resource.products.length > 0 && (
            <div className="mt-6 flex flex-wrap gap-2">
              {resource.products.map((product) => (
                <Link key={product.id} to={`/p/${product.slug}`} className="rounded-full border border-ink-200 bg-white px-3 py-1.5 text-sm font-semibold text-ink-700 transition-colors hover:border-brand-300 hover:text-brand-700">
                  {product.name}
                </Link>
              ))}
            </div>
          )}

          <Card className="mt-8 p-6">
            <div className="flex items-start gap-4">
              <div className="flex h-12 w-12 shrink-0 items-center justify-center rounded-xl bg-brand-50 text-brand-600">
                {resource.source_type === 'external_url' ? <ExternalLink className="h-6 w-6" /> : <FileArchive className="h-6 w-6" />}
              </div>
              <div className="min-w-0">
                <h2 className="text-base font-bold text-ink-950">Resource Details</h2>
                <dl className="mt-4 grid gap-4 text-sm sm:grid-cols-2">
                  <Meta label="Version" value={resource.version} />
                  <Meta label="File" value={resource.original_filename || (resource.source_type === 'external_url' ? 'External link' : 'Download')} />
                  <Meta label="Size" value={formatBytes(resource.file_size)} />
                  <Meta label="Updated" value={new Date(resource.updated_at).toLocaleDateString()} />
                </dl>
              </div>
            </div>
          </Card>
        </div>

        <aside>
          <Card className="sticky top-24 p-6">
            <div className="flex h-12 w-12 items-center justify-center rounded-xl bg-ink-950 text-white">
              {protectedResource ? <Lock className="h-6 w-6" /> : <Download className="h-6 w-6" />}
            </div>
            <h2 className="mt-4 text-lg font-bold text-ink-950">{archived ? 'Unavailable' : resource.source_type === 'external_url' ? 'Open Resource' : 'Download Resource'}</h2>
            <p className="mt-2 text-sm leading-6 text-ink-500">{resource.authorization_message}</p>
            {downloadUrl ? (
              <Button
                className="mt-5 w-full"
                rightIcon={resource.source_type === 'external_url' ? <ExternalLink className="h-4 w-4" /> : <ArrowRight className="h-4 w-4" />}
                onClick={() => window.location.assign(downloadUrl)}
              >
                {resource.source_type === 'external_url' ? 'Open Resource' : 'Download Resource'}
              </Button>
            ) : (
              <Button className="mt-5 w-full" variant="outline" disabled leftIcon={archived ? <RefreshCw className="h-4 w-4" /> : <Lock className="h-4 w-4" />}>
                {archived ? 'Archived' : 'Locked'}
              </Button>
            )}
            {protectedResource && !resource.authorized && !archived && (
              <Link to="/login" className="mt-3 block text-center text-sm font-semibold text-brand-700 hover:text-brand-800">
                Sign in to verify access
              </Link>
            )}
            <div className="mt-5 border-t border-ink-100 pt-4 text-xs text-ink-400">
              {resource.download_count.toLocaleString()} downloads tracked
            </div>
          </Card>
        </aside>
      </section>
    </main>
  );
}

function Meta({ label, value }: { label: string; value?: string | null }) {
  return (
    <div>
      <dt className="text-xs font-semibold uppercase text-ink-400">{label}</dt>
      <dd className="mt-1 truncate font-medium text-ink-800">{value || 'Not specified'}</dd>
    </div>
  );
}

function formatBytes(bytes?: number | null): string {
  if (!bytes) return 'Not specified';
  if (bytes >= 1024 * 1024) return `${Math.round(bytes / 1024 / 1024)} MB`;
  if (bytes >= 1024) return `${Math.round(bytes / 1024)} KB`;
  return `${bytes} B`;
}
