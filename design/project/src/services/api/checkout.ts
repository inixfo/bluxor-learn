import { apiRequest, minorToDisplay } from './client';

export type CheckoutQuote = {
  type: 'product' | 'bundle';
  id: number;
  title: string;
  slug: string;
  subtotalMinor: number;
  discountMinor: number;
  totalMinor: number;
  subtotal: number;
  discount: number;
  total: number;
  currency: string;
  couponCode?: string;
  landingPageId?: number;
  landingPageVersionId?: number;
  offerKey?: string;
};

type CheckoutTarget = {
  product_id?: number;
  bundle_id?: number;
  landing_page_slug?: string;
  landing_page_id?: number;
  offer_key?: string;
};

export type CheckoutOrder = {
  id: number;
  order_number: string;
  total_minor: number;
  currency: string;
  guest_access_token?: string | null;
};

type QuoteResponse = {
  type: 'product' | 'bundle';
  id: number;
  title: string;
  slug: string;
  subtotal_minor: number;
  discount_minor: number;
  total_minor: number;
  currency: string;
  coupon_code?: string;
  landing_page_id?: number;
  landing_page_version_id?: number;
  offer_key?: string;
};

export async function quoteCheckout(payload: CheckoutTarget & { coupon_code?: string }): Promise<CheckoutQuote> {
  const response = await apiRequest<{ data: QuoteResponse }>('/checkout/quote', {
    method: 'POST',
    body: JSON.stringify(payload),
  });

  return toQuote(response.data);
}

export async function createCheckoutOrder(payload: {
  product_id?: number;
  bundle_id?: number;
  landing_page_slug?: string;
  landing_page_id?: number;
  offer_key?: string;
  coupon_code?: string;
  customer_name: string;
  customer_email: string;
  customer_phone?: string;
  payment_method: string;
}): Promise<CheckoutOrder> {
  const response = await apiRequest<{ data: { order: CheckoutOrder; guest_access_token?: string | null } }>('/checkout/orders', {
    method: 'POST',
    body: JSON.stringify(payload),
  });

  return { ...response.data.order, guest_access_token: response.data.guest_access_token || null };
}

export async function initiatePipraPay(orderNumber: string): Promise<{ checkout_url: string; redirect_url: string; provider_payment_id?: string | null }> {
  const response = await apiRequest<{ data: { checkout_url: string; redirect_url: string; provider_payment_id?: string | null } }>('/payments/piprapay/initiate', {
    method: 'POST',
    body: JSON.stringify({ order_number: orderNumber }),
  });

  return response.data;
}

export async function verifyPipraPayRedirect(params: { pp_id: string; order?: string }): Promise<unknown> {
  return (await apiRequest<{ data: unknown }>('/payments/piprapay/success', {
    method: 'POST',
    body: JSON.stringify(params),
  })).data;
}

function toQuote(data: QuoteResponse): CheckoutQuote {
  return {
    type: data.type,
    id: data.id,
    title: data.title,
    slug: data.slug,
    subtotalMinor: data.subtotal_minor,
    discountMinor: data.discount_minor,
    totalMinor: data.total_minor,
    subtotal: minorToDisplay(data.subtotal_minor) || 0,
    discount: minorToDisplay(data.discount_minor) || 0,
    total: minorToDisplay(data.total_minor) || 0,
    currency: data.currency,
    couponCode: data.coupon_code,
    landingPageId: data.landing_page_id,
    landingPageVersionId: data.landing_page_version_id,
    offerKey: data.offer_key,
  };
}
