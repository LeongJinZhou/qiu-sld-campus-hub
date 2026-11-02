import { Html5Qrcode } from 'html5-qrcode';
import { useEffect, useId, useRef, useState } from 'react';

/** Live camera QR scanner; calls onScan with each decoded text. */
export default function QrScanner({ onScan }: { onScan: (value: string) => void }) {
    const id = `qr-${useId().replace(/:/g, '')}`;
    const [error, setError] = useState<string | null>(null);
    const latest = useRef(onScan);
    latest.current = onScan;

    useEffect(() => {
        const scanner = new Html5Qrcode(id);
        let stopped = false;
        scanner
            .start(
                { facingMode: 'environment' },
                {
                    fps: 10,
                    qrbox: (w: number, h: number) => ({ width: Math.max(50, Math.round(Math.min(w, h) * 0.65)), height: Math.max(50, Math.round(Math.min(w, h) * 0.65)) }),
                },
                (text) => latest.current(text),
                () => undefined,
            )
            .catch(() => !stopped && setError('Camera unavailable. Allow camera access and try again.'));
        return () => {
            stopped = true;
            if (scanner.isScanning) void scanner.stop();
        };
    }, [id]);

    return (
        <div className="grid gap-2">
            <div className="camera-frame relative mx-auto aspect-square w-full max-w-sm overflow-hidden rounded-card bg-ink">
                <div id={id} className="absolute inset-0" />
                <div className="pointer-events-none absolute inset-0 grid place-items-center" aria-hidden="true">
                    <div className="aspect-square w-[65%] rounded-2xl shadow-[0_0_0_9999px_rgb(0_0_0/0.35)] outline-2 outline-white/80" style={{ outlineStyle: 'solid' }} />
                </div>
            </div>
            {error && <p className="text-sm text-danger">{error}</p>}
        </div>
    );
}
