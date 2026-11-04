import Icon from '@/Components/ui/Icon';
import { useEffect, useRef, useState } from 'react';

interface GaugeItem {
    key: string;
    label: string;
    done: boolean;
}

const DURATION = 1200;
const R = 42;
const CIRC = 2 * Math.PI * R;

/**
 * How much of a form's compulsory parts is done: a ring that eases to the new value (and a count that follows it),
 * plus the list of parts, each tappable to jump there. The motion is one continuous ease, never a looping clip.
 */
export default function ProgressGauge({ items, title, onJump }: { items: GaugeItem[]; title?: string; onJump: (key: string) => void }) {
    const done = items.filter((i) => i.done).length;
    const percent = items.length ? Math.round((done / items.length) * 100) : 0;
    const [shown, setShown] = useState(0);
    const shownRef = useRef(0);

    // The number counts towards the target in step with the ring (same duration and easing).
    useEffect(() => {
        let frame = 0;
        const from = shownRef.current;
        const start = performance.now();
        const ease = (t: number): number => 1 - Math.pow(1 - t, 4);
        const step = (now: number): void => {
            const t = Math.min(1, (now - start) / DURATION);
            shownRef.current = from + (percent - from) * ease(t);
            setShown(shownRef.current);
            if (t < 1) frame = requestAnimationFrame(step);
        };
        frame = requestAnimationFrame(step);
        return () => cancelAnimationFrame(frame);
    }, [percent]);

    const complete = percent === 100;
    return (
        <section className="grid gap-4 rounded-card border border-line bg-surface p-5 shadow-card sm:grid-cols-[auto_1fr] sm:items-center" aria-label="Compulsory parts done">
            <div className="relative mx-auto size-28 shrink-0" role="progressbar" aria-valuenow={percent} aria-valuemin={0} aria-valuemax={100}>
                <svg viewBox="0 0 100 100" className="size-full -rotate-90">
                    <defs>
                        <linearGradient id="gauge-fill" x1="0" y1="0" x2="1" y2="1">
                            <stop offset="0%" stopColor="var(--color-accent)" />
                            <stop offset="55%" stopColor="var(--color-primary-strong)" />
                            <stop offset="100%" stopColor="var(--color-primary)" />
                        </linearGradient>
                    </defs>
                    <circle cx="50" cy="50" r={R} fill="none" stroke="var(--color-line)" strokeWidth="9" />
                    <circle
                        cx="50"
                        cy="50"
                        r={R}
                        fill="none"
                        stroke={complete ? 'var(--color-success)' : 'url(#gauge-fill)'}
                        strokeWidth="9"
                        strokeLinecap="round"
                        strokeDasharray={CIRC}
                        strokeDashoffset={CIRC * (1 - shown / 100)}
                    />
                </svg>
                <div className="absolute inset-0 grid place-items-center text-center">
                    <div>
                        <p className={`text-2xl font-bold tabular-nums ${complete ? 'text-success' : ''}`}>{Math.round(shown)}%</p>
                        <p className="text-[11px] text-ink-muted">
                            {done}/{items.length} parts
                        </p>
                    </div>
                </div>
            </div>
            <div className="grid gap-2">
                <p className="text-sm font-semibold">{complete ? 'Every compulsory part is done. Ready to sign and submit.' : (title ?? 'Compulsory parts')}</p>
                <ul className="flex flex-wrap gap-1.5">
                    {items.map((item) => (
                        <li key={item.key}>
                            <button
                                type="button"
                                className={`flex items-center gap-1 rounded-full border px-2.5 py-1 text-xs font-medium transition-colors duration-500 ${item.done ? 'border-success/40 bg-success/10 text-success' : 'border-line bg-surface-muted text-ink-muted hover:border-danger/50 hover:text-danger'}`}
                                onClick={() => onJump(item.key)}
                            >
                                <Icon name={item.done ? 'check' : 'clock'} size={12} strokeWidth={3} />
                                {item.label}
                            </button>
                        </li>
                    ))}
                </ul>
            </div>
        </section>
    );
}
