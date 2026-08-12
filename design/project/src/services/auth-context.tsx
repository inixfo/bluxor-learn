/* eslint-disable react-refresh/only-export-components */
import { createContext, useCallback, useContext, useEffect, useMemo, useState } from 'react';
import { currentUser, login as loginRequest, logout as logoutRequest, register as registerRequest, type AuthUser } from '@/services/api/auth';

type AuthContextValue = {
  user: AuthUser | null;
  initializing: boolean;
  isAdmin: boolean;
  refresh: () => Promise<void>;
  login: (payload: { email: string; password: string; remember?: boolean }) => Promise<AuthUser>;
  register: (payload: { name: string; email: string; password: string; password_confirmation: string }) => Promise<AuthUser>;
  logout: () => Promise<void>;
};

const AuthContext = createContext<AuthContextValue | null>(null);

export function AuthProvider({ children }: { children: React.ReactNode }) {
  const [user, setUser] = useState<AuthUser | null>(null);
  const [initializing, setInitializing] = useState(true);

  const refresh = useCallback(async () => {
    try {
      setUser(await currentUser());
    } catch {
      setUser(null);
    }
  }, []);

  useEffect(() => {
    refresh().finally(() => setInitializing(false));
  }, [refresh]);

  useEffect(() => {
    const clear = () => setUser(null);
    window.addEventListener('learn-bluxor:unauthorized', clear);
    return () => window.removeEventListener('learn-bluxor:unauthorized', clear);
  }, []);

  const value = useMemo<AuthContextValue>(() => ({
    user,
    initializing,
    isAdmin: !!user?.roles?.some((role) => role.name === 'admin'),
    refresh,
    login: async (payload) => {
      const next = await loginRequest(payload);
      setUser(next);
      return next;
    },
    register: async (payload) => {
      const next = await registerRequest(payload);
      setUser(next);
      return next;
    },
    logout: async () => {
      await logoutRequest().catch(() => undefined);
      setUser(null);
    },
  }), [initializing, refresh, user]);

  return <AuthContext.Provider value={value}>{children}</AuthContext.Provider>;
}

export function useAuth() {
  const context = useContext(AuthContext);
  if (!context) throw new Error('useAuth must be used inside AuthProvider');
  return context;
}
