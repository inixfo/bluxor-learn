import { Outlet, useNavigate } from 'react-router-dom';
import { useEffect } from 'react';
import { CustomerSidebar } from '@/components/customer/CustomerSidebar';
import { useAuth } from '@/services/auth-context';

export function CustomerLayout() {
  const navigate = useNavigate();
  const { user, initializing } = useAuth();

  useEffect(() => {
    if (!initializing && !user) navigate('/login');
  }, [initializing, navigate, user]);

  if (initializing || !user) {
    return <div className="min-h-screen bg-ink-50 p-8 text-sm text-ink-500">Loading account...</div>;
  }

  return (
    <div className="min-h-screen bg-ink-50">
      <div className="container-page flex gap-8 py-8">
        <CustomerSidebar />
        <main className="min-w-0 flex-1">
          <Outlet />
        </main>
      </div>
    </div>
  );
}
