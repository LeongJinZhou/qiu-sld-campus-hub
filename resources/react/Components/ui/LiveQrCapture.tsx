import { Html5Qrcode } from 'html5-qrcode';
import { useEffect, useId, useRef, useState } from 'react';
import Button from './Button';

type State = 'starting' | 'looking' | 'steady' | 'wrong' | 'captured' | 'error';
const tokenOf = (text: string): string | null => text.match(/\/p\/([A-Za-z0-9_-]+)/)?.[1] ?? null;

interface LiveQrCaptureProps {
    expectToken: string;
    personName: string;
    onCapture: (photo: Blob) => void;
}

/**
 * Live camera that captures a photo by itself once it sees the expected person's profile QR code (…/p/{token})
 * steadily in view. Someone else's QR is ignored with a warning. "Capture now" is a manual fallback.
 */
export default function LiveQrCapture({ expectToken, personName, onCapture }: LiveQrCaptureProps) {
    const id = `live-qr-${useId().replace(/:/g, '')}`;
    const [state, setState] = useState<State>('starting');
    const [attempt, setAttempt] = useState(0);
    const active = useRef(false);
    const capturing = useRef(false);
    const stateRef = useRef<State>('starting');
    const scanner = useRef<Html5Qrcode | null>(null);
    const hits = useRef({ count: 0, last: 0 });
    const capture = useRef(onCapture);
    capture.current = onCapture;
    const set = (s: State): void => {
        stateRef.current = s;
        setState(s);
    };
    const stop = async (): Promise<void> => {
        if (scanner.current?.isScanning) await scanner.current.stop().catch(() => undefined);
    };

    const grabFrame = (): void => {
        if (!active.current || capturing.current || stateRef.current === 'captured') return;
        const video = document.getElementById(id)?.querySelector('video');
        if (!video || !video.videoWidth || !video.videoHeight) return;
        const canvas = document.createElement('canvas');
        canvas.width = video.videoWidth;
        canvas.height = video.videoHeight;
        const context = canvas.getContext('2d');
        if (!context) return;
        context.drawImage(video, 0, 0);
        capturing.current = true;
        canvas.toBlob(
            (blob) => {
                capturing.current = false;
                if (!active.current) return;
                if (!blob) {
                    set('error');
                    return;
                }
                set('captured');
                void stop();
                capture.current(blob);
            },
            'image/jpeg',
            0.92,
        );
    };

    useEffect(() => {
        let disposed = false;
        active.current = true;
        capturing.current = false;
        hits.current = { count: 0, last: 0 };
        set('starting');
        const onScan = (text: string): void => {
            if (disposed || capturing.current || stateRef.current === 'captured') return;
            if (tokenOf(text) !== expectToken) {
                set('wrong');
                hits.current.count = 0;
                return;
            }
            // Two matching reads close together = the QR is held steady, so the photo will be sharp.
            const now = Date.now();
            hits.current.count = now - hits.current.last < 1500 ? hits.current.count + 1 : 1;
            hits.current.last = now;
            set('steady');
            if (hits.current.count >= 2) grabFrame();
        };
        const current = new Html5Qrcode(id, { verbose: false });
        scanner.current = current;
        current
            .start(
                { facingMode: 'environment' },
                {
                    fps: 8,
                    // Scan the middle of the picture, but photograph the whole frame (QR and cash together).
                    qrbox: (w: number, h: number) => ({ width: Math.max(50, Math.round(Math.min(w, h) * 0.6)), height: Math.max(50, Math.round(Math.min(w, h) * 0.6)) }),
                    videoConstraints: { facingMode: 'environment', width: { ideal: 1920 }, height: { ideal: 1080 } },
                },
                onScan,
                () => undefined,
            )
            .then(async () => {
                if (disposed) {
                    if (current.isScanning) await current.stop().catch(() => undefined);
                } else set('looking');
            })
            .catch(() => { if (!disposed) set('error'); });
        return () => {
            disposed = true;
            active.current = false;
            if (current.isScanning) void current.stop().catch(() => undefined);
        };
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, [id, expectToken, attempt]);

    const messages: Record<State, string> = {
        starting: 'Starting the camera…',
        looking: `Point the camera at ${personName}'s phone, with the cash in the same picture.`,
        steady: 'QR found — hold still…',
        wrong: `That QR code belongs to someone else. Ask ${personName} to show their own QR.`,
        captured: 'Photo captured — checking…',
        error: 'Camera unavailable. Allow camera access in your browser, then try again.',
    };
    const outline = state === 'steady' ? 'outline-4 outline-success' : state === 'wrong' ? 'outline-4 outline-danger' : 'outline-2 outline-white/80';
    const bar = state === 'wrong' || state === 'error' ? 'bg-danger/90' : state === 'steady' ? 'bg-success/90' : 'bg-black/60';

    return (
        <div className="grid gap-3">
            {/* Fixed-shape frame: the video fills it (cropped, never stretched) and our guide square sits over the area the scanner reads. */}
            <div className="camera-frame relative mx-auto aspect-[3/4] w-full max-w-md overflow-hidden rounded-card bg-ink sm:aspect-[4/3]">
                <div id={id} className="absolute inset-0" />
                <div className="pointer-events-none absolute inset-0 grid place-items-center" aria-hidden="true">
                    <div
                        className={`relative aspect-square w-[60%] max-w-[60%] rounded-2xl shadow-[0_0_0_9999px_rgb(0_0_0/0.35)] transition-colors duration-300 sm:h-[60%] sm:w-auto ${outline}`}
                        style={{ outlineStyle: 'solid', outlineOffset: 0 }}
                    >
                        {state === 'looking' && <span className="absolute inset-x-3 top-1/2 h-0.5 -translate-y-1/2 animate-pulse rounded bg-brand-cyan/80" />}
                    </div>
                </div>
                <span className={`pointer-events-none absolute inset-x-3 bottom-3 rounded-control px-3 py-2 text-center text-sm font-medium text-white backdrop-blur ${bar}`}>{messages[state]}</span>
            </div>
            {state === 'error' && <Button variant="secondary" onClick={() => setAttempt((value) => value + 1)}>Try camera again</Button>}
            {(state === 'looking' || state === 'steady' || state === 'wrong') && (
                <Button variant="secondary" onClick={grabFrame}>
                    Capture now
                </Button>
            )}
        </div>
    );
}
