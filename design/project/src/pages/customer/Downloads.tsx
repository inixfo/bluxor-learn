import { useEffect, useState } from 'react';
import { Download, FileText } from 'lucide-react';
import { CustomerMobileNav } from '@/components/customer/CustomerSidebar';
import { Button } from '@/components/ui/Button';
import { Card, EmptyState } from '@/components/ui/Card';
import { displaySize, getDownloads, requestDownload, type LibraryResource } from '@/services/api/account';
import { useToast } from '@/components/ui/Toast';

export default function CustomerDownloads() {
  const toast = useToast();
  const [files, setFiles] = useState<LibraryResource[]>([]);
  const [loading, setLoading] = useState(true);
  const groups = files.reduce<Record<string, LibraryResource[]>>((acc, file) => {
    acc[file.product_title] = acc[file.product_title] || [];
    acc[file.product_title].push(file);
    return acc;
  }, {});

  useEffect(() => {
    getDownloads().then(setFiles).finally(() => setLoading(false));
  }, []);

  const download = async (file: LibraryResource) => {
    try {
      const signed = await requestDownload(file.file_id);
      window.location.href = signed.download_url || '';
    } catch {
      toast({ type: 'error', title: 'Download unavailable', message: 'You do not have access to this file.' });
    }
  };

  return (
    <div>
      <CustomerMobileNav />
      <div className="mb-6">
        <h1 className="font-display text-2xl font-bold text-ink-900">Downloads</h1>
        <p className="mt-1 text-sm text-ink-500">{loading ? 'Loading downloads...' : 'All files you have permission to download.'}</p>
      </div>

      {files.length === 0 ? (
        <EmptyState icon={<Download className="h-7 w-7" />} title="No downloads available" description="Purchase products to access downloads." />
      ) : (
        <div className="space-y-6">
          {Object.entries(groups).map(([title, productFiles]) => (
            <div key={title}>
              <div className="mb-3 flex items-center gap-3">
                <h2 className="text-sm font-bold text-ink-900">{title}</h2>
              </div>
              <div className="space-y-2">
                {productFiles.map((file) => (
                  <Card key={file.file_id} className="flex items-center gap-3 p-3">
                    <div className="flex h-9 w-9 items-center justify-center rounded-lg bg-ink-100 text-ink-500">
                      <FileText className="h-4 w-4" />
                    </div>
                    <div className="flex-1">
                      <p className="text-sm font-medium text-ink-900">{file.name}</p>
                      <p className="text-xs text-ink-400">{displaySize(file.file_size_bytes)} · {file.file_type} · v{file.version}</p>
                    </div>
                    <Button size="sm" variant="outline" onClick={() => download(file)}>
                      <Download className="h-4 w-4" /> Download
                    </Button>
                  </Card>
                ))}
              </div>
            </div>
          ))}
        </div>
      )}
    </div>
  );
}
