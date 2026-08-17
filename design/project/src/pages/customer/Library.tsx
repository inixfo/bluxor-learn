import { useEffect, useState } from 'react';
import { Link } from 'react-router-dom';
import { Download, BookOpen, Users } from 'lucide-react';
import { CustomerMobileNav } from '@/components/customer/CustomerSidebar';
import { Card, EmptyState } from '@/components/ui/Card';
import { Button } from '@/components/ui/Button';
import { Badge } from '@/components/ui/Badge';
import { getLibrary, type LibraryItem } from '@/services/api/account';

export default function CustomerLibrary() {
  const [items, setItems] = useState<LibraryItem[]>([]);
  const [loading, setLoading] = useState(true);

  useEffect(() => {
    getLibrary().then(setItems).finally(() => setLoading(false));
  }, []);

  return (
    <div>
      <CustomerMobileNav />
      <div className="mb-6">
        <h1 className="font-display text-2xl font-bold text-ink-900">My Library</h1>
        <p className="mt-1 text-sm text-ink-500">{loading ? 'Loading your products...' : 'All your purchased products in one place.'}</p>
      </div>

      {items.length === 0 ? (
        <EmptyState icon={<BookOpen className="h-7 w-7" />} title="Your library is empty" description="Purchase products to access them here anytime." action={<Link to="/products"><Button>Browse Products</Button></Link>} />
      ) : (
        <div className="grid grid-cols-1 gap-5 sm:grid-cols-2 lg:grid-cols-3">
          {items.map((item) => (
            <Card key={item.entitlement_id} className="overflow-hidden">
              <div className="relative aspect-[16/10] overflow-hidden">
                {item.cover ? (
                  <img src={item.cover} alt={item.title} className="h-full w-full object-cover" />
                ) : (
                  <div className="flex h-full w-full items-center justify-center bg-brand-50 text-brand-600">
                    <BookOpen className="h-8 w-8" />
                  </div>
                )}
                <div className="absolute right-3 top-3">
                  <Badge tone="success"><Download className="h-3 w-3" /> Owned</Badge>
                </div>
              </div>
              <div className="p-4">
                <h3 className="text-base font-bold text-ink-900">{item.title}</h3>
                <p className="mt-1 text-xs text-ink-400">Purchased on {item.purchased_at} · {item.resource_count} files</p>
                {item.communities.length > 0 && (
                  <div className="mt-3 rounded-lg border border-brand-100 bg-brand-50 px-3 py-2 text-xs font-semibold text-brand-700">
                    <Users className="mr-1 inline h-3.5 w-3.5" />
                    Community Access: {item.communities[0].name}
                  </div>
                )}
                <div className="mt-3 flex gap-2">
                  <Link to={`/account/library/${item.product_id}`} className="flex-1">
                    <Button size="sm" className="w-full"><BookOpen className="h-4 w-4" /> Read</Button>
                  </Link>
                  <Link to={`/account/library/${item.product_id}`}>
                    <Button size="sm" variant="outline"><Download className="h-4 w-4" /></Button>
                  </Link>
                </div>
              </div>
            </Card>
          ))}
        </div>
      )}
      <div className="mt-10 rounded-xl border border-ink-200/60 bg-white p-4 text-sm text-ink-500">
        Your library only shows products granted by backend entitlements.
      </div>
    </div>
  );
}
