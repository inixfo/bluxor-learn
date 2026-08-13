import { useEffect, useState } from 'react';
import { Link } from 'react-router-dom';
import { Search, Bell, ChevronDown } from 'lucide-react';
import { getAdminNotifications, getAdminUnreadNotificationCount, markAdminNotificationRead, markAllAdminNotificationsRead, type AdminNotification } from '@/services/api/admin';
import { useAuth } from '@/services/auth-context';

export function AdminTopbar() {
  const { user } = useAuth();
  const [open, setOpen] = useState(false);
  const [count, setCount] = useState(0);
  const [notifications, setNotifications] = useState<AdminNotification[]>([]);

  const refreshCount = () => getAdminUnreadNotificationCount().then(setCount).catch(() => undefined);
  const refreshList = () => getAdminNotifications().then(setNotifications).catch(() => undefined);

  useEffect(() => {
    refreshCount();
    const interval = window.setInterval(refreshCount, 45000);
    const focus = () => refreshCount();
    window.addEventListener('focus', focus);
    return () => {
      window.clearInterval(interval);
      window.removeEventListener('focus', focus);
    };
  }, []);

  const toggle = () => {
    const next = !open;
    setOpen(next);
    if (next) refreshList();
  };

  return (
    <header className="sticky top-0 z-20 border-b border-ink-100 bg-white/80 px-4 py-3 backdrop-blur sm:px-6 lg:px-8">
      <div className="flex items-center justify-between gap-4">
        <div className="hidden flex-1 sm:block">
          <div className="relative max-w-xs">
            <Search className="absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-ink-400" />
            <input
              placeholder="Search..."
              className="h-9 w-full rounded-xl border border-ink-200 bg-ink-50 pl-9 pr-3 text-sm text-ink-700 placeholder:text-ink-400 focus:border-brand-500 focus:bg-white focus:outline-none"
            />
          </div>
        </div>
        <div className="flex items-center gap-3">
          <button onClick={toggle} className="relative flex h-10 w-10 items-center justify-center rounded-xl border border-ink-200 text-ink-500 transition-colors hover:bg-ink-50">
            <Bell className="h-5 w-5" />
            {count > 0 && <span className="absolute right-1 top-1 min-w-4 rounded-full bg-danger-500 px-1 text-[10px] font-bold text-white">{count}</span>}
          </button>
          {open && (
            <div className="absolute right-4 top-16 z-30 w-80 overflow-hidden rounded-xl border border-ink-100 bg-white shadow-xl">
              <div className="flex items-center justify-between border-b border-ink-100 px-4 py-3">
                <p className="text-sm font-bold text-ink-900">Notifications</p>
                <button className="text-xs font-medium text-brand-600" onClick={() => markAllAdminNotificationsRead().then(() => { setCount(0); refreshList(); })}>Mark all read</button>
              </div>
              <div className="max-h-96 overflow-y-auto">
                {notifications.length === 0 ? <p className="px-4 py-6 text-center text-sm text-ink-400">No notifications yet.</p> : notifications.map((notification) => (
                  <Link
                    key={notification.id}
                    to={notification.url || '/admin'}
                    onClick={() => markAdminNotificationRead(notification.id).then(() => refreshCount()).catch(() => undefined)}
                    className={`block border-b border-ink-50 px-4 py-3 hover:bg-ink-50 ${notification.read_at ? '' : 'bg-brand-50/40'}`}
                  >
                    <p className="text-sm font-semibold text-ink-900">{notification.title}</p>
                    <p className="mt-0.5 line-clamp-2 text-xs text-ink-500">{notification.message}</p>
                    <p className="mt-1 text-[10px] text-ink-400">{new Date(notification.created_at).toLocaleString()}</p>
                  </Link>
                ))}
              </div>
            </div>
          )}
          <div className="flex items-center gap-2 rounded-xl border border-ink-200 py-1 pl-1 pr-2">
            <div className="flex h-8 w-8 items-center justify-center rounded-lg bg-gradient-to-br from-brand-500 to-violet-500 text-sm font-bold text-white">A</div>
            <div className="hidden sm:block">
              <p className="text-xs font-semibold text-ink-900">{user?.name || 'Admin'}</p>
              <p className="text-[10px] text-ink-400">{user?.email || 'admin@bluxor.com'}</p>
            </div>
            <ChevronDown className="h-4 w-4 text-ink-400" />
          </div>
        </div>
      </div>
    </header>
  );
}
