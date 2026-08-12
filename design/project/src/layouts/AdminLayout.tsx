import { useEffect } from 'react';
import { Outlet, useNavigate } from 'react-router-dom';
import { AdminSidebar } from '@/components/admin/AdminSidebar';
import { AdminTopbar } from '@/components/admin/AdminTopbar';
import { useAuth } from '@/services/auth-context';

export function AdminLayout() {
  const navigate = useNavigate();
  const { initializing, isAdmin } = useAuth();

  useEffect(() => {
    if (!initializing && !isAdmin) navigate('/login', { replace: true });
  }, [initializing, isAdmin, navigate]);

  if (initializing || !isAdmin) {
    return <div className="flex min-h-screen items-center justify-center bg-ink-50 text-sm text-ink-500">Loading admin...</div>;
  }

  return (
    <div className="min-h-screen bg-ink-50">
      <AdminSidebar />
      <div className="lg:pl-64">
        <AdminTopbar />
        <main className="p-4 sm:p-6 lg:p-8">
          <Outlet />
        </main>
      </div>
    </div>
  );
}
