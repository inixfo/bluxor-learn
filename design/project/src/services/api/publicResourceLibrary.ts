import { apiRequest } from '@/services/api/client';

export interface PublicLibraryResource {
  name?: string | null;
  title?: string | null;
  type?: string | null;
  original_file?: string | null;
  public_file?: string | null;
  bytes?: number | null;
  mime_type?: string | null;
  download_url?: string | null;
}

export interface PublicLibraryProject {
  project: number;
  slug: string;
  title: string;
  page_url: string;
  resource_count: number;
  resource_types: string[];
  resources: PublicLibraryResource[];
}

export interface PublicLibraryProduct {
  exists: boolean;
  slug: string;
  name: string;
  status?: string | null;
  product_url: string;
}

export interface PublicLibraryManifest {
  title: string;
  slug: string;
  generated_at: string;
  public_base_url: string;
  index_url: string;
  authorized: boolean;
  authorization_message: string;
  product: PublicLibraryProduct;
  master_pack: PublicLibraryResource & { title: string };
  projects: PublicLibraryProject[];
}

export async function getN8nAutomationLabLibrary(params?: URLSearchParams): Promise<PublicLibraryManifest> {
  const accessParams = new URLSearchParams();
  ['order_number', 'guest_access_token'].forEach((key) => {
    const value = params?.get(key);
    if (value) accessParams.set(key, value);
  });

  const suffix = accessParams.toString() ? `?${accessParams.toString()}` : '';
  return (await apiRequest<{ data: PublicLibraryManifest }>(`/public-resource-library/n8n-automation-lab${suffix}`)).data;
}
