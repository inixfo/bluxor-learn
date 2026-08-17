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
  community_enabled?: boolean;
  community_name?: string | null;
  community_url?: string | null;
  short_description?: string | null;
  description?: string | null;
  updated_at: string;
  deleted_at?: string | null;
  category_id?: number | null;
  category?: { id?: number; name: string } | null;
  files?: AdminProductFile[];
  resources?: AdminResource[];
};

export type AdminProductFile = {
  id: number;
  name: string;
  file_type: string;
  file_size_bytes: number;
  version: string;
  status: string;
};

export type AdminResourceStatus = 'draft' | 'published' | 'archived';
export type AdminResourceAccess = 'public' | 'purchase_required';
export type AdminResourceSource = 'uploaded_file' | 'external_url';

export type AdminResourceVersion = {
  id: number;
  resource_id: number;
  version: string;
  original_filename?: string | null;
  file_size?: number | null;
  created_at: string;
  creator?: { id: number; name: string; email: string } | null;
};

export type AdminResource = {
  id: number;
  title: string;
  slug: string;
  description?: string | null;
  resource_type: string;
  source_type: AdminResourceSource;
  external_url?: string | null;
  original_filename?: string | null;
  mime_type?: string | null;
  file_size?: number | null;
  version: string;
  access_type: AdminResourceAccess;
  status: AdminResourceStatus;
  download_count: number;
  updated_at: string;
  products?: { id: number; name: string; slug: string }[];
  versions?: AdminResourceVersion[];
};

export type AdminResourcePayload = {
  title: string;
  slug: string;
  description?: string;
  resource_type: string;
  source_type: AdminResourceSource;
  external_url?: string;
  product_ids?: number[];
  access_type: AdminResourceAccess;
  version: string;
  status: AdminResourceStatus;
  file?: File | null;
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
  cover_image?: File | null;
  remove_cover_image?: boolean;
  community_enabled?: boolean;
  community_name?: string | null;
  community_url?: string | null;
  category_id?: number | null;
};

export type AdminCategory = {
  id: number;
  name: string;
  name_bn?: string | null;
  slug: string;
  description?: string | null;
  image_path?: string | null;
  status: 'active' | 'inactive';
  sort_order: number;
  products_count?: number;
};

export type AdminContentPage = {
  id: number;
  title: string;
  slug: string;
  content?: string | null;
  meta_title?: string | null;
  meta_description?: string | null;
  status: 'draft' | 'published';
};

export type AdminNotification = {
  id: number;
  type: string;
  title: string;
  message?: string | null;
  url?: string | null;
  read_at?: string | null;
  created_at: string;
};

export type AdminContactInquiryStatus = 'new' | 'read' | 'replied' | 'resolved' | 'spam';

export type AdminContactInquiryReply = {
  id: number;
  contact_inquiry_id: number;
  admin_user_id?: number | null;
  sent_to: string;
  subject: string;
  message: string;
  created_at: string;
  admin?: { id: number; name: string; email: string } | null;
};

export type AdminContactInquiry = {
  id: number;
  uuid: string;
  name: string;
  email: string;
  subject: string;
  message: string;
  status: AdminContactInquiryStatus;
  read_at?: string | null;
  replied_at?: string | null;
  resolved_at?: string | null;
  admin_notes?: string | null;
  created_at: string;
  updated_at: string;
  replies?: AdminContactInquiryReply[];
};

export type AdminContactInquiryCounts = Record<'all' | AdminContactInquiryStatus, number>;

export type AdminFaqCategory = {
  id: number;
  name: string;
  slug: string;
  sort_order: number;
  status: 'active' | 'inactive';
  items_count?: number;
};

export type AdminFaqItem = {
  id: number;
  faq_category_id: number;
  question: string;
  answer: string;
  sort_order: number;
  status: 'active' | 'inactive';
  category?: AdminFaqCategory;
};

export type AdminHelpCategory = {
  id: number;
  name: string;
  slug: string;
  description?: string | null;
  icon?: string | null;
  sort_order: number;
  status: 'active' | 'inactive';
  articles_count?: number;
};

export type AdminHelpArticle = {
  id: number;
  help_category_id: number;
  title: string;
  slug: string;
  summary?: string | null;
  content: string;
  sort_order: number;
  is_featured: boolean;
  status: 'draft' | 'published';
  views?: number;
  category?: AdminHelpCategory;
};

export type AdminOrder = {
  id: number;
  order_number: string;
  customer_name?: string | null;
  customer_email: string;
  customer_phone?: string | null;
  customer_key?: string;
  user_id?: number | null;
  checkout_type?: 'guest' | 'account' | null;
  checkout_type_label?: string;
  current_account_status?: string;
  current_account_status_label?: string;
  subtotal_minor?: number;
  discount_minor?: number;
  total_minor: number;
  currency: string;
  coupon_id?: number | null;
  order_status: string;
  payment_status: string;
  payment_gateway?: string | null;
  payment_transactions?: {
    id: number;
    gateway?: string | null;
    provider_transaction_id?: string | null;
    provider_reference?: string | null;
    validation_id?: string | null;
    amount_minor?: number;
    currency?: string;
    status?: string;
    normalized_state?: string | null;
    paid_at?: string | null;
    failed_at?: string | null;
    verified_at?: string | null;
  }[];
  entitlements?: {
    id: number;
    product_id: number;
    product_name?: string | null;
    status: string;
    granted_at?: string | null;
    expires_at?: string | null;
  }[];
  admin_notes?: string | null;
  payment_completed_at?: string | null;
  communities?: { name: string; url: string; product_id?: number; product_name?: string }[];
  attribution?: {
    source?: string | null;
    medium?: string | null;
    campaign?: string | null;
    content?: string | null;
    term?: string | null;
    landing_url?: string | null;
    path?: string | null;
    referrer?: string | null;
    referrer_host?: string | null;
    visitor_id?: string | null;
    session_id?: string | null;
    landing_page_id?: number | null;
    landing_page_version_id?: number | null;
    offer_key?: string | null;
    first_touch?: Record<string, unknown> | null;
    last_touch?: Record<string, unknown> | null;
  };
  actions?: {
    can_cancel: boolean;
    can_refund: boolean;
    can_resend_email: boolean;
    can_mark_paid: boolean;
  };
  created_at: string;
  updated_at?: string;
  items: {
    id?: number;
    product_name: string;
    product_slug?: string;
    quantity?: number;
    unit_price_minor?: number;
    discount_minor?: number;
    total_minor: number;
    currency?: string;
    product_id?: number | null;
    bundle_id?: number | null;
    purchasable_type?: string;
  }[];
};

export type AdminCustomer = {
  id: number;
  customer_key: string;
  name: string;
  email: string;
  phone?: string | null;
  account_status?: string;
  account_status_label?: string;
  has_account?: boolean;
  verified?: boolean;
  created_at: string;
  updated_at: string;
  orders_count?: number;
  paid_orders_count?: number;
  unpaid_orders_count?: number;
  products_count?: number;
  products?: string[];
  paid_revenue_minor?: number;
  refunded_amount_minor?: number;
  net_revenue_minor?: number;
  ltv_minor?: number;
  first_purchase_at?: string | null;
  last_purchase_at?: string | null;
  last_order_number?: string | null;
  auth_provider?: string | null;
  roles?: { name: string }[];
};

export type AdminCustomerDetail = {
  summary: AdminCustomer;
  orders: AdminOrder[];
  entitlements: NonNullable<AdminOrder['entitlements']>;
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
    new_support_messages: number;
    unresolved_inquiries: number;
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

export type AdminMetaTrackingStatus = {
  meta: {
    pixel_enabled: boolean;
    pixel_effective_enabled: boolean;
    pixel_id: string;
    pixel_id_configured: boolean;
    pixel_env_enabled: boolean;
    capi_enabled: boolean;
    capi_effective_enabled: boolean;
    capi_env_enabled: boolean;
    capi_token_configured: boolean;
    graph_api_version: string;
    test_event_code_configured: boolean;
    require_marketing_consent: boolean;
  };
  recent_events: {
    event_name: string;
    event_id: string;
    order_id?: number | null;
    status: string;
    attempts: number;
    last_error_code?: string | null;
    last_error_message?: string | null;
    sent_at?: string | null;
    created_at: string;
  }[];
};

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

export async function getAdminMetaTracking(): Promise<AdminMetaTrackingStatus> {
  return (await apiRequest<{ data: AdminMetaTrackingStatus }>('/admin/tracking/meta')).data;
}

export async function updateAdminMetaTracking(payload: {
  pixel_enabled: boolean;
  pixel_id?: string;
  capi_enabled: boolean;
  graph_api_version?: string;
}): Promise<AdminMetaTrackingStatus> {
  return (await apiRequest<{ data: AdminMetaTrackingStatus }>('/admin/tracking/meta', {
    method: 'PATCH',
    body: JSON.stringify(payload),
  })).data;
}

export async function sendAdminMetaTestEvent(): Promise<{ ok: boolean; message: string }> {
  return (await apiRequest<{ data: { ok: boolean; message: string } }>('/admin/tracking/meta/test', {
    method: 'POST',
    body: JSON.stringify({}),
  })).data;
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
  if (payload.cover_image) {
    return (await apiFormRequest<{ data: AdminProduct }>('/admin/products', productFormData(payload))).data;
  }

  return (await apiRequest<{ data: AdminProduct }>('/admin/products', { method: 'POST', body: JSON.stringify(payload) })).data;
}

export async function updateAdminProduct(id: string, payload: Partial<AdminProductPayload>): Promise<AdminProduct> {
  if (payload.cover_image) {
    const formData = productFormData(payload);
    formData.append('_method', 'PATCH');
    return (await apiFormRequest<{ data: AdminProduct }>(`/admin/products/${id}`, formData)).data;
  }

  return (await apiRequest<{ data: AdminProduct }>(`/admin/products/${id}`, { method: 'PATCH', body: JSON.stringify(payload) })).data;
}

function productFormData(payload: Partial<AdminProductPayload>): FormData {
  const formData = new FormData();
  Object.entries(payload).forEach(([key, value]) => {
    if (value === undefined || value === null || key === 'cover_image') return;
    if (key === 'remove_cover_image') {
      if (value === true) formData.append(key, formBoolean(value));
      return;
    }
    if (typeof value === 'boolean') {
      formData.append(key, formBoolean(value));
      return;
    }
    formData.append(key, String(value));
  });
  if (payload.cover_image) formData.append('cover_image', payload.cover_image);
  return formData;
}

export async function publishAdminProduct(id: string): Promise<AdminProduct> {
  return (await apiRequest<{ data: AdminProduct }>(`/admin/products/${id}/publish`, {
    method: 'POST',
    body: JSON.stringify({}),
  })).data;
}

export async function archiveAdminProduct(id: string): Promise<AdminProduct> {
  return (await apiRequest<{ data: AdminProduct }>(`/admin/products/${id}/archive`, {
    method: 'POST',
    body: JSON.stringify({}),
  })).data;
}

export async function restoreAdminProduct(id: string): Promise<AdminProduct> {
  return (await apiRequest<{ data: AdminProduct }>(`/admin/products/${id}/restore`, {
    method: 'POST',
    body: JSON.stringify({}),
  })).data;
}

export async function deleteAdminProduct(id: string): Promise<{ ok: boolean; active_landing_pages?: number }> {
  return (await apiRequest<{ data: { ok: boolean; active_landing_pages?: number } }>(`/admin/products/${id}`, {
    method: 'DELETE',
  })).data;
}

export async function restoreDeletedAdminProduct(id: string): Promise<AdminProduct> {
  return (await apiRequest<{ data: AdminProduct }>(`/admin/products/${id}/restore-deleted`, {
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

export async function getAdminResources(filters: { q?: string; product_id?: number | string; resource_type?: string; status?: string; access_type?: string } = {}): Promise<AdminResource[]> {
  const params = new URLSearchParams();
  if (filters.q) params.set('q', filters.q);
  if (filters.product_id) params.set('product_id', String(filters.product_id));
  if (filters.resource_type) params.set('resource_type', filters.resource_type);
  if (filters.status) params.set('status', filters.status);
  if (filters.access_type) params.set('access_type', filters.access_type);
  const suffix = params.toString() ? `?${params.toString()}` : '';
  return pageItems(await apiRequest<{ data: Paginated<AdminResource> }>(`/admin/resources${suffix}`));
}

export async function getAdminResource(id: number): Promise<AdminResource> {
  return (await apiRequest<{ data: AdminResource }>(`/admin/resources/${id}`)).data;
}

export async function createAdminResource(payload: AdminResourcePayload): Promise<AdminResource> {
  return (await apiFormRequest<{ data: AdminResource }>('/admin/resources', resourceFormData(payload))).data;
}

export async function updateAdminResource(id: number, payload: Partial<AdminResourcePayload>): Promise<AdminResource> {
  const formData = resourceFormData(payload);
  formData.append('_method', 'PATCH');
  return (await apiFormRequest<{ data: AdminResource }>(`/admin/resources/${id}`, formData)).data;
}

export async function archiveAdminResource(id: number): Promise<AdminResource> {
  return (await apiRequest<{ data: AdminResource }>(`/admin/resources/${id}/archive`, {
    method: 'POST',
    body: JSON.stringify({}),
  })).data;
}

export async function attachAdminProductResource(productId: string, resourceId: number): Promise<AdminProduct> {
  return (await apiRequest<{ data: AdminProduct }>(`/admin/products/${productId}/resources`, {
    method: 'POST',
    body: JSON.stringify({ resource_id: resourceId }),
  })).data;
}

export async function detachAdminProductResource(productId: string, resourceId: number): Promise<void> {
  await apiRequest(`/admin/products/${productId}/resources/${resourceId}`, { method: 'DELETE' });
}

function resourceFormData(payload: Partial<AdminResourcePayload>): FormData {
  const formData = new FormData();
  Object.entries(payload).forEach(([key, value]) => {
    if (value === undefined || value === null || key === 'file' || key === 'product_ids') return;
    formData.append(key, String(value));
  });
  payload.product_ids?.forEach((id) => formData.append('product_ids[]', String(id)));
  if (payload.file) formData.append('file', payload.file);
  return formData;
}

export async function getAdminOrders(): Promise<AdminOrder[]> {
  const orders = pageItems(await apiRequest<{ data: Paginated<AdminOrder> }>('/admin/orders'));
  return orders.map((order) => ({
    ...order,
    payment_gateway: order.payment_gateway || order.payment_transactions?.[0]?.gateway || null,
  }));
}

export async function getAdminOrder(id: number): Promise<AdminOrder> {
  return (await apiRequest<{ data: AdminOrder }>(`/admin/orders/${id}`)).data;
}

export async function cancelAdminOrder(id: number): Promise<AdminOrder> {
  return (await apiRequest<{ data: AdminOrder }>(`/admin/orders/${id}/cancel`, {
    method: 'POST',
    body: JSON.stringify({}),
  })).data;
}

export async function resendAdminOrderEmail(id: number): Promise<string> {
  return (await apiRequest<{ data: { message: string } }>(`/admin/orders/${id}/resend-email`, {
    method: 'POST',
    body: JSON.stringify({}),
  })).data.message;
}

export async function updateAdminOrderNotes(id: number, adminNotes: string): Promise<AdminOrder> {
  return (await apiRequest<{ data: AdminOrder }>(`/admin/orders/${id}/notes`, {
    method: 'PATCH',
    body: JSON.stringify({ admin_notes: adminNotes }),
  })).data;
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

export async function getAdminCustomer(customerKey: string): Promise<AdminCustomerDetail> {
  return (await apiRequest<{ data: AdminCustomerDetail }>(`/admin/customers/${customerKey}`)).data;
}

export async function suspendAdminCustomer(userId: number): Promise<AdminCustomer> {
  return (await apiRequest<{ data: AdminCustomer }>(`/admin/customers/${userId}/suspend`, {
    method: 'POST',
    body: JSON.stringify({}),
  })).data;
}

export async function reactivateAdminCustomer(userId: number): Promise<AdminCustomer> {
  return (await apiRequest<{ data: AdminCustomer }>(`/admin/customers/${userId}/reactivate`, {
    method: 'POST',
    body: JSON.stringify({}),
  })).data;
}

export async function getAdminCategories(): Promise<AdminCategory[]> {
  return (await apiRequest<{ data: AdminCategory[] }>('/admin/categories')).data;
}

export async function createAdminCategory(payload: Partial<AdminCategory> & { image?: File | null }): Promise<AdminCategory> {
  return (await apiFormRequest<{ data: AdminCategory }>('/admin/categories', categoryFormData(payload))).data;
}

export async function updateAdminCategory(id: number, payload: Partial<AdminCategory> & { image?: File | null; remove_image?: boolean }): Promise<AdminCategory> {
  const formData = categoryFormData(payload);
  formData.append('_method', 'PATCH');
  return (await apiFormRequest<{ data: AdminCategory }>(`/admin/categories/${id}`, formData)).data;
}

export async function deleteAdminCategory(id: number): Promise<void> {
  await apiRequest(`/admin/categories/${id}`, { method: 'DELETE' });
}

function categoryFormData(payload: Partial<AdminCategory> & { image?: File | null; remove_image?: boolean }): FormData {
  const formData = new FormData();
  Object.entries(payload).forEach(([key, value]) => {
    if (value === undefined || value === null || key === 'image') return;
    if (key === 'remove_image') {
      if (value === true) formData.append(key, formBoolean(value));
      return;
    }
    if (typeof value === 'boolean') {
      formData.append(key, formBoolean(value));
      return;
    }
    formData.append(key, String(value));
  });
  if (payload.image) formData.append('image', payload.image);
  return formData;
}

function formBoolean(value: boolean): string {
  return value ? '1' : '0';
}

export async function getAdminContentPages(): Promise<AdminContentPage[]> {
  return (await apiRequest<{ data: AdminContentPage[] }>('/admin/content-pages')).data;
}

export async function updateAdminContentPage(id: number, payload: Partial<AdminContentPage>): Promise<AdminContentPage> {
  return (await apiRequest<{ data: AdminContentPage }>(`/admin/content-pages/${id}`, { method: 'PATCH', body: JSON.stringify(payload) })).data;
}

export async function sendAdminTestEmail(email: string): Promise<string> {
  return (await apiRequest<{ data: { message: string } }>('/admin/settings/email/test', { method: 'POST', body: JSON.stringify({ email }) })).data.message;
}

export async function getAdminNotifications(): Promise<AdminNotification[]> {
  return (await apiRequest<{ data: AdminNotification[] }>('/admin/notifications')).data;
}

export async function getAdminUnreadNotificationCount(): Promise<number> {
  return (await apiRequest<{ data: { count: number } }>('/admin/notifications/unread-count')).data.count;
}

export async function markAdminNotificationRead(id: number): Promise<AdminNotification> {
  return (await apiRequest<{ data: AdminNotification }>(`/admin/notifications/${id}/read`, { method: 'POST', body: JSON.stringify({}) })).data;
}

export async function markAllAdminNotificationsRead(): Promise<void> {
  await apiRequest('/admin/notifications/read-all', { method: 'POST', body: JSON.stringify({}) });
}

export async function getAdminContactInquiries(filters: { q?: string; status?: string } = {}): Promise<{ items: AdminContactInquiry[]; counts: AdminContactInquiryCounts }> {
  const params = new URLSearchParams();
  if (filters.q) params.set('q', filters.q);
  if (filters.status) params.set('status', filters.status);
  const suffix = params.toString() ? `?${params.toString()}` : '';
  const response = await apiRequest<{ data: { items: Paginated<AdminContactInquiry>; counts: AdminContactInquiryCounts } }>(`/admin/contact-inquiries${suffix}`);
  return {
    items: pageItems({ data: response.data.items }),
    counts: response.data.counts,
  };
}

export async function getAdminContactInquiry(id: string): Promise<AdminContactInquiry> {
  return (await apiRequest<{ data: AdminContactInquiry }>(`/admin/contact-inquiries/${id}`)).data;
}

export async function updateAdminContactInquiry(id: number, payload: { status?: AdminContactInquiryStatus; admin_notes?: string | null }): Promise<AdminContactInquiry> {
  return (await apiRequest<{ data: AdminContactInquiry }>(`/admin/contact-inquiries/${id}`, {
    method: 'PATCH',
    body: JSON.stringify(payload),
  })).data;
}

export async function replyToAdminContactInquiry(id: number, payload: { subject: string; message: string }): Promise<AdminContactInquiry> {
  return (await apiRequest<{ data: AdminContactInquiry }>(`/admin/contact-inquiries/${id}/reply`, {
    method: 'POST',
    body: JSON.stringify(payload),
  })).data;
}

export async function getAdminFaqCategories(): Promise<AdminFaqCategory[]> {
  return (await apiRequest<{ data: AdminFaqCategory[] }>('/admin/faq-categories')).data;
}

export async function createAdminFaqCategory(payload: Partial<AdminFaqCategory>): Promise<AdminFaqCategory> {
  return (await apiRequest<{ data: AdminFaqCategory }>('/admin/faq-categories', { method: 'POST', body: JSON.stringify(payload) })).data;
}

export async function updateAdminFaqCategory(id: number, payload: Partial<AdminFaqCategory>): Promise<AdminFaqCategory> {
  return (await apiRequest<{ data: AdminFaqCategory }>(`/admin/faq-categories/${id}`, { method: 'PATCH', body: JSON.stringify(payload) })).data;
}

export async function deleteAdminFaqCategory(id: number): Promise<void> {
  await apiRequest(`/admin/faq-categories/${id}`, { method: 'DELETE' });
}

export async function getAdminFaqItems(categoryId?: number): Promise<AdminFaqItem[]> {
  const suffix = categoryId ? `?${new URLSearchParams({ category_id: String(categoryId) }).toString()}` : '';
  return (await apiRequest<{ data: AdminFaqItem[] }>(`/admin/faq-items${suffix}`)).data;
}

export async function createAdminFaqItem(payload: Partial<AdminFaqItem>): Promise<AdminFaqItem> {
  return (await apiRequest<{ data: AdminFaqItem }>('/admin/faq-items', { method: 'POST', body: JSON.stringify(payload) })).data;
}

export async function updateAdminFaqItem(id: number, payload: Partial<AdminFaqItem>): Promise<AdminFaqItem> {
  return (await apiRequest<{ data: AdminFaqItem }>(`/admin/faq-items/${id}`, { method: 'PATCH', body: JSON.stringify(payload) })).data;
}

export async function deleteAdminFaqItem(id: number): Promise<void> {
  await apiRequest(`/admin/faq-items/${id}`, { method: 'DELETE' });
}

export async function getAdminHelpCategories(): Promise<AdminHelpCategory[]> {
  return (await apiRequest<{ data: AdminHelpCategory[] }>('/admin/help-categories')).data;
}

export async function createAdminHelpCategory(payload: Partial<AdminHelpCategory>): Promise<AdminHelpCategory> {
  return (await apiRequest<{ data: AdminHelpCategory }>('/admin/help-categories', { method: 'POST', body: JSON.stringify(payload) })).data;
}

export async function updateAdminHelpCategory(id: number, payload: Partial<AdminHelpCategory>): Promise<AdminHelpCategory> {
  return (await apiRequest<{ data: AdminHelpCategory }>(`/admin/help-categories/${id}`, { method: 'PATCH', body: JSON.stringify(payload) })).data;
}

export async function deleteAdminHelpCategory(id: number): Promise<void> {
  await apiRequest(`/admin/help-categories/${id}`, { method: 'DELETE' });
}

export async function getAdminHelpArticles(filters: { category_id?: number; status?: string } = {}): Promise<AdminHelpArticle[]> {
  const params = new URLSearchParams();
  if (filters.category_id) params.set('category_id', String(filters.category_id));
  if (filters.status) params.set('status', filters.status);
  const suffix = params.toString() ? `?${params.toString()}` : '';
  return (await apiRequest<{ data: AdminHelpArticle[] }>(`/admin/help-articles${suffix}`)).data;
}

export async function createAdminHelpArticle(payload: Partial<AdminHelpArticle>): Promise<AdminHelpArticle> {
  return (await apiRequest<{ data: AdminHelpArticle }>('/admin/help-articles', { method: 'POST', body: JSON.stringify(payload) })).data;
}

export async function updateAdminHelpArticle(id: number, payload: Partial<AdminHelpArticle>): Promise<AdminHelpArticle> {
  return (await apiRequest<{ data: AdminHelpArticle }>(`/admin/help-articles/${id}`, { method: 'PATCH', body: JSON.stringify(payload) })).data;
}

export async function deleteAdminHelpArticle(id: number): Promise<void> {
  await apiRequest(`/admin/help-articles/${id}`, { method: 'DELETE' });
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
