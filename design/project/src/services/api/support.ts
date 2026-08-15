import { apiRequest } from './client';

export type FaqCategory = {
  id: number;
  name: string;
  slug: string;
  sort_order: number;
  status: string;
  items: FaqItem[];
};

export type FaqItem = {
  id: number;
  faq_category_id: number;
  question: string;
  answer: string;
  sort_order: number;
  status: string;
};

export type HelpCategory = {
  id: number;
  name: string;
  slug: string;
  description?: string | null;
  icon?: string | null;
  sort_order: number;
  status: string;
  articles_count?: number;
};

export type HelpArticle = {
  id: number;
  help_category_id: number;
  title: string;
  slug: string;
  summary?: string | null;
  content: string;
  sort_order: number;
  is_featured: boolean;
  status: string;
  views: number;
  category?: HelpCategory;
  created_at: string;
  updated_at: string;
};

export type ContactSettings = {
  support_email?: string | null;
  support_phone?: string | null;
  support_whatsapp?: string | null;
  business_name?: string | null;
  business_address?: string | null;
  support_availability_text?: string | null;
};

export async function getFaq(params: { q?: string; category?: string } = {}): Promise<FaqCategory[]> {
  const query = new URLSearchParams();
  if (params.q) query.set('q', params.q);
  if (params.category) query.set('category', params.category);
  const suffix = query.toString() ? `?${query.toString()}` : '';
  return (await apiRequest<{ data: FaqCategory[] }>(`/faq${suffix}`)).data;
}

export async function getHelpCenter(q?: string): Promise<{ categories: HelpCategory[]; featured_articles: HelpArticle[]; results: HelpArticle[] }> {
  const suffix = q ? `?${new URLSearchParams({ q }).toString()}` : '';
  return (await apiRequest<{ data: { categories: HelpCategory[]; featured_articles: HelpArticle[]; results: HelpArticle[] } }>(`/help-center${suffix}`)).data;
}

export async function getHelpArticle(categorySlug: string, articleSlug: string): Promise<{ article: HelpArticle; related: HelpArticle[] }> {
  return (await apiRequest<{ data: { article: HelpArticle; related: HelpArticle[] } }>(`/help-center/${categorySlug}/${articleSlug}`)).data;
}

export async function getContactSettings(): Promise<ContactSettings> {
  return (await apiRequest<{ data: ContactSettings }>('/settings/contact')).data;
}
