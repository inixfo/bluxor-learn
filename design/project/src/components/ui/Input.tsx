import { forwardRef, type InputHTMLAttributes, type ReactNode, type SelectHTMLAttributes, type TextareaHTMLAttributes } from 'react';

interface InputProps extends InputHTMLAttributes<HTMLInputElement> {
  label?: string;
  hint?: string;
  error?: string;
  leftIcon?: ReactNode;
  rightSlot?: ReactNode;
}

const baseField =
  'w-full rounded-xl border bg-white px-3.5 text-sm text-ink-900 placeholder:text-ink-400 transition-colors focus:border-brand-500 focus:outline-none focus:ring-4 focus:ring-brand-500/10 disabled:bg-ink-50 disabled:cursor-not-allowed';

export const Input = forwardRef<HTMLInputElement, InputProps>(
  ({ label, hint, error, leftIcon, rightSlot, className = '', id, ...props }, ref) => {
    const inputId = id || props.name;
    return (
      <div className="space-y-1.5">
        {label && (
          <label htmlFor={inputId} className="block text-sm font-medium text-ink-700">
            {label}
          </label>
        )}
        <div className="relative">
          {leftIcon && (
            <span className="pointer-events-none absolute left-3 top-1/2 -translate-y-1/2 text-ink-400">
              {leftIcon}
            </span>
          )}
          <input
            ref={ref}
            id={inputId}
            className={`${baseField} ${leftIcon ? 'pl-10' : ''} ${rightSlot ? 'pr-10' : ''} ${
              error ? 'border-danger-300 focus:border-danger-500 focus:ring-danger-500/10' : 'border-ink-200'
            } ${className}`}
            aria-invalid={!!error}
            {...props}
          />
          {rightSlot && (
            <span className="absolute right-2 top-1/2 -translate-y-1/2">{rightSlot}</span>
          )}
        </div>
        {error ? (
          <p className="text-xs text-danger-600">{error}</p>
        ) : hint ? (
          <p className="text-xs text-ink-400">{hint}</p>
        ) : null}
      </div>
    );
  }
);
Input.displayName = 'Input';

interface SelectProps extends SelectHTMLAttributes<HTMLSelectElement> {
  label?: string;
  hint?: string;
  error?: string;
}

export const Select = forwardRef<HTMLSelectElement, SelectProps>(
  ({ label, hint, error, className = '', id, children, ...props }, ref) => {
    const selectId = id || props.name;
    return (
      <div className="space-y-1.5">
        {label && (
          <label htmlFor={selectId} className="block text-sm font-medium text-ink-700">
            {label}
          </label>
        )}
        <select
          ref={ref}
          id={selectId}
          className={`${baseField} appearance-none bg-[url('data:image/svg+xml;utf8,<svg xmlns=%22http://www.w3.org/2000/svg%22 width=%2216%22 height=%2216%22 fill=%22none%22 stroke=%22%23677389%22 stroke-width=%222%22><path d=%22M4 6l4 4 4-4%22/></svg>')] bg-[length:16px] bg-[right_0.75rem_center] bg-no-repeat pr-9 ${
            error ? 'border-danger-300' : 'border-ink-200'
          } ${className}`}
          {...props}
        >
          {children}
        </select>
        {error ? (
          <p className="text-xs text-danger-600">{error}</p>
        ) : hint ? (
          <p className="text-xs text-ink-400">{hint}</p>
        ) : null}
      </div>
    );
  }
);
Select.displayName = 'Select';

interface TextareaProps extends TextareaHTMLAttributes<HTMLTextAreaElement> {
  label?: string;
  hint?: string;
  error?: string;
}

export const Textarea = forwardRef<HTMLTextAreaElement, TextareaProps>(
  ({ label, hint, error, className = '', id, ...props }, ref) => {
    const textareaId = id || props.name;
    return (
      <div className="space-y-1.5">
        {label && (
          <label htmlFor={textareaId} className="block text-sm font-medium text-ink-700">
            {label}
          </label>
        )}
        <textarea
          ref={ref}
          id={textareaId}
          className={`${baseField} min-h-[96px] resize-y ${
            error ? 'border-danger-300' : 'border-ink-200'
          } ${className}`}
          {...props}
        />
        {error ? (
          <p className="text-xs text-danger-600">{error}</p>
        ) : hint ? (
          <p className="text-xs text-ink-400">{hint}</p>
        ) : null}
      </div>
    );
  }
);
Textarea.displayName = 'Textarea';
