import { Navigate, Outlet } from 'react-router-dom';
import { authenticatedHomePath } from '@/services/auth-routing';
import { useAuth } from '@/services/auth-context';

export function GuestOnlyLayout() {
  const { initializing, isAdmin, user } = useAuth();

  if (initializing) {
    return (
      <div className="flex min-h-[calc(100vh-4rem)] items-center justify-center bg-ink-50 px-4 text-sm text-ink-500">
        Loading...
      </div>
    );
  }

  if (user) {
    return <Navigate to={isAdmin ? '/admin' : authenticatedHomePath(user)} replace />;
  }

  return <Outlet />;
}
