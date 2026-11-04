import { useCallback, useEffect, useRef, useState, type PointerEvent as ReactPointerEvent } from 'react';
import Icon from './Icon';

type Point = [number, number, number, number];
const INK = '#14213d';

interface SignaturePadProps {
    /** PNG data URL of the signature, or null when empty. */
    value: string | null;
    onChange: (png: string | null, strokes: string | null) => void;
    label?: string;
    disabled?: boolean;
    height?: number;
    signer?: string;
}

const width = (p: Point): number => 1.2 + 2.6 * (p[3] || 0.5);

function drawStroke(target: CanvasRenderingContext2D, stroke: Point[], dx = 0, dy = 0): void {
    target.strokeStyle = INK;
    target.fillStyle = INK;
    target.lineCap = 'round';
    target.lineJoin = 'round';
    const [first] = stroke;
    if (!first) return;
    if (stroke.length === 1) {
        target.beginPath();
        target.arc(first[0] - dx, first[1] - dy, width(first) / 2, 0, Math.PI * 2);
        target.fill();
        return;
    }
    // Quadratic curves through midpoints give smooth ink instead of jagged segments.
    for (let i = 1; i < stroke.length; i++) {
        const a = stroke[i - 1]!;
        const b = stroke[i]!;
        const before = stroke[i - 2] ?? a;
        target.beginPath();
        target.lineWidth = width(b);
        target.moveTo((before[0] + a[0]) / 2 - dx, (before[1] + a[1]) / 2 - dy);
        target.quadraticCurveTo(a[0] - dx, a[1] - dy, (a[0] + b[0]) / 2 - dx, (a[1] + b[1]) / 2 - dy);
        target.stroke();
    }
}

/**
 * E-signature pad. Records every stroke from mouse, finger or stylus (x, y, time, pressure) and exports a
 * tightly cropped transparent PNG for the printed forms. The recording ({w, h, device, strokes}) is sent with
 * the PNG as evidence.
 */
export default function SignaturePad({ value, onChange, label, disabled = false, height = 180, signer }: SignaturePadProps) {
    const wrapper = useRef<HTMLDivElement>(null);
    const canvas = useRef<HTMLCanvasElement>(null);
    const ctx = useRef<CanvasRenderingContext2D | null>(null);
    const strokes = useRef<Point[][]>([]);
    const current = useRef<Point[] | null>(null);
    const started = useRef(0);
    const [count, setCount] = useState(0);
    const [device, setDevice] = useState<'mouse' | 'touch' | 'pen'>('mouse');

    const redraw = useCallback((): void => {
        const el = canvas.current;
        if (!ctx.current || !el) return;
        ctx.current.clearRect(0, 0, el.width, el.height);
        for (const stroke of strokes.current) drawStroke(ctx.current, stroke);
    }, []);

    // Sizes the canvas to its box (and device pixel ratio) whenever it changes, including when a hidden dialog
    // opens, then redraws what was already signed.
    useEffect(() => {
        const resize = (): void => {
            const el = canvas.current;
            if (!el || el.clientWidth === 0) return;
            const ratio = window.devicePixelRatio || 1;
            el.width = Math.round(el.clientWidth * ratio);
            el.height = Math.round(el.clientHeight * ratio);
            ctx.current = el.getContext('2d');
            ctx.current?.setTransform(ratio, 0, 0, ratio, 0, 0);
            redraw();
        };
        const observer = new ResizeObserver(resize);
        if (wrapper.current) observer.observe(wrapper.current);
        resize();
        return () => observer.disconnect();
    }, [redraw]);

    const point = (event: PointerEvent): Point => {
        const rect = canvas.current!.getBoundingClientRect();
        const pressure = event.pointerType === 'mouse' ? 0.5 : event.pressure || 0.5;
        return [
            Math.round((event.clientX - rect.left) * 10) / 10,
            Math.round((event.clientY - rect.top) * 10) / 10,
            Math.round(performance.now() - started.current),
            Math.round(pressure * 100) / 100,
        ];
    };

    const emit = (kind: string = device): void => {
        const el = canvas.current;
        const all = strokes.current.flat();
        setCount(strokes.current.length);
        if (!el || all.length === 0) {
            onChange(null, null);
            return;
        }
        // Crop to the ink with a small margin, on a transparent background, at 2x for crisp printing.
        const pad = 8;
        const xs = all.map((p) => p[0]);
        const ys = all.map((p) => p[1]);
        const minX = Math.max(0, Math.min(...xs) - pad);
        const minY = Math.max(0, Math.min(...ys) - pad);
        const out = document.createElement('canvas');
        out.width = Math.ceil((Math.max(...xs) + pad - minX) * 2);
        out.height = Math.ceil((Math.max(...ys) + pad - minY) * 2);
        const octx = out.getContext('2d');
        if (!octx) return;
        octx.scale(2, 2);
        for (const stroke of strokes.current) drawStroke(octx, stroke, minX, minY);
        onChange(out.toDataURL('image/png'), JSON.stringify({ w: el.clientWidth, h: el.clientHeight, device: kind, strokes: strokes.current }));
    };

    const down = (event: ReactPointerEvent<HTMLCanvasElement>): void => {
        event.preventDefault();
        if (!started.current) started.current = performance.now();
        const kind = event.pointerType === 'touch' ? 'touch' : event.pointerType === 'pen' ? 'pen' : 'mouse';
        setDevice(kind);
        canvas.current?.setPointerCapture(event.pointerId);
        current.current = [point(event.nativeEvent)];
        strokes.current.push(current.current);
        setCount(strokes.current.length);
        redraw();
    };
    const move = (event: ReactPointerEvent<HTMLCanvasElement>): void => {
        if (!current.current) return;
        event.preventDefault();
        // Coalesced events keep fast finger/stylus strokes smooth.
        const native = event.nativeEvent;
        const events = typeof native.getCoalescedEvents === 'function' ? native.getCoalescedEvents() : [native];
        for (const e of events.length ? events : [native]) current.current.push(point(e));
        redraw();
    };
    const up = (): void => {
        if (!current.current) return;
        current.current = null;
        emit();
    };
    const undo = (): void => {
        strokes.current.pop();
        redraw();
        emit();
    };
    const clear = (): void => {
        strokes.current = [];
        started.current = 0;
        redraw();
        emit();
    };

    return (
        <div className="grid gap-2">
            <div className="flex items-center justify-between">
                {label && <span className="text-sm font-medium">{label}</span>}
                {!disabled && (
                    <div className="ml-auto flex gap-1">
                        <button type="button" className="inline-flex items-center gap-1 rounded-control px-2 py-1 text-xs text-ink-muted hover:bg-surface-muted hover:text-ink disabled:opacity-40" disabled={!count} onClick={undo}>
                            ↶ Undo
                        </button>
                        <button type="button" className="inline-flex items-center gap-1 rounded-control px-2 py-1 text-xs text-ink-muted hover:bg-surface-muted hover:text-ink disabled:opacity-40" disabled={!count} onClick={clear}>
                            <Icon name="x" size={14} /> Clear
                        </button>
                    </div>
                )}
            </div>
            <div ref={wrapper} className={`relative overflow-hidden rounded-card border-2 border-dashed bg-white transition ${value ? 'border-primary' : 'border-line'}`} style={{ height: `${height}px` }}>
                {/* Signing guide: baseline with an X, like a paper form. */}
                <div className="pointer-events-none absolute inset-x-6 bottom-10 flex items-end gap-2 text-ink-muted/60" aria-hidden="true">
                    <span className="text-lg leading-none">✕</span>
                    <span className="mb-1 h-px flex-1 bg-ink-muted/40" />
                </div>
                {!count && <p className="pointer-events-none absolute inset-0 grid place-items-center px-6 text-center text-sm text-ink-muted/70">Sign here with your mouse, finger or stylus</p>}
                {signer && <p className="pointer-events-none absolute bottom-2 left-6 text-xs text-ink-muted">{signer}</p>}
                <canvas
                    ref={canvas}
                    className={`absolute inset-0 size-full touch-none select-none ${disabled ? 'pointer-events-none opacity-60' : 'cursor-crosshair'}`}
                    aria-label="Signature pad"
                    onPointerDown={down}
                    onPointerMove={move}
                    onPointerUp={up}
                    onPointerCancel={up}
                    onPointerLeave={up}
                />
            </div>
            <p className="text-xs text-ink-muted">
                {value ? `✓ Signature captured (${device}). The server adds the date, time and a verification code.` : 'Your drawn signature is recorded and printed on the official form.'}
            </p>
        </div>
    );
}
