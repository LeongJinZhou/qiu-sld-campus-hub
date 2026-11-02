import type { ReactNode } from 'react';

interface CardProps {
    title?: ReactNode;
    actions?: ReactNode;
    padded?: boolean;
    className?: string;
    children?: ReactNode;
}

export default function Card({ title, actions, padded = true, className = '', children }: CardProps) {
    return (
        <section className={`rounded-card border border-line bg-surface shadow-card ${padded ? 'p-5' : ''} ${className}`.trim()}>
            {title && (
                <header className="mb-4 flex items-center justify-between gap-3">
                    <h2 className="text-base font-semibold">{title}</h2>
                    {actions}
                </header>
            )}
            {children}
        </section>
    );
}
