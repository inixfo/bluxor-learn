import { useEffect, useState } from 'react';
import { Link, NavLink, useNavigate } from 'react-router-dom';
import { ShieldCheck, Search, Menu, User, LayoutDashboard, BookOpen, Package, LogOut, ChevronDown } from 'lucide-react';
import { Button } from '@/components/ui/Button';
import { SearchOverlay } from '@/components/storefront/SearchOverlay';
import { MobileDrawer } from '@/components/storefront/MobileDrawer';
import { useAuth } from '@/services/auth-context';

const navItems = [
  { label: 'Explore', to: '/products' },
  { label: 'Categories', to: '/products?view=categories' },
  { label: 'Bundles', to: '/products?filter=bundle' },
  { label: 'New Releases', to: '/products?sort=newest' },
];

export function Navbar() {
  const [scrolled, setScrolled] = useState(false);
  const [searchOpen, setSearchOpen] = useState(false);
  const [drawerOpen, setDrawerOpen] = useState(false);
  const [avatarOpen, setAvatarOpen] = useState(false);
  const { user, initializing, isAdmin, logout } = useAuth();
  const navigate = useNavigate();

  useEffect(() => {
    const onScroll = () => setScrolled(window.scrollY > 8);
    window.addEventListener('scroll', onScroll);
    return () => window.removeEventListener('scroll', onScroll);
  }, []);

  return (
    <>
      <header
        className={`sticky top-0 z-40 transition-all duration-300 ${
          scrolled ? 'glass border-b border-ink-100 shadow-sm' : 'bg-transparent'
        }`}
      >
        <div className="container-page flex h-16 items-center justify-between gap-4">
          {/* Left: logo */}
          <Link to="/" className="flex shrink-0 items-center gap-2.5">
            <div className="flex h-9 w-9 items-center justify-center rounded-xl bg-gradient-to-br from-brand-600 to-brand-700 text-white shadow-sm shadow-brand-600/30">
              <ShieldCheck className="h-5 w-5" />
            </div>
            <div className="hidden sm:block">
              <p className="text-sm font-bold leading-tight text-ink-900">Learn by Bluxor</p>
              <p className="text-[10px] font-medium leading-tight text-ink-400">by Bluxor</p>
            </div>
          </Link>

          {/* Center: nav */}
          <nav className="hidden items-center gap-1 lg:flex">
            {navItems.map((item) => (
              <NavLink
                key={item.label}
                to={item.to}
                className={({ isActive }) =>
                  `rounded-lg px-3.5 py-2 text-sm font-medium transition-colors ${
                    isActive ? 'text-brand-700' : 'text-ink-600 hover:bg-ink-100 hover:text-ink-900'
                  }`
                }
              >
                {item.label}
              </NavLink>
            ))}
          </nav>

          {/* Right */}
          <div className="flex items-center gap-2">
            <button
              onClick={() => setSearchOpen(true)}
              className="flex h-10 items-center gap-2 rounded-xl border border-ink-200 bg-white px-3 text-sm text-ink-400 transition-colors hover:border-brand-300 hover:text-brand-600"
              aria-label="Search"
            >
              <Search className="h-4 w-4" />
              <span className="hidden md:inline">Search products...</span>
              <kbd className="hidden rounded border border-ink-200 bg-ink-50 px-1.5 text-[10px] font-medium text-ink-400 md:inline">⌘K</kbd>
            </button>

            {initializing ? (
              <div className="hidden h-10 w-24 rounded-xl border border-ink-200 bg-white sm:block" />
            ) : user ? (
              <div className="relative hidden sm:block">
                <button
                  onClick={() => setAvatarOpen((v) => !v)}
                  className="flex h-10 items-center gap-1.5 rounded-xl border border-ink-200 bg-white pl-1 pr-2 transition-colors hover:border-ink-300"
                >
                  <div className="flex h-8 w-8 items-center justify-center rounded-lg bg-gradient-to-br from-brand-500 to-violet-500 text-sm font-bold text-white">
                    {user.name.charAt(0).toUpperCase()}
                  </div>
                  <ChevronDown className="h-4 w-4 text-ink-400" />
                </button>
                {avatarOpen && (
                  <>
                    <div className="fixed inset-0 z-10" onClick={() => setAvatarOpen(false)} />
                    <div className="absolute right-0 top-12 z-20 w-56 animate-scale-in rounded-xl border border-ink-100 bg-white p-1.5 shadow-lift">
                      <div className="border-b border-ink-100 px-3 py-2">
                        <p className="text-sm font-semibold text-ink-900">{user.name}</p>
                        <p className="text-xs text-ink-400">{user.email}</p>
                      </div>
                      <div className="py-1">
                        <AvatarLink to="/account/library" icon={<BookOpen className="h-4 w-4" />} label="My Library" />
                        <AvatarLink to="/account/orders" icon={<Package className="h-4 w-4" />} label="Orders" />
                        <AvatarLink to="/account" icon={<User className="h-4 w-4" />} label="Profile" />
                        {isAdmin && <AvatarLink to="/admin" icon={<LayoutDashboard className="h-4 w-4" />} label="Admin Dashboard" />}
                      </div>
                      <div className="border-t border-ink-100 pt-1">
                        <button
                          onClick={async () => {
                            await logout();
                            setAvatarOpen(false);
                            navigate('/login');
                          }}
                          className="flex w-full items-center gap-2.5 rounded-lg px-3 py-2 text-sm text-danger-600 transition-colors hover:bg-danger-50"
                        >
                          <LogOut className="h-4 w-4" />
                          Logout
                        </button>
                      </div>
                    </div>
                  </>
                )}
              </div>
            ) : (
              <Link to="/login" className="hidden sm:block">
                <Button variant="outline" size="sm">Login</Button>
              </Link>
            )}

            <Link to="/products" className="hidden sm:block">
              <Button size="sm">Explore Products</Button>
            </Link>

            <button
              onClick={() => setDrawerOpen(true)}
              className="flex h-10 w-10 items-center justify-center rounded-xl border border-ink-200 bg-white text-ink-700 lg:hidden"
              aria-label="Menu"
            >
              <Menu className="h-5 w-5" />
            </button>
          </div>
        </div>
      </header>

      <SearchOverlay open={searchOpen} onClose={() => setSearchOpen(false)} />
      <MobileDrawer open={drawerOpen} onClose={() => setDrawerOpen(false)} user={user} isAdmin={isAdmin} onLogout={logout} />
    </>
  );
}

function AvatarLink({ to, icon, label }: { to: string; icon: React.ReactNode; label: string }) {
  return (
    <Link to={to} className="flex w-full items-center gap-2.5 rounded-lg px-3 py-2 text-sm text-ink-700 transition-colors hover:bg-ink-100">
      {icon}
      {label}
    </Link>
  );
}
