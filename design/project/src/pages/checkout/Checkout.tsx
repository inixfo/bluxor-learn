import { useEffect, useMemo, useState } from 'react';
import { Link, useSearchParams } from 'react-router-dom';
import { Lock, CreditCard, Tag, ShieldCheck, LogIn, Check, AlertCircle } from 'lucide-react';
import { Button } from '@/components/ui/Button';
import { Input } from '@/components/ui/Input';
import { Badge } from '@/components/ui/Badge';
import { useToast } from '@/components/ui/Toast';
import { formatBDT } from '@/data/store';
import { ApiError } from '@/services/api/client';
import { createCheckoutOrder, initiatePipraPay, quoteCheckout, type CheckoutQuote } from '@/services/api/checkout';
import { useAuth } from '@/services/auth-context';

export default function Checkout() {
  const [params] = useSearchParams();
  const toast = useToast();
  const { user, initializing } = useAuth();
  const [coupon, setCoupon] = useState('');
  const [appliedCoupon, setAppliedCoupon] = useState('');
  const [loading, setLoading] = useState(false);
  const [quoteLoading, setQuoteLoading] = useState(true);
  const [quote, setQuote] = useState<CheckoutQuote | null>(null);
  const [quoteError, setQuoteError] = useState('');
  const [fieldErrors, setFieldErrors] = useState<Record<string, string[]>>({});
  const [customer, setCustomer] = useState({ name: '', phone: '', email: '' });

  const productId = Number(params.get('product_id') || 0);
  const bundleId = Number(params.get('bundle_id') || 0);
  const landingPageSlug = params.get('landing_page_slug') || params.get('lp') || '';
  const offerKey = params.get('offer_key') || params.get('offer') || '';

  const offerPayload = useMemo(() => {
    if (landingPageSlug) return { landing_page_slug: landingPageSlug, offer_key: offerKey || 'single' };
    if (bundleId) return { bundle_id: bundleId };
    return { product_id: productId };
  }, [bundleId, landingPageSlug, offerKey, productId]);

  useEffect(() => {
    if (initializing || !user) return;
    setCustomer((prev) => ({
      name: prev.name || user.name,
      email: prev.email || user.email,
      phone: prev.phone || user.phone || '',
    }));
  }, [initializing, user]);

  useEffect(() => {
    if (!productId && !bundleId && !landingPageSlug) {
      setQuoteError('Choose a product before checking out.');
      setQuoteLoading(false);
      return;
    }

    setQuoteLoading(true);
    setQuoteError('');
    quoteCheckout(offerPayload)
      .then(setQuote)
      .catch(() => {
        setQuote(null);
        setQuoteError('This checkout item is unavailable.');
      })
      .finally(() => setQuoteLoading(false));
  }, [bundleId, landingPageSlug, offerPayload, productId]);

  const applyCoupon = async () => {
    if (!coupon.trim()) return;
    try {
      const nextQuote = await quoteCheckout({ ...offerPayload, coupon_code: coupon.trim() });
      setQuote(nextQuote);
      setAppliedCoupon(nextQuote.couponCode || coupon.trim().toUpperCase());
      toast({ type: 'success', title: 'Coupon applied', message: `${formatBDT(nextQuote.discount)} discount applied` });
    } catch (error) {
      toast({ type: 'error', title: 'Coupon could not be applied', message: error instanceof ApiError ? error.message : 'Check the code and try again.' });
    }
  };

  const removeCoupon = async () => {
    setCoupon('');
    setAppliedCoupon('');
    setQuote(await quoteCheckout(offerPayload));
  };

  const placeOrder = async () => {
    setFieldErrors({});
    if (!quote) return;
    if (!customer.name || !customer.email) {
      toast({ type: 'error', title: 'Customer information required', message: 'Enter your name and email to continue.' });
      return;
    }

    setLoading(true);
    try {
      const order = await createCheckoutOrder({
        ...offerPayload,
        coupon_code: appliedCoupon || undefined,
        customer_name: customer.name,
        customer_email: customer.email,
        customer_phone: customer.phone || undefined,
        payment_method: 'piprapay',
      });
      if (order.guest_access_token) {
        sessionStorage.setItem(`guest_access_token:${order.order_number}`, order.guest_access_token);
      }
      const paymentIntent = await initiatePipraPay(order.order_number);
      window.location.assign(paymentIntent.checkout_url || paymentIntent.redirect_url);
    } catch (error) {
      if (error instanceof ApiError) setFieldErrors(error.errors || {});
      toast({ type: 'error', title: 'Checkout failed', message: error instanceof ApiError ? error.message : 'Please review your information and try again.' });
    } finally {
      setLoading(false);
    }
  };

  const total = quote?.total || 0;

  return (
    <div className="container-page py-8 lg:py-12">
      <div className="mb-6">
        <h1 className="font-display text-2xl font-bold text-ink-900">Checkout</h1>
        <p className="mt-1 text-sm text-ink-500">Complete your purchase securely.</p>
      </div>

      <div className="grid gap-8 lg:grid-cols-[1fr_400px]">
        <div className="space-y-6">
          {!user && !initializing && (
            <div className="flex items-center justify-between rounded-xl border border-brand-200 bg-brand-50/50 px-4 py-3">
              <p className="text-sm text-ink-700"><span className="font-semibold">Already have an account?</span> Log in for faster checkout.</p>
              <Link to="/login" className="flex items-center gap-1.5 text-sm font-semibold text-brand-600 hover:text-brand-700">
                <LogIn className="h-4 w-4" /> Log in
              </Link>
            </div>
          )}

          <section className="card-surface p-6">
            <h2 className="mb-4 text-base font-bold text-ink-900">Customer information</h2>
            <div className="grid gap-4 sm:grid-cols-2">
              <Input label="Full name" name="name" placeholder="Your name" value={customer.name} onChange={(e) => setCustomer((prev) => ({ ...prev, name: e.target.value }))} hint={fieldErrors.customer_name?.[0]} required />
              <Input label="Phone (optional)" name="phone" placeholder="01XXXXXXXXX" value={customer.phone} onChange={(e) => setCustomer((prev) => ({ ...prev, phone: e.target.value }))} hint={fieldErrors.customer_phone?.[0]} />
              <Input label="Email" name="email" type="email" placeholder="you@example.com" value={customer.email} onChange={(e) => setCustomer((prev) => ({ ...prev, email: e.target.value }))} hint={fieldErrors.customer_email?.[0]} className="sm:col-span-2" required />
            </div>
            <p className="mt-3 text-xs text-ink-400">Your download link will be sent to this email. Registration is optional.</p>
          </section>

          <section className="card-surface p-6">
            <h2 className="mb-4 text-base font-bold text-ink-900">Payment method</h2>
            <div className="flex items-center gap-3 rounded-xl border border-brand-500 bg-brand-50/50 p-4">
              <div className="flex h-10 w-10 items-center justify-center rounded-lg bg-brand-600 text-white">
                <CreditCard className="h-5 w-5" />
              </div>
              <div className="flex-1">
                <p className="text-sm font-semibold text-ink-900">PipraPay hosted checkout</p>
                <p className="text-xs text-ink-400">You will be redirected to complete payment securely.</p>
              </div>
              <div className="flex h-5 w-5 items-center justify-center rounded-full border-2 border-brand-600 bg-brand-600">
                <Check className="h-3 w-3 text-white" />
              </div>
            </div>
          </section>

          <div className="flex items-center gap-2 text-sm text-ink-500">
            <ShieldCheck className="h-4 w-4 text-success-600" />
            Your payment is encrypted and secure. Learn by Bluxor never receives card details.
          </div>
        </div>

        <div className="lg:sticky lg:top-24 lg:self-start">
          <div className="card-surface overflow-hidden">
            <div className="border-b border-ink-100 p-5">
              <h2 className="text-base font-bold text-ink-900">Order summary</h2>
            </div>
            <div className="p-5">
              {quoteLoading ? (
                <p className="text-sm text-ink-500">Calculating price...</p>
              ) : quoteError ? (
                <div className="rounded-xl border border-danger-200 bg-danger-50 p-4 text-sm text-danger-700">
                  <AlertCircle className="mb-2 h-5 w-5" />
                  {quoteError}
                </div>
              ) : quote && (
                <>
                  <div>
                    <Badge tone={quote.type === 'bundle' ? 'violet' : 'brand'}>{quote.type}</Badge>
                    <p className="mt-2 text-base font-semibold text-ink-900">{quote.title}</p>
                    {quote.offerKey && <p className="mt-1 text-xs text-ink-400">Offer: {quote.offerKey}</p>}
                  </div>

                  <div className="mt-5">
                    <label className="mb-1.5 block text-sm font-medium text-ink-700">Coupon code</label>
                    {appliedCoupon ? (
                      <div className="flex items-center justify-between rounded-xl border border-success-200 bg-success-50 px-3 py-2.5">
                        <span className="flex items-center gap-2 text-sm font-semibold text-success-700"><Check className="h-4 w-4" /> {appliedCoupon} applied</span>
                        <button onClick={removeCoupon} className="text-xs text-ink-500 hover:text-danger-600">Remove</button>
                      </div>
                    ) : (
                      <div className="flex gap-2">
                        <Input placeholder="Enter code" value={coupon} onChange={(e) => setCoupon(e.target.value)} leftIcon={<Tag className="h-4 w-4" />} />
                        <Button variant="outline" onClick={applyCoupon} className="shrink-0">Apply</Button>
                      </div>
                    )}
                  </div>

                  <div className="mt-5 space-y-2 border-t border-ink-100 pt-4 text-sm">
                    <div className="flex justify-between"><span className="text-ink-500">Subtotal</span><span className="font-medium text-ink-900">{formatBDT(quote.subtotal)}</span></div>
                    <div className="flex justify-between"><span className="text-ink-500">Discount</span><span className="font-medium text-success-600">-{formatBDT(quote.discount)}</span></div>
                    <div className="flex justify-between"><span className="text-ink-500">Processing fee</span><span className="font-medium text-ink-900">{formatBDT(0)}</span></div>
                  </div>
                  <div className="mt-3 flex items-baseline justify-between border-t border-ink-100 pt-3">
                    <span className="text-base font-bold text-ink-900">Total</span>
                    <span className="text-2xl font-bold text-ink-900">{formatBDT(total)}</span>
                  </div>
                </>
              )}

              <Button onClick={placeOrder} loading={loading} disabled={!quote || quoteLoading || !!quoteError} size="lg" className="mt-5 w-full">
                <Lock className="h-4 w-4" />
                Place Order - {formatBDT(total)}
              </Button>

              <p className="mt-3 text-center text-xs text-ink-400">
                By placing your order, you agree to our Terms and Privacy Policy.
              </p>
            </div>
          </div>
        </div>
      </div>
    </div>
  );
}
