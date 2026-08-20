import { useEffect, useState } from 'react';
import { ExternalLink, FileText } from 'lucide-react';
import { CustomerMobileNav } from '@/components/customer/CustomerSidebar';
import { Button } from '@/components/ui/Button';
import { Card, EmptyState } from '@/components/ui/Card';
import { getAccountResources, type AccountResourceGrant } from '@/services/api/account';

export default function CustomerResources() {
  const [resources, setResources] = useState<AccountResourceGrant[]>([]);
  const [loading, setLoading] = useState(true);

  useEffect(() => {
    getAccountResources().then(setResources).finally(() => setLoading(false));
  }, []);

  return (
    <div>
      <CustomerMobileNav />
      <div className="mb-6">
        <h1 className="font-display text-2xl font-bold text-ink-900">My Resources</h1>
        <p className="mt-1 text-sm text-ink-500">{loading ? 'Loading resources...' : 'Individual resources granted to your account.'}</p>
      </div>

      {resources.length === 0 ? (
        <EmptyState icon={<FileText className="h-7 w-7" />} title="No individual resources" description="Resources granted directly to your account will appear here." />
      ) : (
        <div className="grid gap-4 sm:grid-cols-2">
          {resources.map((resource) => (
            <Card key={resource.grant_id} className="p-5">
              <div className="flex items-start gap-3">
                <div className="flex h-10 w-10 shrink-0 items-center justify-center rounded-xl bg-brand-50 text-brand-600">
                  <FileText className="h-5 w-5" />
                </div>
                <div className="min-w-0 flex-1">
                  <p className="text-sm font-bold text-ink-900">{resource.title}</p>
                  <p className="mt-1 text-xs text-ink-400">{resource.resource_type} · v{resource.version}</p>
                  {resource.description && <p className="mt-2 line-clamp-2 text-sm text-ink-500">{resource.description}</p>}
                  <p className="mt-3 text-xs text-ink-400">
                    Granted {resource.granted_at || '-'} {resource.expires_at ? `· Expires ${resource.expires_at}` : '· No expiration'}
                  </p>
                  <a href={resource.open_url} className="mt-4 inline-block">
                    <Button size="sm" rightIcon={<ExternalLink className="h-4 w-4" />}>Open / Download</Button>
                  </a>
                </div>
              </div>
            </Card>
          ))}
        </div>
      )}
    </div>
  );
}
