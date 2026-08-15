import { useState } from 'react';
import { NavLink, Link } from 'react-router-dom';
import {
  LayoutDashboard, Package, FileCode2, ShoppingCart, Users, Ticket,
  BarChart3, ClipboardList, Settings, LogOut, ShieldCheck, ChevronLeft, Menu, Tags, FileText, MessageSquare, HelpCircle, CircleHelp, FolderDown,
} from 'lucide-react';

const navItems = [
  { to: '/admin', label: 'Dashboard', icon: LayoutDashboard, end: true },
  { to: '/admin/products', label: 'Products', icon: Package },
  { to: '/admin/categories', label: 'Categories', icon: Tags },
  { to: '/admin/resources', label: 'Resources', icon: FolderDown },
  { to: '/admin/landing-pages', label: 'Landing Pages', icon: FileCode2 },
  { to: '/admin/orders', label: 'Orders', icon: ShoppingCart },
  { to: '/admin/customers', label: 'Customers', icon: Users },
  { to: '/admin/contact-inquiries', label: 'Contact Inquiries', icon: MessageSquare },
  { to: '/admin/coupons', label: 'Coupons', icon: Ticket },
  { to: '/admin/analytics', label: 'Analytics', icon: BarChart3 },
  { to: '/admin/audit-logs', label: 'Audit Logs', icon: ClipboardList },
  { to: '/admin/content-pages', label: 'Content Pages', icon: FileText },
  { to: '/admin/help-center', label: 'Help Center', icon: HelpCircle },
  { to: '/admin/faq', label: 'FAQ', icon: CircleHelp },
  { to: '/admin/settings', label: 'Settings', icon: Settings },
];

export function AdminSidebar() {
  const [collapsed, setCollapsed] = useState(false);
  const [mobileOpen, setMobileOpen] = useState(false);

  return (
    <>
      {/* Mobile toggle */}
      <button
        onClick={() => setMobileOpen(true)}
        className="fixed left-4 top-4 z-50 flex h-10 w-10 items-center justify-center rounded-xl bg-ink-900 text-white shadow-lg lg:hidden"
        aria-label="Open menu"
      >
        <Menu className="h-5 w-5" />
      </button>

      {/* Mobile overlay */}
      {mobileOpen && (
        <div className="fixed inset-0 z-50 lg:hidden">
          <div className="absolute inset-0 bg-ink-950/50 backdrop-blur-sm" onClick={() => setMobileOpen(false)} />
          <div className="absolute left-0 top-0 h-full w-64 bg-ink-950 p-4 animate-slide-in-right">
            <SidebarContent collapsed={false} onNavigate={() => setMobileOpen(false)} />
          </div>
        </div>
      )}

      {/* Desktop */}
      <aside className={`fixed left-0 top-0 z-30 hidden h-screen bg-ink-950 transition-all duration-300 lg:block ${collapsed ? 'w-20' : 'w-64'}`}>
        <div className="flex h-full flex-col">
          <div className="flex items-center justify-between p-4">
            <Link to="/admin" className="flex items-center gap-2.5 overflow-hidden">
              <div className="flex h-9 w-9 shrink-0 items-center justify-center rounded-xl bg-gradient-to-br from-brand-500 to-brand-600 text-white">
                <ShieldCheck className="h-5 w-5" />
              </div>
              {!collapsed && (
                <div className="overflow-hidden">
                  <p className="truncate text-sm font-bold text-white">Bluxor Admin</p>
                  <p className="truncate text-[10px] text-ink-400">learn.bluxor.com</p>
                </div>
              )}
            </Link>
            <button onClick={() => setCollapsed((v) => !v)} className="rounded-lg p-1.5 text-ink-400 transition-colors hover:bg-ink-800 hover:text-white">
              <ChevronLeft className={`h-4 w-4 transition-transform ${collapsed ? 'rotate-180' : ''}`} />
            </button>
          </div>

          <SidebarContent collapsed={collapsed} />
        </div>
      </aside>
    </>
  );
}

function SidebarContent({ collapsed, onNavigate }: { collapsed: boolean; onNavigate?: () => void }) {
  return (
    <>
      <nav className="flex-1 space-y-1 px-2">
        {navItems.map((item) => (
          <NavLink
            key={item.to}
            to={item.to}
            end={item.end}
            onClick={onNavigate}
            title={collapsed ? item.label : undefined}
            className={({ isActive }) =>
              `flex items-center gap-3 rounded-xl px-3 py-2.5 text-sm font-medium transition-colors ${
                isActive ? 'bg-brand-600 text-white' : 'text-ink-400 hover:bg-ink-800 hover:text-white'
              } ${collapsed ? 'justify-center' : ''}`
            }
          >
            <item.icon className="h-5 w-5 shrink-0" />
            {!collapsed && <span className="truncate">{item.label}</span>}
          </NavLink>
        ))}
      </nav>
      <div className="border-t border-ink-800 p-2">
        <Link to="/" onClick={onNavigate} className={`flex items-center gap-3 rounded-xl px-3 py-2.5 text-sm font-medium text-ink-400 transition-colors hover:bg-ink-800 hover:text-white ${collapsed ? 'justify-center' : ''}`}>
          <LogOut className="h-5 w-5 shrink-0" />
          {!collapsed && <span>Exit to Site</span>}
        </Link>
      </div>
    </>
  );
}
