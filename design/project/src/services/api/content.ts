import { apiRequest } from './client';

export type ContentPage = {
  id: number;
  title: string;
  slug: string;
  content?: string | null;
  meta_title?: string | null;
  meta_description?: string | null;
};

export async function getContentPage(slug: string): Promise<ContentPage> {
  return (await apiRequest<{ data: ContentPage }>(`/content-pages/${slug}`)).data;
}

export async function submitContact(payload: { name: string; email: string; subject: string; message: string }): Promise<string> {
  return (await apiRequest<{ data: { message: string } }>('/contact', {
    method: 'POST',
    body: JSON.stringify(payload),
  })).data.message;
}
