import { apiFormRequest, apiRequest, minorToDisplay } from './client';

type Paginated<T> = {
  data: T[];
  current_page?: number;
  last_page?: number;
  total?: number;
};

export type AdminProduct = {
  id: number;
  name: string;
  slug: string;
  product_type: string;
  status: 'draft' | 'published' | 'archived';
  regular_price_minor: number;
  sale_price_minor?: number | null;
  currency: string;
  cover_image_path?: string | null;
  short_description?: string | null;
  description?: string | null;
  updated_at: string;
  category?: { name: string } | null;
  files?: AdminProductFile[];
};

export type AdminProductFile = {
  id: number;
  name: string;
  file_type: string;
  file_size_bytes: number;
  version: string;
  status: string;
};

export type AdminProductPayload = {
  name: string;
  slug: string;
  product_type: string;
  regular_price_minor: number;
  sale_price_minor?: number | null;
  currency: string;
  status: 'draft' | 'published' | 'archived';
  short_description?: string;
  description?: string;
  cover_image_path?: string;
};

export type AdminOrder = {
  id: number;
  order_number: string;
  customer_name?: string | null;
  customer_email: string;
  total_minor: number;
  currency: string;
  order_status: string;
  payment_status: string;
  payment_gateway?: string | null;
  payment_transactions?: { gateway?: string | null }[];
  created_at: string;
  items: { product_name: string; total_minor: number }[];
};

export type AdminCustomer = {
  id: number;
  name: string;
  email: string;
  account_status?: string;
  verified?: boolean;
  created_at: string;
  updated_at: string;
  orders_count?: number;
  products_count?: number;
  paid_revenue_minor?: number;
  refunded_amount_minor?: number;
  net_revenue_minor?: number;
  ltv_minor?: number;
  first_purchase_at?: string | null;
  last_purchase_at?: string | null;
  roles?: { name: string }[];
};

export type AdminCoupon = {
  id: number;
  code: string;
  type: 'percent' | 'fixed';
  amount_minor?: number | null;
  percentage_bps?: number | null;
  status: 'active' | 'expired' | 'paused' | string;
  usage_limit?: number | null;
  expires_at?: string | null;
  starts_at?: string | null;
  minimum_order_minor?: number;
  per_customer_limit?: number | null;
};

export type AdminCouponPayload = {
  code: string;
  type: 'percent' | 'fixed';
  amount_minor?: number | null;
  percentage_bps?: number | null;
  status?: string;
  starts_at?: string | null;
  expires_at?: string | null;
  usage_limit?: number | null;
  per_customer_limit?: number | null;
  minimum_order_minor?: number;
  currency?: string;
  product_ids?: number[];
  bundle_ids?: number[];
};

export type AdminAuditLog = {
  id: number;
  action: string;
  auditable_type?: string | null;
  auditable_id?: number | null;
  metadata?: Record<string, unknown> | null;
  created_at: string;
  actor?: { id: number; name: string; email: string } | null;
};

export type AdminDashboardSummary = {
  metrics: {
    revenue_minor: number;
    orders: number;
    customers: number;
    products: number;
  };
  recent_orders: AdminOrder[];
  top_products: AdminProduct[];
};

export type AdminAnalyticsSummary = {
  summary: {
    revenue_minor: number;
    paid_revenue_minor: number;
    refunded_amount_minor: number;
    ltv_minor: number;
    purchases: number;
    visitors: number;
  };
  landing_pages: { id: number; name: string; slug: string; status: string; versions?: unknown[] }[];
  products: AdminProduct[];
};

export type AdminSettingsPayload = Record<string, Record<string, unknown>>;
type RawSettingsRow = { key: string; value: unknown };
type RawSettingsPayload = Record<string, RawSettingsRow[] | Record<string, unknown>>;

function pageItems<T>(payload: { data: Paginated<T> | T[] }): T[] {
  return Array.isArray(payload.data) ? payload.data : payload.data.data;
}

export async function getAdminProducts(filters: { q?: string; status?: string; type?: string } = {}): Promise<AdminProduct[]> {
  const params = new URLSearchParams();
  if (filters.q) params.set('q', filters.q);
  if (filters.status) params.set('status', filters.status);
  if (filters.type) params.set('type', filters.type);
  const suffix = params.toString() ? `?${params.toString()}` : '';
  return pageItems(await apiRequest<{ data: Paginated<AdminProduct> }>(`/admin/products${suffix}`));
}

export async function getAdminDashboard(): Promise<AdminDashboardSummary> {
  return (await apiRequest<{ data: AdminDashboardSummary }>('/admin/dashboard')).data;
}

export async function getAdminAnalytics(): Promise<AdminAnalyticsSummary> {
  return (await apiRequest<{ data: AdminAnalyticsSummary }>('/admin/analytics/summary')).data;
}

export async function getAdminSettings(): Promise<AdminSettingsPayload> {
  const response = await apiRequest<{ data: RawSettingsPayload }>('/admin/settings');
  return normalizeSettings(response.data);
}

export async function updateAdminSettings(section: string, payload: Record<string, unknown>): Promise<AdminSettingsPayload> {
  const response = await apiRequest<{ data: RawSettingsPayload }>(`/admin/settings/${section}`, {
    method: 'PATCH',
    body: JSON.stringify(payload),
  });
  return normalizeSettings(response.data);
}

function normalizeSettings(payload: RawSettingsPayload): AdminSettingsPayload {
  return Object.fromEntries(
    Object.entries(payload || {}).map(([group, value]) => {
      if (Array.isArray(value)) {
        return [group, Object.fromEntries(value.map((row) => [row.key, parseSettingValue(row.value)]))];
      }
      return [group, value];
    }),
  );
}

function parseSettingValue(value: unknown): unknown {
  if (typeof value !== 'string') return value;
  try {
    return JSON.parse(value);
  } catch {
    return value;
  }
}

export async function getAdminProduct(id: string): Promise<AdminProduct> {
  return (await apiRequest<{ data: AdminProduct }>(`/admin/products/${id}`)).data;
}

export async function createAdminProduct(payload: AdminProductPayload): Promise<AdminProduct> {
  return (await apiRequest<{ data: AdminProduct }>('/admin/products', {
    method: 'POST',
    body: JSON.stringify(payload),
  })).data;
}

export async function updateAdminProduct(id: string, payload: Partial<AdminProductPayload>): Promise<AdminProduct> {
  return (await apiRequest<{ data: AdminProduct }>(`/admin/products/${id}`, {
    method: 'PATCH',
    body: JSON.stringify(payload),
  })).data;
}

export async function publishAdminProduct(id: string): Promise<AdminProduct> {
  return (await apiRequest<{ data: AdminProduct }>(`/admin/products/${id}/publish`, {
    method: 'POST',
    body: JSON.stringify({}),
  })).data;
}

export async function uploadAdminProductFile(productId: string, file: File, name?: string): Promise<AdminProductFile> {
  const formData = new FormData();
  formData.append('file', file);
  if (name) formData.append('name', name);

  return (await apiFormRequest<{ data: AdminProductFile }>(`/admin/products/${productId}/files`, formData)).data;
}

export async function getAdminOrders(): Promise<AdminOrder[]> {
  const orders = pageItems(await apiRequest<{ data: Paginated<AdminOrder> }>('/admin/orders'));
  return orders.map((order) => ({
    ...order,
    payment_gateway: order.payment_transactions?.[0]?.gateway || null,
  }));
}

export async function refundAdminOrder(orderId: number): Promise<unknown> {
  return (await apiRequest<{ data: unknown }>(`/admin/orders/${orderId}/refund`, {
    method: 'POST',
    body: JSON.stringify({ confirm: true }),
  })).data;
}

export async function getAdminCustomers(): Promise<AdminCustomer[]> {
  return pageItems(await apiRequest<{ data: Paginated<AdminCustomer> }>('/admin/customers'));
}

export async function getAdminCoupons(): Promise<AdminCoupon[]> {
  return pageItems(await apiRequest<{ data: Paginated<AdminCoupon> }>('/admin/coupons'));
}

export async function createAdminCoupon(payload: AdminCouponPayload): Promise<AdminCoupon> {
  return (await apiRequest<{ data: AdminCoupon }>('/admin/coupons', {
    method: 'POST',
    body: JSON.stringify(payload),
  })).data;
}

export async function updateAdminCoupon(id: number, payload: Partial<AdminCouponPayload>): Promise<AdminCoupon> {
  return (await apiRequest<{ data: AdminCoupon }>(`/admin/coupons/${id}`, {
    method: 'PATCH',
    body: JSON.stringify(payload),
  })).data;
}

export async function pauseAdminCoupon(id: number): Promise<AdminCoupon> {
  return (await apiRequest<{ data: AdminCoupon }>(`/admin/coupons/${id}/pause`, {
    method: 'POST',
    body: JSON.stringify({}),
  })).data;
}

export async function archiveAdminCoupon(id: number): Promise<AdminCoupon> {
  return (await apiRequest<{ data: AdminCoupon }>(`/admin/coupons/${id}`, {
    method: 'DELETE',
  })).data;
}

export async function getAdminAuditLogs(filters: { action?: string; entity?: string; from?: string; to?: string } = {}): Promise<AdminAuditLog[]> {
  const params = new URLSearchParams();
  if (filters.action) params.set('action', filters.action);
  if (filters.entity) params.set('entity', filters.entity);
  if (filters.from) params.set('from', filters.from);
  if (filters.to) params.set('to', filters.to);
  const suffix = params.toString() ? `?${params.toString()}` : '';
  return pageItems(await apiRequest<{ data: Paginated<AdminAuditLog> }>(`/admin/audit-logs${suffix}`));
}

export function displayMinor(value?: number | null): number {
  return minorToDisplay(value) || 0;
}
