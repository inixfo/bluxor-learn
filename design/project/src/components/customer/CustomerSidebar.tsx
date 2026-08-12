import { NavLink, Link, useNavigate } from 'react-router-dom';
import { LayoutGrid, BookOpen, Package, Download, User, LogOut, ShieldCheck } from 'lucide-react';
import { logout } from '@/services/api/auth';

const navItems = [
  { to: '/account', label: 'Overview', icon: LayoutGrid, end: true },
  { to: '/account/library', label: 'My Library', icon: BookOpen },
  { to: '/account/orders', label: 'Orders', icon: Package },
  { to: '/account/downloads', label: 'Downloads', icon: Download },
  { to: '/account/profile', label: 'Profile', icon: User },
];

export function CustomerSidebar() {
  const navigate = useNavigate();

  const handleLogout = async () => {
    await logout().catch(() => undefined);
    navigate('/login');
  };

  return (
    <aside className="hidden w-60 shrink-0 lg:block">
      <div className="sticky top-24">
        <Link to="/" className="mb-4 flex items-center gap-2.5">
          <div className="flex h-9 w-9 items-center justify-center rounded-xl bg-gradient-to-br from-brand-600 to-brand-700 text-white">
            <ShieldCheck className="h-5 w-5" />
          </div>
          <span className="text-sm font-bold text-ink-900">My Account</span>
        </Link>
        <nav className="space-y-1">
          {navItems.map((item) => (
            <NavLink
              key={item.to}
              to={item.to}
              end={item.end}
              className={({ isActive }) =>
                `flex items-center gap-3 rounded-xl px-3 py-2.5 text-sm font-medium transition-colors ${
                  isActive ? 'bg-brand-50 text-brand-700' : 'text-ink-600 hover:bg-ink-100'
                }`
              }
            >
              <item.icon className="h-5 w-5" />
              {item.label}
            </NavLink>
          ))}
          <button onClick={handleLogout} className="flex w-full items-center gap-3 rounded-xl px-3 py-2.5 text-sm font-medium text-danger-600 transition-colors hover:bg-danger-50">
            <LogOut className="h-5 w-5" />
            Logout
          </button>
        </nav>
      </div>
    </aside>
  );
}

export function CustomerMobileNav() {
  return (
    <div className="-mx-4 mb-6 overflow-x-auto px-4 lg:hidden no-scrollbar">
      <div className="flex gap-2">
        {navItems.map((item) => (
          <NavLink
            key={item.to}
            to={item.to}
            end={item.end}
            className={({ isActive }) =>
              `flex shrink-0 items-center gap-2 rounded-full px-4 py-2 text-sm font-medium transition-colors ${
                isActive ? 'bg-brand-600 text-white' : 'bg-white text-ink-600 border border-ink-200'
              }`
            }
          >
            <item.icon className="h-4 w-4" />
            {item.label}
          </NavLink>
        ))}
      </div>
    </div>
  );
}
