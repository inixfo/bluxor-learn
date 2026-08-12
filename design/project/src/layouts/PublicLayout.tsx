import { Outlet } from 'react-router-dom';
import { Navbar } from '@/components/storefront/Navbar';
import { Footer } from '@/components/storefront/Footer';

export function PublicLayout() {
  return (
    <div className="flex min-h-screen flex-col bg-ink-50">
      <Navbar />
      <main className="flex-1">
        <Outlet />
      </main>
      <Footer />
    </div>
  );
}
