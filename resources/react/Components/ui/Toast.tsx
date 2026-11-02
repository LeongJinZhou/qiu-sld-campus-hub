import { usePage } from '@inertiajs/react';
import { useEffect, useState } from 'react';

export default function Toast() {
    const { flash } = usePage().props;
    const [message, setMessage] = useState<{ text: string; tone: 'success' | 'error' } | null>(null);

    useEffect(() => {
        const text = flash.error ?? flash.success;
        if (!text) return;
        setMessage({ text, tone: flash.error ? 'error' : 'success' });
        const timer = setTimeout(() => setMessage(null), 5000);
        return () => clearTimeout(timer);
    }, [flash]);

    return (
        <div className="pointer-events-none fixed inset-x-0 top-4 z-50 flex justify-center px-4" aria-live="polite">
            {message && (
                <p className={`pointer-events-auto rounded-control px-4 py-3 text-sm font-medium text-white shadow-pop ${message.tone === 'error' ? 'bg-danger' : 'bg-ink'}`} role="status">
                    {message.text}
                </p>
            )}
        </div>
    );
}
