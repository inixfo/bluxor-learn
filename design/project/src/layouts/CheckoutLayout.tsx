import { Outlet, Link } from 'react-router-dom';
import { ShieldCheck, ArrowLeft } from 'lucide-react';

export function CheckoutLayout() {
  return (
    <div className="flex min-h-screen flex-col bg-ink-50">
      <header className="border-b border-ink-100 bg-white">
        <div className="container-page flex h-16 items-center justify-between">
          <Link to="/" className="flex items-center gap-2">
            <div className="flex h-9 w-9 items-center justify-center rounded-xl bg-brand-600 text-white">
              <ShieldCheck className="h-5 w-5" />
            </div>
            <span className="text-lg font-bold text-ink-900">Learn by Bluxor</span>
          </Link>
          <Link to="/products" className="flex items-center gap-1.5 text-sm text-ink-500 transition-colors hover:text-brand-600">
            <ArrowLeft className="h-4 w-4" />
            Continue shopping
          </Link>
        </div>
      </header>
      <main className="flex-1">
        <Outlet />
      </main>
    </div>
  );
}
