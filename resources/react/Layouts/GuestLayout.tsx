import Toast from '@react/Components/ui/Toast';
import type { ReactNode } from 'react';

export default function GuestLayout({ children }: { children?: ReactNode }) {
    return (
        <div className="relative grid min-h-dvh place-items-center overflow-hidden px-4 py-10">
            <div className="pointer-events-none absolute -left-24 -top-24 size-72 rounded-full bg-brand-yellow/40 blur-3xl" aria-hidden="true" />
            <div className="pointer-events-none absolute -bottom-24 -right-24 size-72 rounded-full bg-brand-magenta/25 blur-3xl" aria-hidden="true" />
            <Toast />
            <main className="relative w-full max-w-sm">{children}</main>
        </div>
    );
}
