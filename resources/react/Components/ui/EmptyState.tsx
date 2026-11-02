import type { ReactNode } from 'react';

export default function EmptyState({ title, description, children }: { title: string; description?: string; children?: ReactNode }) {
    return (
        <div className="grid justify-items-center gap-2 rounded-card border border-dashed border-line px-6 py-12 text-center">
            <p className="font-semibold">{title}</p>
            {description && <p className="max-w-sm text-sm text-ink-muted">{description}</p>}
            {children && <div className="mt-2">{children}</div>}
        </div>
    );
}
