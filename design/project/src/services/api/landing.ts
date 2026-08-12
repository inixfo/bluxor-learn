import { apiFormRequest, apiRequest, minorToDisplay } from './client';

type Paginated<T> = { data: T[] };

export type LandingVersion = {
  id: number;
  version_number: number;
  status: string;
  sdk_version: string;
  package_size_bytes: number;
  validation_report?: { checks?: { label: string; status: string }[]; errors?: string[]; warnings?: string[] };
  created_at: string;
  published_at?: string | null;
  landing_page_id: number;
};

export type LandingPageAdmin = {
  id: number;
  uuid: string;
  name: string;
  slug: string;
  status: string;
  product?: string | null;
  primary_product_id?: number | null;
  published_version_id?: number | null;
  version?: string | null;
  updated_at: string;
  versions?: LandingVersion[];
  offers?: { offer_key: string; offer_type: string; product_id?: number; bundle_id?: number; label?: string; is_primary: boolean }[];
};

export type LandingOfferPayload = {
  offer_key: string;
  offer_type: 'product' | 'bundle';
  product_id?: number | null;
  bundle_id?: number | null;
  is_primary?: boolean;
};

export type OfferItem = {
  id: number;
  name: string;
  slug: string;
  type: 'product' | 'bundle';
  price_minor: number;
  currency: string;
};

export type LandingAnalytics = {
  visitors: number;
  cta_clicks: number;
  checkout_started: number;
  purchases: number;
  conversion_rate: number;
  revenue_minor: number;
  aov_minor: number;
};

export async function getLandingPages(): Promise<LandingPageAdmin[]> {
  const response = await apiRequest<{ data: Paginated<LandingPageAdmin> }>('/admin/landing-pages');
  return response.data.data;
}

export async function getLandingPage(id: string): Promise<LandingPageAdmin> {
  return (await apiRequest<{ data: LandingPageAdmin }>(`/admin/landing-pages/${id}`)).data;
}

export async function uploadLandingPage(formData: FormData): Promise<LandingVersion> {
  return (await apiFormRequest<{ data: LandingVersion }>('/admin/landing-pages/uploads', formData)).data;
}

export async function publishLandingVersion(pageId: number, versionId: number): Promise<LandingPageAdmin> {
  return (await apiRequest<{ data: LandingPageAdmin }>(`/admin/landing-pages/${pageId}/versions/${versionId}/publish`, {
    method: 'POST',
    body: JSON.stringify({}),
  })).data;
}

export async function getPreviewUrl(versionId: number): Promise<string> {
  return (await apiRequest<{ data: { url: string } }>(`/admin/landing-page-versions/${versionId}/preview-url`)).data.url;
}

export async function getLandingAnalytics(pageId: number): Promise<LandingAnalytics> {
  return (await apiRequest<{ data: LandingAnalytics }>(`/admin/landing-pages/${pageId}/analytics`)).data;
}

export async function assignLandingOffers(pageId: number, payload: { primary_product_id?: number | null; offers: LandingOfferPayload[] }): Promise<LandingPageAdmin> {
  return (await apiRequest<{ data: LandingPageAdmin }>(`/admin/landing-pages/${pageId}/offers`, {
    method: 'PATCH',
    body: JSON.stringify(payload),
  })).data;
}

export async function searchOfferItems(type: 'product' | 'bundle', q = ''): Promise<OfferItem[]> {
  const params = new URLSearchParams({ type });
  if (q) params.set('q', q);
  return (await apiRequest<{ data: OfferItem[] }>(`/admin/offer-items?${params.toString()}`)).data;
}

export function displayMinor(value?: number | null): number {
  return minorToDisplay(value) || 0;
}

export function displaySize(bytes: number): string {
  if (bytes >= 1024 * 1024) return `${Math.round(bytes / 1024 / 1024)} MB`;
  if (bytes >= 1024) return `${Math.round(bytes / 1024)} KB`;
  return `${bytes} B`;
}
