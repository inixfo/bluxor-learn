/* eslint-disable react-refresh/only-export-components */
import { createContext, useCallback, useContext, useState, type ReactNode } from 'react';
import { createPortal } from 'react-dom';
import { CheckCircle2, AlertCircle, Info, XCircle, X } from 'lucide-react';

type ToastType = 'success' | 'error' | 'info' | 'warning';
interface Toast { id: number; type: ToastType; title: string; message?: string }

const ToastCtx = createContext<(t: Omit<Toast, 'id'>) => void>(() => {});
export const useToast = () => useContext(ToastCtx);

const icons = {
  success: CheckCircle2,
  error: XCircle,
  info: Info,
  warning: AlertCircle,
};
const tones = {
  success: 'text-success-600',
  error: 'text-danger-600',
  info: 'text-brand-600',
  warning: 'text-warning-600',
};

export function ToastProvider({ children }: { children: ReactNode }) {
  const [toasts, setToasts] = useState<Toast[]>([]);

  const push = useCallback((t: Omit<Toast, 'id'>) => {
    const id = Date.now() + Math.random();
    setToasts((prev) => [...prev, { ...t, id }]);
    setTimeout(() => setToasts((prev) => prev.filter((x) => x.id !== id)), 4000);
  }, []);

  return (
    <ToastCtx.Provider value={push}>
      {children}
      {createPortal(
        <div className="fixed bottom-4 right-4 z-[100] flex w-full max-w-sm flex-col gap-2">
          {toasts.map((t) => {
            const Icon = icons[t.type];
            return (
              <div
                key={t.id}
                className="animate-slide-in-right flex items-start gap-3 rounded-xl border border-ink-200 bg-white p-4 shadow-lift"
              >
                <Icon className={`h-5 w-5 shrink-0 ${tones[t.type]}`} />
                <div className="flex-1">
                  <p className="text-sm font-semibold text-ink-900">{t.title}</p>
                  {t.message && <p className="mt-0.5 text-xs text-ink-500">{t.message}</p>}
                </div>
                <button
                  onClick={() => setToasts((prev) => prev.filter((x) => x.id !== t.id))}
                  className="text-ink-300 transition-colors hover:text-ink-600"
                >
                  <X className="h-4 w-4" />
                </button>
              </div>
            );
          })}
        </div>,
        document.body
      )}
    </ToastCtx.Provider>
  );
}
