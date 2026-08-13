import { apiRequest } from './client';

export type AuthUser = {
  id: number;
  name: string;
  email: string;
  phone?: string | null;
  email_verified_at?: string | null;
  roles?: { name: string }[];
};

export async function currentUser(): Promise<AuthUser | null> {
  const response = await apiRequest<{ data: AuthUser | null }>('/auth/me');
  return response.data;
}

export async function login(payload: { email: string; password: string; remember?: boolean }): Promise<AuthUser> {
  const response = await apiRequest<{ data: AuthUser }>('/auth/login', {
    method: 'POST',
    body: JSON.stringify(payload),
  });
  return response.data;
}

export async function register(payload: { name: string; email: string; password: string; password_confirmation: string }): Promise<AuthUser> {
  const response = await apiRequest<{ data: AuthUser }>('/auth/register', {
    method: 'POST',
    body: JSON.stringify(payload),
  });
  return response.data;
}

export async function logout(): Promise<void> {
  await apiRequest('/auth/logout', { method: 'POST', body: JSON.stringify({}) });
}

export async function forgotPassword(email: string): Promise<string> {
  const response = await apiRequest<{ data: { message: string } }>('/auth/forgot-password', {
    method: 'POST',
    body: JSON.stringify({ email }),
  });
  return response.data.message;
}

export async function resetPassword(payload: { token: string; email: string; password: string; password_confirmation: string }): Promise<string> {
  const response = await apiRequest<{ data: { message: string } }>('/auth/reset-password', {
    method: 'POST',
    body: JSON.stringify(payload),
  });
  return response.data.message;
}

export async function resendVerification(): Promise<string> {
  const response = await apiRequest<{ data: { message: string } }>('/auth/email/verification-notification', {
    method: 'POST',
    body: JSON.stringify({}),
  });
  return response.data.message;
}

export async function googleRedirectUrl(returnTo: string): Promise<string> {
  const params = new URLSearchParams({ return_to: returnTo });
  return (await apiRequest<{ data: { url: string } }>(`/auth/google/redirect?${params.toString()}`)).data.url;
}
