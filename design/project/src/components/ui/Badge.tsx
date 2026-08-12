import type { ReactNode } from 'react';

export type Tone = 'brand' | 'success' | 'warning' | 'danger' | 'neutral' | 'violet';

const tones: Record<Tone, string> = {
  brand: 'bg-brand-50 text-brand-700 ring-brand-200',
  success: 'bg-success-50 text-success-700 ring-success-200',
  warning: 'bg-warning-50 text-warning-700 ring-warning-200',
  danger: 'bg-danger-50 text-danger-700 ring-danger-200',
  neutral: 'bg-ink-100 text-ink-600 ring-ink-200',
  violet: 'bg-violet-50 text-violet-700 ring-violet-200',
};

export function Badge({
  children,
  tone = 'neutral',
  className = '',
}: {
  children: ReactNode;
  tone?: Tone;
  className?: string;
}) {
  return (
    <span
      className={`inline-flex items-center gap-1 rounded-full px-2.5 py-0.5 text-xs font-semibold ring-1 ring-inset ${tones[tone]} ${className}`}
    >
      {children}
    </span>
  );
}

export function SaleBadge({ discount }: { discount: number }) {
  return (
    <span className="inline-flex items-center gap-1 rounded-full bg-danger-600 px-2.5 py-0.5 text-xs font-bold text-white shadow-sm shadow-danger-600/30">
      -{discount}%
    </span>
  );
}
