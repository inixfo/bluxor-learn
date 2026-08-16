import { useEffect, useMemo, useState } from 'react';
import { Link, useParams, useSearchParams } from 'react-router-dom';
import { ArrowLeft, BookOpen, Download, ExternalLink, FileArchive, FolderOpen, Lock, Search } from 'lucide-react';
import { Badge } from '@/components/ui/Badge';
import { Button } from '@/components/ui/Button';
import { Card, Skeleton } from '@/components/ui/Card';
import {
  getN8nAutomationLabLibrary,
  type PublicLibraryManifest,
  type PublicLibraryProject,
  type PublicLibraryResource,
} from '@/services/api/publicResourceLibrary';

export default function N8nAutomationLabResources() {
  const { projectSlug } = useParams();
  const [searchParams] = useSearchParams();
  const [manifest, setManifest] = useState<PublicLibraryManifest | null>(null);
  const [loading, setLoading] = useState(true);
  const [failed, setFailed] = useState(false);
  const [query, setQuery] = useState('');

  const accessQuery = useMemo(() => {
    const params = new URLSearchParams();
    ['order_number', 'guest_access_token'].forEach((key) => {
      const value = searchParams.get(key);
      if (value) params.set(key, value);
    });

    return params.toString() ? `?${params.toString()}` : '';
  }, [searchParams]);

  useEffect(() => {
    setLoading(true);
    setFailed(false);
    getN8nAutomationLabLibrary(searchParams)
      .then(setManifest)
      .catch(() => setFailed(true))
      .finally(() => setLoading(false));
  }, [searchParams]);

  const project = useMemo(() => {
    if (!manifest || !projectSlug) return null;
    return manifest.projects.find((item) => item.slug === projectSlug) || null;
  }, [manifest, projectSlug]);

  const filteredProjects = useMemo(() => {
    if (!manifest) return [];
    const needle = query.trim().toLowerCase();
    if (!needle) return manifest.projects;
    return manifest.projects.filter((item) => {
      return (
        item.title.toLowerCase().includes(needle) ||
        item.slug.includes(needle) ||
        item.resource_types.some((type) => type.toLowerCase().includes(needle)) ||
        item.resources.some((resource) =>
          `${resource.name || ''} ${resource.original_file || ''} ${resource.type || ''}`.toLowerCase().includes(needle)
        )
      );
    });
  }, [manifest, query]);

  if (loading) {
    return (
      <main className="mx-auto max-w-7xl px-4 py-10 sm:px-6 lg:px-8">
        <Skeleton className="h-10 w-72" />
        <Skeleton className="mt-6 h-96 w-full" />
      </main>
    );
  }

  if (failed || !manifest) {
    return (
      <main className="mx-auto max-w-3xl px-4 py-16 text-center sm:px-6 lg:px-8">
        <div className="mx-auto flex h-14 w-14 items-center justify-center rounded-xl bg-danger-50 text-danger-600">
          <FileArchive className="h-7 w-7" />
        </div>
        <h1 className="mt-5 font-display text-3xl font-bold text-ink-950">Resource library unavailable</h1>
        <p className="mt-3 text-ink-500">The public resource manifest could not be loaded.</p>
      </main>
    );
  }

  if (projectSlug && !project) {
    return (
      <main className="mx-auto max-w-3xl px-4 py-16 text-center sm:px-6 lg:px-8">
        <div className="mx-auto flex h-14 w-14 items-center justify-center rounded-xl bg-warning-50 text-warning-700">
          <FolderOpen className="h-7 w-7" />
        </div>
        <h1 className="mt-5 font-display text-3xl font-bold text-ink-950">Project resources not found</h1>
        <p className="mt-3 text-ink-500">Choose a project from the public resource index.</p>
        <Link to="/resources/n8n-automation-lab/">
          <Button className="mt-6" variant="outline" leftIcon={<ArrowLeft className="h-4 w-4" />}>Back to index</Button>
        </Link>
      </main>
    );
  }

  return project ? <ProjectResources accessQuery={accessQuery} manifest={manifest} project={project} /> : (
    <main className="bg-ink-50">
      <section className="border-b border-ink-100 bg-white">
        <div className="mx-auto max-w-7xl px-4 py-10 sm:px-6 lg:px-8">
          <div className="flex flex-col gap-6 lg:flex-row lg:items-end lg:justify-between">
            <div>
              <Badge tone="brand">Public Resource Library</Badge>
              <h1 className="mt-4 font-display text-4xl font-bold text-ink-950 sm:text-5xl">{manifest.title}</h1>
              <p className="mt-3 max-w-3xl text-base leading-7 text-ink-600">
                Browse the project resource index. Downloads unlock automatically for customers who purchased the book.
              </p>
            </div>
            <AccessActions manifest={manifest} />
          </div>
        </div>
      </section>

      <section className="mx-auto max-w-7xl px-4 py-8 sm:px-6 lg:px-8">
        <Card className="p-4">
          <label className="flex items-center gap-3 rounded-lg border border-ink-200 bg-white px-3 py-2 text-sm text-ink-500">
            <Search className="h-4 w-4 shrink-0" />
            <input
              value={query}
              onChange={(event) => setQuery(event.target.value)}
              className="h-8 min-w-0 flex-1 bg-transparent font-medium text-ink-800 outline-none placeholder:text-ink-400"
              placeholder="Search projects or files"
            />
          </label>
        </Card>

        <div className="mt-6 grid gap-4 md:grid-cols-2 xl:grid-cols-3">
          {filteredProjects.map((item) => (
            <ProjectCard accessQuery={accessQuery} key={item.slug} project={item} />
          ))}
        </div>
      </section>
    </main>
  );
}

function ProjectResources({ accessQuery, manifest, project }: { accessQuery: string; manifest: PublicLibraryManifest; project: PublicLibraryProject }) {
  return (
    <main className="bg-ink-50">
      <section className="border-b border-ink-100 bg-white">
        <div className="mx-auto max-w-7xl px-4 py-8 sm:px-6 lg:px-8">
          <Link to={`/resources/n8n-automation-lab/${accessQuery}`} className="inline-flex items-center gap-2 text-sm font-semibold text-brand-700 hover:text-brand-800">
            <ArrowLeft className="h-4 w-4" />
            Resource index
          </Link>
          <div className="mt-5 flex flex-col gap-5 lg:flex-row lg:items-end lg:justify-between">
            <div>
              <Badge tone="brand">Project {project.project.toString().padStart(2, '0')}</Badge>
              <h1 className="mt-4 font-display text-4xl font-bold text-ink-950">{project.title}</h1>
              <p className="mt-3 text-base leading-7 text-ink-600">
                {manifest.authorized
                  ? `${project.resource_count} downloadable files for this project.`
                  : `${project.resource_count} files are included with this project.`}
              </p>
            </div>
            <AccessActions manifest={manifest} compact />
          </div>
        </div>
      </section>

      <section className="mx-auto max-w-7xl px-4 py-8 sm:px-6 lg:px-8">
        {manifest.authorized ? (
          <Card className="overflow-hidden">
            <div className="divide-y divide-ink-100">
              {project.resources.map((resource, index) => (
                <ResourceRow key={`${resource.public_file || resource.name || 'resource'}-${index}`} resource={resource} />
              ))}
            </div>
          </Card>
        ) : (
          <LockedDownloads manifest={manifest} />
        )}
      </section>
    </main>
  );
}

function ProjectCard({ accessQuery, project }: { accessQuery: string; project: PublicLibraryProject }) {
  const fileTypes = project.resource_types.slice(0, 4).map((type) => type.replace(/-/g, ' ').toUpperCase());

  return (
    <Card className="p-5 transition-shadow hover:shadow-md">
      <div className="flex items-start justify-between gap-4">
        <div className="min-w-0">
          <div className="flex items-center gap-2 text-sm font-semibold text-brand-700">
            <BookOpen className="h-4 w-4" />
            Project {project.project.toString().padStart(2, '0')}
          </div>
          <h2 className="mt-3 line-clamp-2 text-lg font-bold text-ink-950">{project.title}</h2>
        </div>
        <Badge tone="success">{project.resource_count} files</Badge>
      </div>
      <div className="mt-4 flex flex-wrap gap-2">
        {fileTypes.map((type) => <Badge key={type} tone="neutral">{type}</Badge>)}
      </div>
      <Link to={`/resources/n8n-automation-lab/${project.slug}/${accessQuery}`}>
        <Button className="mt-5 w-full" variant="outline" rightIcon={<ExternalLink className="h-4 w-4" />}>
          Open Project Resources
        </Button>
      </Link>
    </Card>
  );
}

function ResourceRow({ resource }: { resource: PublicLibraryResource }) {
  const extension = resource.public_file?.split('.').pop()?.toUpperCase() || 'FILE';

  return (
    <div className="flex flex-col gap-4 p-4 sm:flex-row sm:items-center sm:justify-between">
      <div className="min-w-0">
        <div className="flex flex-wrap items-center gap-2">
          <Badge tone="neutral">{extension}</Badge>
          {typeof resource.bytes === 'number' && <span className="text-xs font-medium text-ink-400">{formatBytes(resource.bytes)}</span>}
        </div>
        <h2 className="mt-2 break-words text-base font-bold text-ink-950">{resource.original_file || resource.title || resource.name}</h2>
        <p className="mt-1 text-sm text-ink-500">{resource.type}</p>
      </div>
      <Button
        className="w-full sm:w-auto"
        variant="outline"
        leftIcon={<Download className="h-4 w-4" />}
        disabled={!resource.download_url}
        onClick={() => resource.download_url && window.location.assign(resource.download_url)}
      >
        Download
      </Button>
    </div>
  );
}

function AccessActions({ manifest, compact = false }: { manifest: PublicLibraryManifest; compact?: boolean }) {
  if (manifest.authorized && manifest.master_pack.download_url) {
    return (
      <Button
        size={compact ? 'md' : 'lg'}
        variant={compact ? 'outline' : 'primary'}
        leftIcon={<Download className="h-5 w-5" />}
        onClick={() => window.location.assign(manifest.master_pack.download_url || '')}
      >
        {compact ? 'Complete Pack' : 'Download Complete Pack'}
      </Button>
    );
  }

  return (
    <div className="flex flex-col gap-3 sm:flex-row">
      <Link to={manifest.product.product_url}>
        <Button size={compact ? 'md' : 'lg'} leftIcon={<BookOpen className="h-5 w-5" />}>
          Get the Book
        </Button>
      </Link>
      <Link to="/login">
        <Button size={compact ? 'md' : 'lg'} variant="outline" leftIcon={<Lock className="h-5 w-5" />}>
          Sign In to Access Resources
        </Button>
      </Link>
    </div>
  );
}

function LockedDownloads({ manifest }: { manifest: PublicLibraryManifest }) {
  return (
    <Card className="p-6 text-center">
      <div className="mx-auto flex h-12 w-12 items-center justify-center rounded-xl bg-warning-50 text-warning-700">
        <Lock className="h-6 w-6" />
      </div>
      <h2 className="mt-4 font-display text-2xl font-bold text-ink-950">Downloads are locked</h2>
      <p className="mx-auto mt-2 max-w-xl text-sm leading-6 text-ink-600">{manifest.authorization_message}</p>
      <div className="mt-5 flex justify-center">
        <AccessActions manifest={manifest} />
      </div>
    </Card>
  );
}

function formatBytes(bytes: number): string {
  if (bytes >= 1024 * 1024) return `${(bytes / 1024 / 1024).toFixed(1)} MB`;
  if (bytes >= 1024) return `${Math.round(bytes / 1024)} KB`;
  return `${bytes} B`;
}
