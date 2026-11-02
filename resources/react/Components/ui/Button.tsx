import { cx } from '@react/lib/cx';
import type { ButtonHTMLAttributes } from 'react';

const variants = {
    primary: 'bg-ink text-white hover:bg-ink/90',
    secondary: 'bg-surface text-ink border border-line hover:bg-surface-muted',
    ghost: 'text-ink hover:bg-surface-muted',
    danger: 'bg-danger text-white hover:bg-danger/90',
};
const sizes = { sm: 'h-8 px-3 text-sm', md: 'h-10 px-4 text-sm', lg: 'h-12 px-6 text-base' };

interface ButtonProps extends ButtonHTMLAttributes<HTMLButtonElement> {
    variant?: keyof typeof variants;
    size?: keyof typeof sizes;
    loading?: boolean;
    block?: boolean;
}

export default function Button({ variant = 'primary', size = 'md', type = 'button', loading = false, disabled = false, block = false, className, children, ...rest }: ButtonProps) {
    return (
        <button
            type={type}
            disabled={disabled || loading}
            aria-busy={loading}
            className={cx(
                'inline-flex items-center justify-center gap-2 whitespace-nowrap rounded-control font-medium transition active:scale-[0.97] enabled:hover:-translate-y-px',
                'focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-brand-cyan',
                'disabled:cursor-not-allowed disabled:opacity-50',
                variants[variant],
                sizes[size],
                block && 'w-full',
                className,
            )}
            {...rest}
        >
            {loading && <span className="size-4 animate-spin rounded-full border-2 border-current border-t-transparent" />}
            {children}
        </button>
    );
}
