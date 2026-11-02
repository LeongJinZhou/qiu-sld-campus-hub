import { useEffect, useState, type CSSProperties } from 'react';
import styles from './Celebration.module.css';

const colours = ['#FDDA1A', '#1CBDFD', '#E01CEB', '#FD8F1A', '#22c55e'];
const pieces = Array.from({ length: 36 }, (_, i) => ({
    left: `${(i * 37) % 100}%`,
    delay: `${(i % 9) * 0.06}s`,
    duration: `${1.6 + (i % 5) * 0.25}s`,
    colour: colours[i % colours.length],
    rotate: `${(i * 47) % 360}deg`,
}));

/** "Approved!" — a check that draws itself and a burst of confetti, shown once for a moment worth marking. */
export default function Celebration({ text, detail }: { text?: string | null; detail?: string | null }) {
    const [open, setOpen] = useState(false);
    useEffect(() => {
        if (!text) return;
        setOpen(true);
        const timer = setTimeout(() => setOpen(false), 4200);
        return () => clearTimeout(timer);
    }, [text]);
    if (!open) return null;

    return (
        <div className="fade-up fixed inset-0 z-[60] grid place-items-center bg-ink/40 p-6 backdrop-blur-sm" role="status" aria-live="polite" onClick={() => setOpen(false)}>
            <div className="pointer-events-none absolute inset-0 overflow-hidden" aria-hidden="true">
                {pieces.map((p, i) => (
                    <span
                        key={i}
                        className={styles.confetti}
                        style={{ left: p.left, animationDelay: p.delay, animationDuration: p.duration, background: p.colour, '--r': p.rotate } as CSSProperties}
                    />
                ))}
            </div>
            <div className={`${styles.pop} relative grid justify-items-center gap-3 rounded-card bg-surface px-10 py-8 text-center shadow-pop`}>
                <svg viewBox="0 0 52 52" className="size-20" aria-hidden="true">
                    <circle cx="26" cy="26" r="24" fill="none" stroke="currentColor" strokeWidth="3" className={`${styles.ring} text-success`} />
                    <path d="M15 27l7 7 15-15" fill="none" stroke="currentColor" strokeWidth="4" strokeLinecap="round" strokeLinejoin="round" className={`${styles.tick} text-success`} />
                </svg>
                <p className="text-xl font-bold">{text}</p>
                <p className="text-sm text-ink-muted">{detail ?? 'Every signature is in. Well done, everyone.'}</p>
            </div>
        </div>
    );
}
