import { useEffect, useState } from 'react';
import { Link, useSearchParams } from 'react-router-dom';
import { CheckCircle2, Download, BookOpen, Mail, ArrowRight, UserPlus, ShieldCheck, Clock3, XCircle } from 'lucide-react';
import { Button } from '@/components/ui/Button';
import { Badge } from '@/components/ui/Badge';
import { formatBDT } from '@/data/store';
import { apiRequest, minorToDisplay } from '@/services/api/client';
import { verifyPipraPayRedirect } from '@/services/api/checkout';
import { trackMetaPurchase } from '@/services/metaTracking';

type Receipt = {
  order_number: string;
  payment_status: string;
  total_minor: number;
  currency: string;
  items: { product_name: string; total_minor: number }[];
  meta?: {
    purchase_event_id: string;
    content_ids: string[];
    content_type: string;
    num_items: number;
    value: number;
    currency: string;
  };
};

export default function PurchaseSuccess() {
  const [params] = useSearchParams();
  const [receipt, setReceipt] = useState<Receipt | null>(null);
  const [guestDownloads, setGuestDownloads] = useState<{ download_url: string; name: string }[]>([]);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState('');
  const orderNumber = params.get('order') || '';
  const ppId = params.get('transaction_ref') || params.get('pp_id') || params.get('payment_id') || '';
  const guestAccessToken = params.get('guest_access_token') || (orderNumber ? sessionStorage.getItem(`guest_access_token:${orderNumber}`) || '' : '');

  useEffect(() => {
    if (!orderNumber) {
      setLoading(false);
      setError('Order number is missing from the payment return URL.');
      return;
    }
    const suffix = guestAccessToken ? `?guest_access_token=${encodeURIComponent(guestAccessToken)}` : '';
    const settle = ppId ? verifyPipraPayRedirect({ pp_id: ppId, order: orderNumber }).catch(() => undefined) : Promise.resolve();
    settle.then(() => {
      apiRequest<{ data: Receipt }>(`/checkout/orders/${orderNumber}/receipt${suffix}`)
        .then((response) => setReceipt(response.data))
        .catch(() => setError('We could not confirm this order yet. Please check your account orders or contact support.'))
        .finally(() => setLoading(false));
      if (guestAccessToken) {
        apiRequest<{ data: { downloads: { download_url: string; name: string }[] } }>(`/guest/orders/${orderNumber}${suffix}`)
          .then((response) => setGuestDownloads(response.data.downloads))
          .catch(() => undefined);
      }
    });
  }, [orderNumber, guestAccessToken, ppId]);

  useEffect(() => {
    if (receipt?.payment_status !== 'paid' || !receipt.meta?.purchase_event_id) return;
    void trackMetaPurchase({
      eventId: receipt.meta.purchase_event_id,
      contentIds: receipt.meta.content_ids,
      contentType: receipt.meta.content_type,
      value: receipt.meta.value,
      currency: receipt.meta.currency,
      numItems: receipt.meta.num_items,
    });
  }, [receipt]);

  const firstItem = receipt?.items[0];
  const paid = receipt?.payment_status === 'paid';
  const pending = loading || (!receipt && !error);

  return (
    <div className="container-page py-12 lg:py-16">
      <div className="mx-auto max-w-2xl">
        <div className="text-center">
          <div className={`mx-auto mb-5 flex h-16 w-16 items-center justify-center rounded-full animate-scale-in ${paid ? 'bg-success-100 text-success-600' : error ? 'bg-danger-100 text-danger-600' : 'bg-warning-100 text-warning-600'}`}>
            {paid ? <CheckCircle2 className="h-9 w-9" /> : error ? <XCircle className="h-9 w-9" /> : <Clock3 className="h-9 w-9" />}
          </div>
          <h1 className="font-display text-3xl font-bold text-ink-900">{paid ? 'Payment successful' : error ? 'Payment status unavailable' : 'Confirming payment'}</h1>
          <p className="mt-2 text-ink-500">{paid ? 'Your order has been confirmed.' : error || 'Please wait while we confirm your order with the server.'} Order number <span className="font-semibold text-ink-800">{receipt?.order_number || orderNumber || 'Pending'}</span></p>
        </div>

        <div className="card-surface mt-8 overflow-hidden">
          <div className="border-b border-ink-100 p-5">
            <h2 className="text-base font-bold text-ink-900">Your purchase</h2>
          </div>
          <div className="p-5">
            <div className="flex gap-4">
              <div className="flex h-24 w-20 items-center justify-center rounded-lg bg-brand-50 text-brand-600">
                <BookOpen className="h-8 w-8" />
              </div>
              <div className="flex-1">
                <Badge tone={paid ? 'success' : error ? 'danger' : 'warning'} className="mb-1">{receipt?.payment_status || (pending ? 'checking' : 'unconfirmed')}</Badge>
                <p className="text-base font-semibold text-ink-900">{firstItem?.product_name || (pending ? 'Confirming purchased product...' : 'Receipt unavailable')}</p>
                {receipt && <p className="mt-1 text-sm font-bold text-ink-900">{formatBDT(minorToDisplay(receipt.total_minor) || 0)}</p>}
              </div>
            </div>

            <div className="mt-5 flex flex-col gap-3 sm:flex-row">
              {paid && guestDownloads[0] ? (
                <Button className="flex-1" onClick={() => { window.location.href = guestDownloads[0].download_url; }}>
                  <Download className="h-5 w-5" />
                  Download Now
                </Button>
              ) : paid ? (
                <Link to="/account/downloads" className="flex-1">
                  <Button className="w-full"><Download className="h-5 w-5" /> Downloads</Button>
                </Link>
              ) : (
                <Button className="flex-1" disabled><Download className="h-5 w-5" /> Downloads</Button>
              )}
              {paid ? (
                <Link to="/account/library" className="flex-1">
                  <Button variant="outline" className="w-full">
                    <BookOpen className="h-5 w-5" />
                    Go to My Library
                  </Button>
                </Link>
              ) : (
                <Button variant="outline" className="flex-1" disabled>
                  <BookOpen className="h-5 w-5" />
                  Go to My Library
                </Button>
              )}
            </div>
          </div>
        </div>

        {paid && <div className="mt-5 flex items-start gap-3 rounded-xl border border-ink-200/60 bg-white p-4">
          <Mail className="mt-0.5 h-5 w-5 shrink-0 text-brand-600" />
          <p className="text-sm text-ink-600">Download and access information has been sent to your email. Check your inbox and spam folder.</p>
        </div>}

        {guestAccessToken && (
          <div className="mt-5 overflow-hidden rounded-2xl border border-brand-200 bg-gradient-to-br from-brand-50 via-white to-violet-50 p-6">
            <div className="flex items-start gap-4">
              <div className="flex h-12 w-12 shrink-0 items-center justify-center rounded-xl bg-brand-600 text-white">
                <UserPlus className="h-6 w-6" />
              </div>
              <div className="flex-1">
                <h3 className="text-base font-bold text-ink-900">Keep all your purchases in one place</h3>
                <p className="mt-1 text-sm text-ink-600">Create a free Learn by Bluxor account using your checkout email to access your library anytime.</p>
                <Link to="/register" className="mt-4 inline-block">
                  <Button size="sm" rightIcon={<ArrowRight className="h-4 w-4" />}>Create My Account</Button>
                </Link>
              </div>
            </div>
          </div>
        )}

        <div className="mt-5 flex items-center justify-center gap-2 text-sm text-ink-400">
          <ShieldCheck className="h-4 w-4" />
          Account creation is optional.
        </div>
      </div>
    </div>
  );
}
