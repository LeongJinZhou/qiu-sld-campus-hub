import { forwardRef, useCallback, useEffect, useImperativeHandle, useMemo, useRef, useState, type PointerEvent } from 'react';

export interface ImageAdjusterHandle {
    /** The framed area as a JPEG at outputWidth × outputWidth / aspect. */
    exportBlob: () => Promise<Blob>;
}

interface ImageAdjusterProps {
    file: Blob;
    aspect: number;
    round?: boolean;
    outputWidth: number;
}

/**
 * Drag to move and slide to zoom a photo inside a fixed frame (round for profile photos, wide for covers),
 * then export exactly what is inside the frame as a JPEG. Pure canvas, no library.
 */
const ImageAdjuster = forwardRef<ImageAdjusterHandle, ImageAdjusterProps>(function ImageAdjuster({ file, aspect, round = false, outputWidth }, ref) {
    const frame = useRef<HTMLDivElement>(null);
    const img = useRef<HTMLImageElement>(null);
    const drag = useRef<{ id: number; sx: number; sy: number; ox: number; oy: number } | null>(null);
    const [zoom, setZoom] = useState(1);
    const [pos, setPos] = useState({ x: 0, y: 0 });
    const [natural, setNatural] = useState({ w: 1, h: 1 });
    const [box, setBox] = useState({ w: 1, h: 1 });
    const url = useMemo(() => URL.createObjectURL(file), [file]);

    useEffect(() => {
        setZoom(1);
        setPos({ x: 0, y: 0 });
        return () => URL.revokeObjectURL(url);
    }, [url]);
    useEffect(() => {
        const observer = new ResizeObserver(([entry]) => setBox({ w: entry?.contentRect.width || 1, h: entry?.contentRect.height || 1 }));
        if (frame.current) observer.observe(frame.current);
        return () => observer.disconnect();
    }, []);

    // Size of the photo on screen at the current zoom: always covers the frame.
    const size = useMemo(() => {
        const cover = Math.max(box.w / natural.w, box.h / natural.h) * zoom;
        return { w: natural.w * cover, h: natural.h * cover, fw: box.w, fh: box.h };
    }, [box, natural, zoom]);

    // Keep the photo covering the frame: no empty edges.
    const clamp = useCallback(
        (p: { x: number; y: number }) => {
            const mx = Math.max(0, (size.w - size.fw) / 2);
            const my = Math.max(0, (size.h - size.fh) / 2);
            return { x: Math.min(mx, Math.max(-mx, p.x)), y: Math.min(my, Math.max(-my, p.y)) };
        },
        [size],
    );
    useEffect(() => setPos((p) => clamp(p)), [clamp]);

    const down = (e: PointerEvent<HTMLDivElement>): void => {
        drag.current = { id: e.pointerId, sx: e.clientX, sy: e.clientY, ox: pos.x, oy: pos.y };
        e.currentTarget.setPointerCapture(e.pointerId);
    };
    const move = (e: PointerEvent<HTMLDivElement>): void => {
        const d = drag.current;
        if (!d || d.id !== e.pointerId) return;
        setPos(clamp({ x: d.ox + e.clientX - d.sx, y: d.oy + e.clientY - d.sy }));
    };
    const up = (): void => {
        drag.current = null;
    };
    // Wheel zoom needs a non-passive listener to stop the page scrolling.
    useEffect(() => {
        const el = frame.current;
        if (!el) return;
        const wheel = (e: WheelEvent): void => {
            e.preventDefault();
            setZoom((z) => Math.min(4, Math.max(1, z - e.deltaY * 0.002)));
        };
        el.addEventListener('wheel', wheel, { passive: false });
        return () => el.removeEventListener('wheel', wheel);
    }, []);

    useImperativeHandle(ref, () => ({
        exportBlob: () => {
            const scale = natural.w / size.w; // screen px → source px
            const sx = (size.w - size.fw) / 2 - pos.x;
            const sy = (size.h - size.fh) / 2 - pos.y;
            const canvas = document.createElement('canvas');
            canvas.width = outputWidth;
            canvas.height = Math.round(outputWidth / aspect);
            const ctx = canvas.getContext('2d');
            if (ctx && img.current) {
                ctx.imageSmoothingQuality = 'high';
                ctx.drawImage(img.current, sx * scale, sy * scale, size.fw * scale, size.fh * scale, 0, 0, canvas.width, canvas.height);
            }
            return new Promise((resolve, reject) => canvas.toBlob((b) => (b ? resolve(b) : reject(new Error('export failed'))), 'image/jpeg', 0.9));
        },
    }));

    return (
        <div className="grid gap-3">
            <div
                ref={frame}
                className={`relative w-full touch-none select-none overflow-hidden bg-ink ${round ? 'mx-auto max-w-72 rounded-full' : 'rounded-card'}`}
                style={{ aspectRatio: String(aspect) }}
                onPointerDown={down}
                onPointerMove={move}
                onPointerUp={up}
                onPointerCancel={up}
            >
                <img
                    ref={img}
                    src={url}
                    alt="Photo being adjusted"
                    draggable={false}
                    className="pointer-events-none absolute left-1/2 top-1/2 max-w-none cursor-grab"
                    style={{ width: `${size.w}px`, height: `${size.h}px`, transform: `translate(calc(-50% + ${pos.x}px), calc(-50% + ${pos.y}px))` }}
                    onLoad={() => setNatural({ w: img.current?.naturalWidth || 1, h: img.current?.naturalHeight || 1 })}
                />
                <span className="pointer-events-none absolute inset-x-0 bottom-2 text-center text-xs font-medium text-white drop-shadow">Drag to move</span>
            </div>
            <label className="flex items-center gap-3 text-sm">
                <span className="text-ink-muted">Zoom</span>
                <input value={zoom} onChange={(e) => setZoom(Number(e.target.value))} type="range" min="1" max="4" step="0.01" className="flex-1 accent-primary-strong" aria-label="Zoom" />
            </label>
        </div>
    );
});

export default ImageAdjuster;
