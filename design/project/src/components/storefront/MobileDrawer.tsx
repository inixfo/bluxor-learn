import { Link } from 'react-router-dom';
import { ShieldCheck, X, LayoutGrid, Package, Sparkles, BookOpen, LogIn, LayoutDashboard, LogOut } from 'lucide-react';
import { Button } from '@/components/ui/Button';
import type { AuthUser } from '@/services/api/auth';

const links = [
  { icon: LayoutGrid, label: 'Explore', to: '/products' },
  { icon: Package, label: 'Bundles', to: '/products?filter=bundle' },
  { icon: Sparkles, label: 'New Releases', to: '/products?sort=newest' },
  { icon: BookOpen, label: 'My Library', to: '/account/library' },
];

export function MobileDrawer({ open, onClose, user, isAdmin, onLogout }: { open: boolean; onClose: () => void; user: AuthUser | null; isAdmin: boolean; onLogout: () => Promise<void> }) {
  if (!open) return null;
  return (
    <div className="fixed inset-0 z-50 lg:hidden">
      <div className="absolute inset-0 bg-ink-950/40 backdrop-blur-sm animate-fade-in" onClick={onClose} />
      <div className="absolute left-0 top-0 h-full w-80 max-w-[85vw] animate-slide-in-right bg-white p-5 shadow-lift">
        <div className="flex items-center justify-between">
          <Link to="/" onClick={onClose} className="flex items-center gap-2.5">
            <div className="flex h-9 w-9 items-center justify-center rounded-xl bg-gradient-to-br from-brand-600 to-brand-700 text-white">
              <ShieldCheck className="h-5 w-5" />
            </div>
            <span className="text-base font-bold text-ink-900">Learn by Bluxor</span>
          </Link>
          <button onClick={onClose} className="rounded-lg p-1.5 text-ink-400 hover:bg-ink-100">
            <X className="h-5 w-5" />
          </button>
        </div>

        <nav className="mt-6 space-y-1">
          {links.map((l) => (
            <Link
              key={l.label}
              to={l.to}
              onClick={onClose}
              className="flex items-center gap-3 rounded-xl px-3 py-3 text-sm font-medium text-ink-700 transition-colors hover:bg-ink-100"
            >
              <l.icon className="h-5 w-5 text-ink-400" />
              {l.label}
            </Link>
          ))}
          {isAdmin && (
            <Link to="/admin" onClick={onClose} className="flex items-center gap-3 rounded-xl px-3 py-3 text-sm font-medium text-ink-700 transition-colors hover:bg-ink-100">
              <LayoutDashboard className="h-5 w-5 text-ink-400" />
              Admin Dashboard
            </Link>
          )}
        </nav>

        <div className="mt-6 space-y-2 border-t border-ink-100 pt-5">
          {user ? (
            <Button variant="outline" className="w-full" onClick={async () => { await onLogout(); onClose(); }}>
              <LogOut className="h-4 w-4" />
              Logout
            </Button>
          ) : (
            <Link to="/login" onClick={onClose} className="block">
              <Button variant="outline" className="w-full">
                <LogIn className="h-4 w-4" />
                Login
              </Button>
            </Link>
          )}
          <Link to="/products" onClick={onClose} className="block">
            <Button className="w-full">Explore Products</Button>
          </Link>
        </div>
      </div>
    </div>
  );
}
