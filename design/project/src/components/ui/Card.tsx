import type { ElementType, ReactNode } from 'react';
import { Link } from 'react-router-dom';

export function Card({ children, className = '', as: As = 'div' }: { children: ReactNode; className?: string; as?: ElementType }) {
  return (
    <As className={`rounded-2xl border border-ink-200/70 bg-white shadow-soft transition-shadow hover:shadow-card ${className}`}>
      {children}
    </As>
  );
}

export function EmptyState({
  icon,
  title,
  description,
  action,
}: {
  icon: ReactNode;
  title: string;
  description: string;
  action?: ReactNode;
}) {
  return (
    <div className="flex flex-col items-center justify-center rounded-2xl border border-dashed border-ink-200 bg-white/60 px-6 py-16 text-center">
      <div className="mb-4 flex h-14 w-14 items-center justify-center rounded-2xl bg-ink-100 text-ink-400">
        {icon}
      </div>
      <h3 className="text-base font-semibold text-ink-800">{title}</h3>
      <p className="mt-1 max-w-sm text-sm text-ink-500">{description}</p>
      {action && <div className="mt-5">{action}</div>}
    </div>
  );
}

export function Skeleton({ className = '' }: { className?: string }) {
  return <div className={`skeleton ${className}`} />;
}

export function Breadcrumb({ items }: { items: { label: string; to?: string }[] }) {
  return (
    <nav aria-label="Breadcrumb" className="flex items-center gap-1.5 text-sm text-ink-400">
      {items.map((item, i) => (
        <span key={i} className="flex items-center gap-1.5">
          {item.to ? (
            <Link to={item.to} className="transition-colors hover:text-brand-600">
              {item.label}
            </Link>
          ) : (
            <span className="font-medium text-ink-700">{item.label}</span>
          )}
          {i < items.length - 1 && <span className="text-ink-300">/</span>}
        </span>
      ))}
    </nav>
  );
}
