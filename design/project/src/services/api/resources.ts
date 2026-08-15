import { API_BASE_URL, apiRequest } from './client';

export type PublicResource = {
  title: string;
  slug: string;
  description?: string | null;
  resource_type: string;
  source_type: 'uploaded_file' | 'external_url';
  version: string;
  access_type: 'public' | 'purchase_required';
  status: 'published' | 'archived';
  download_count: number;
  original_filename?: string | null;
  file_size?: number | null;
  mime_type?: string | null;
  updated_at: string;
  canonical_url: string;
  download_url?: string | null;
  authorized: boolean;
  authorization_message: string;
  products: { id: number; name: string; slug: string; status: string }[];
};

export async function getPublicResource(slug: string, params: URLSearchParams = new URLSearchParams()): Promise<PublicResource> {
  const suffix = params.toString() ? `?${params.toString()}` : '';
  return (await apiRequest<{ data: PublicResource }>(`/resources/${slug}${suffix}`)).data;
}

export function publicResourceDownloadUrl(resource: PublicResource): string | null {
  if (!resource.download_url) return null;
  return resource.download_url.startsWith('/api/')
    ? resource.download_url
    : `${API_BASE_URL}${resource.download_url}`;
}
