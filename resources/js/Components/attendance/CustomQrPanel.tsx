import Button from '@/Components/ui/Button';
import Icon from '@/Components/ui/Icon';
import { router } from '@inertiajs/react';
import { useEffect, useRef, useState } from 'react';

export interface CustomQr {
    in: string | null;
    out: string | null;
}

const LABELS = { in: 'Check-in QR', out: 'Check-out QR' } as const;
type Action = keyof typeof LABELS;

/**
 * The committee's own check-in and check-out QR images (for example a Google Form): upload, replace or remove each,
 * and show either one full screen for participants to scan.
 */
export default function CustomQrPanel({ qr, uploadUrl, title }: { qr: CustomQr; uploadUrl: string; title: string }) {
    const [showing, setShowing] = useState<Action | null>(null);
    const [busy, setBusy] = useState<Action | null>(null);
    const inputs = { in: useRef<HTMLInputElement>(null), out: useRef<HTMLInputElement>(null) };

    const send = (action: Action, data: Record<string, File | boolean>): void => {
        setBusy(action);
        router.post(uploadUrl, data, { forceFormData: true, preserveScroll: true, onFinish: () => setBusy(null) });
    };

    return (
        <section className="grid gap-3 rounded-card border border-line bg-surface p-5 shadow-card">
            <header className="grid gap-1">
                <h2 className="font-semibold">Your own QR codes</h2>
                <p className="text-xs text-ink-muted">Optional. Upload your own check-in and check-out QR images (for example, a Google Form) and show them full screen for participants to scan.</p>
            </header>
            <div className="grid grid-cols-2 gap-3">
                {(Object.keys(LABELS) as Action[]).map((action) => (
                    <div key={action} className="grid content-start gap-2">
                        <p className="text-xs font-semibold text-ink-muted">{LABELS[action]}</p>
                        {qr[action] ? (
                            <button type="button" className="group relative aspect-square overflow-hidden rounded-control border border-line bg-white" onClick={() => setShowing(action)} aria-label={`Show ${LABELS[action]} full screen`}>
                                <img src={qr[action] ?? ''} alt={LABELS[action]} className="size-full object-contain p-2" />
                                <span className="absolute inset-0 grid place-items-center bg-ink/0 text-white opacity-0 transition group-hover:bg-ink/40 group-hover:opacity-100">
                                    <Icon name="expand" size={22} />
                                </span>
                            </button>
                        ) : (
                            <button
                                type="button"
                                className="grid aspect-square place-items-center rounded-control border-2 border-dashed border-line text-xs text-ink-muted transition hover:border-primary hover:text-primary"
                                onClick={() => inputs[action].current?.click()}
                            >
                                <span className="grid justify-items-center gap-1">
                                    <Icon name="upload" size={20} />
                                    Upload image
                                </span>
                            </button>
                        )}
                        <input
                            ref={inputs[action]}
                            type="file"
                            accept="image/png,image/jpeg,image/webp"
                            className="hidden"
                            onChange={(e) => {
                                const file = e.target.files?.[0];
                                if (file) send(action, { [action]: file });
                                e.target.value = '';
                            }}
                        />
                        {qr[action] && (
                            <div className="flex flex-wrap gap-1">
                                <Button size="sm" variant="secondary" loading={busy === action} onClick={() => inputs[action].current?.click()}>
                                    Replace
                                </Button>
                                <Button size="sm" variant="ghost" onClick={() => send(action, { [`remove_${action}`]: true })}>
                                    Remove
                                </Button>
                            </div>
                        )}
                    </div>
                ))}
            </div>
            {showing && <QrFullscreen qr={qr} start={showing} title={title} onClose={() => setShowing(null)} />}
        </section>
    );
}

/** A full-screen view of an uploaded QR for participants to scan, switchable between check-in and check-out. */
function QrFullscreen({ qr, start, title, onClose }: { qr: CustomQr; start: Action; title: string; onClose: () => void }) {
    const [action, setAction] = useState<Action>(start);
    const box = useRef<HTMLDivElement>(null);
    useEffect(() => {
        box.current?.requestFullscreen?.().catch(() => undefined);
        const escape = (e: KeyboardEvent): void => {
            if (e.key === 'Escape') onClose();
        };
        const left = (): void => {
            if (!document.fullscreenElement) onClose();
        };
        document.addEventListener('keydown', escape);
        document.addEventListener('fullscreenchange', left);
        return () => {
            document.removeEventListener('keydown', escape);
            document.removeEventListener('fullscreenchange', left);
            if (document.fullscreenElement) void document.exitFullscreen().catch(() => undefined);
        };
    }, [onClose]);

    return (
        <div ref={box} className="fixed inset-0 z-50 grid grid-rows-[auto_1fr_auto] gap-4 bg-white p-6 text-ink" role="dialog" aria-modal="true" aria-label={`${LABELS[action]} for ${title}`}>
            <header className="flex items-center justify-between gap-3">
                <div>
                    <p className="text-sm font-medium text-neutral-500">{title}</p>
                    <p className={`text-2xl font-bold ${action === 'in' ? 'text-success' : 'text-neutral-900'}`}>Scan to {action === 'in' ? 'check in' : 'check out'}</p>
                </div>
                <button type="button" className="grid size-11 place-items-center rounded-full bg-neutral-100 text-neutral-900 hover:bg-neutral-200" aria-label="Close" onClick={onClose}>
                    <Icon name="x" size={22} />
                </button>
            </header>
            <div className="grid min-h-0 place-items-center">
                {qr[action] ? (
                    <img src={qr[action] ?? ''} alt={LABELS[action]} className="max-h-full max-w-full object-contain" />
                ) : (
                    <p className="text-neutral-500">No {LABELS[action].toLowerCase()} uploaded.</p>
                )}
            </div>
            <div className="mx-auto grid w-full max-w-sm grid-cols-2 gap-1 rounded-full bg-neutral-100 p-1">
                {(Object.keys(LABELS) as Action[]).map((a) => (
                    <button key={a} type="button" className={`rounded-full py-2.5 text-sm font-semibold ${action === a ? 'bg-white shadow' : 'text-neutral-500'}`} onClick={() => setAction(a)} disabled={!qr[a]}>
                        {a === 'in' ? 'Check in' : 'Check out'}
                    </button>
                ))}
            </div>
        </div>
    );
}
