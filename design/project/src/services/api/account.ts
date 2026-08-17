import { apiRequest, minorToDisplay } from './client';

export type LibraryResource = {
  file_id: number;
  product_id: number;
  entitlement_id: number;
  product_title: string;
  name: string;
  file_type: string;
  file_size_bytes: number;
  version: string;
  status: string;
  download_url?: string;
  expires_at?: string;
};

export type ProductCommunity = {
  name: string;
  url: string;
  product_id?: number;
  product_name?: string;
};

export type LibraryItem = {
  entitlement_id: number;
  product_id: number;
  product_uuid: string;
  slug: string;
  title: string;
  cover: string;
  description: string;
  purchased_at: string;
  resource_count: number;
  communities: ProductCommunity[];
  files: LibraryResource[];
};

export type AccountOrder = {
  id: number;
  order_number: string;
  date: string;
  total_minor: number;
  currency: string;
  order_status: string;
  payment_status: string;
  communities?: ProductCommunity[];
  items: { name: string; total_minor: number; currency: string }[];
};

export type AccountOverview = {
  customer: { name: string; email: string; phone?: string | null; email_verified: boolean };
  purchased_product_count: number;
  recent_orders: AccountOrder[];
  recent_library_items: LibraryItem[];
};

export type AccountProfile = {
  name: string;
  email: string;
  phone?: string | null;
  email_verified?: boolean;
  email_verified_at?: string | null;
};

export async function getOverview(): Promise<AccountOverview> {
  return (await apiRequest<{ data: AccountOverview }>('/account/overview')).data;
}

export async function getLibrary(): Promise<LibraryItem[]> {
  return (await apiRequest<{ data: LibraryItem[] }>('/account/library')).data;
}

export async function getLibraryDetail(productId: string): Promise<LibraryItem> {
  return (await apiRequest<{ data: LibraryItem }>(`/account/library/${productId}`)).data;
}

export async function getOrders(): Promise<AccountOrder[]> {
  return (await apiRequest<{ data: AccountOrder[] }>('/account/orders')).data;
}

export async function getOrderDetail(orderNumber: string): Promise<AccountOrder> {
  return (await apiRequest<{ data: AccountOrder }>(`/account/orders/${encodeURIComponent(orderNumber)}`)).data;
}

export async function getDownloads(): Promise<LibraryResource[]> {
  return (await apiRequest<{ data: LibraryResource[] }>('/account/downloads')).data;
}

export async function requestDownload(fileId: number): Promise<LibraryResource> {
  const response = await apiRequest<{ data: { file: LibraryResource; download_url: string; expires_at: string } }>(`/account/downloads/${fileId}`, {
    method: 'POST',
    body: JSON.stringify({}),
  });
  return { ...response.data.file, download_url: response.data.download_url, expires_at: response.data.expires_at };
}

export async function getProfile() {
  return (await apiRequest<{ data: AccountProfile }>('/account/profile')).data;
}

export async function updateProfile(payload: { name: string; phone?: string }) {
  return (await apiRequest<{ data: AccountProfile }>('/account/profile', {
    method: 'PATCH',
    body: JSON.stringify(payload),
  })).data;
}

export async function updatePassword(payload: { current_password: string; password: string; password_confirmation: string }) {
  return apiRequest('/account/password', {
    method: 'PUT',
    body: JSON.stringify(payload),
  });
}

export function displayMoney(minor: number): number {
  return minorToDisplay(minor) || 0;
}

export function displaySize(bytes: number): string {
  if (bytes >= 1024 * 1024) return `${Math.round(bytes / 1024 / 1024)} MB`;
  if (bytes >= 1024) return `${Math.round(bytes / 1024)} KB`;
  return `${bytes} B`;
}
