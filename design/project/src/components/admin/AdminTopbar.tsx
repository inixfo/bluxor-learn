import { Search, Bell, ChevronDown } from 'lucide-react';

export function AdminTopbar() {
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
          <button className="relative flex h-10 w-10 items-center justify-center rounded-xl border border-ink-200 text-ink-500 transition-colors hover:bg-ink-50">
            <Bell className="h-5 w-5" />
            <span className="absolute right-2 top-2 h-2 w-2 rounded-full bg-danger-500" />
          </button>
          <div className="flex items-center gap-2 rounded-xl border border-ink-200 py-1 pl-1 pr-2">
            <div className="flex h-8 w-8 items-center justify-center rounded-lg bg-gradient-to-br from-brand-500 to-violet-500 text-sm font-bold text-white">A</div>
            <div className="hidden sm:block">
              <p className="text-xs font-semibold text-ink-900">Admin</p>
              <p className="text-[10px] text-ink-400">admin@bluxor.com</p>
            </div>
            <ChevronDown className="h-4 w-4 text-ink-400" />
          </div>
        </div>
      </div>
    </header>
  );
}
