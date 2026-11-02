import { TOURS, type TourName, type TourStep } from '@/lib/tours';
import { useWindowEvent } from '@react/hooks/useWindowEvent';
import { router, usePage } from '@inertiajs/react';
import { useCallback, useEffect, useMemo, useState, type CSSProperties } from 'react';
import { createPortal } from 'react-dom';
import styles from './GuidedTour.module.css';

/** The visible element for a step (desktop and phone navigation both carry the same mark). */
function find(target: string): HTMLElement | null {
    return [...document.querySelectorAll<HTMLElement>(`[data-tour="${target}"]`)].find((el) => el.offsetParent !== null || getComputedStyle(el).position === 'fixed') ?? null;
}

const PAD = 8;

/**
 * First-time guides: the page dims, the real button is cut out of the dimmed layer and circled, and a card explains
 * it. Shown once per account (recorded on the server as soon as it opens), again from Profile → Settings, or via
 * ?tour=student|committee.
 */
export default function GuidedTour() {
    const page = usePage();
    const user = page.props.auth.user;
    const [tour, setTour] = useState<TourName | null>(null);
    const [steps, setSteps] = useState<TourStep[]>([]);
    const [index, setIndex] = useState(0);
    const [rect, setRect] = useState<DOMRect | null>(null);
    const step = steps[index] ?? null;

    const markSeen = useCallback(
        (name: TourName): void => {
            if (user?.tours_seen?.includes(name)) return;
            router.post('/me/tours', { tour: name, seen: true }, { preserveScroll: true, preserveState: true, only: ['auth'] });
        },
        [user],
    );
    const measure = useCallback((): void => {
        const el = step ? find(step.target) : null;
        setRect(el ? el.getBoundingClientRect() : null);
    }, [step]);

    // Each step: scroll its element into view, then measure it once the scroll settles.
    useEffect(() => {
        if (!tour || !step) return;
        const el = find(step.target);
        if (!el) {
            if (index < steps.length - 1) setIndex(index + 1);
            else finish();
            return;
        }
        el.scrollIntoView({ block: 'center', behavior: 'smooth' });
        const timer = setTimeout(measure, 350);
        return () => clearTimeout(timer);
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, [tour, step]);
    useWindowEvent('resize', measure);
    useWindowEvent('scroll', measure, { passive: true, capture: true });

    const finish = (): void => {
        const name = tour;
        setTour(null);
        setRect(null);
        if (name) markSeen(name);
    };
    const next = (): void => (index < steps.length - 1 ? setIndex(index + 1) : finish());
    const back = (): void => void (index > 0 && setIndex(index - 1));
    useWindowEvent('keydown', (e) => {
        if (!tour) return;
        if (e.key === 'Escape') finish();
        if (e.key === 'ArrowRight' || e.key === 'Enter') next();
        if (e.key === 'ArrowLeft') back();
    });

    // When to start: first time (not seen yet, on the right page, no other dialog open), or when asked via ?tour=.
    useEffect(() => {
        if (!user || tour) return;
        const seen = user.tours_seen ?? [];
        const asked = new URLSearchParams(window.location.search).get('tour') as TourName | null;
        const club = user.committee_club;
        const onMyClub = club && page.url.startsWith(`/clubs/${club.slug}`) && page.component === 'Clubs/Show';
        const wanted: TourName | null =
            asked && asked in TOURS ? asked : onMyClub && !seen.includes('committee') ? 'committee' : page.component === 'Feed/Index' && !seen.includes('student') ? 'student' : null;
        if (!wanted) return;
        let timer: ReturnType<typeof setTimeout>;
        const tryStart = (attempt = 0): void => {
            // Let pop-ups (e.g. the committee fee reminder) go first.
            if (document.querySelector('dialog[open]') && attempt < 20) {
                timer = setTimeout(() => tryStart(attempt + 1), 1000);
                return;
            }
            const available = TOURS[wanted].steps.filter((s) => find(s.target));
            if (!available.length) return;
            setSteps(available);
            setIndex(0);
            setTour(wanted);
            markSeen(wanted);
        };
        timer = setTimeout(tryStart, 900);
        return () => clearTimeout(timer);
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, [page.url]);

    // Spotlight geometry: padded hole around the element, circle drawn just outside it, card placed above or below.
    const hole = rect ? { x: rect.left - PAD, y: rect.top - PAD, w: rect.width + PAD * 2, h: rect.height + PAD * 2 } : null;
    const card = useMemo((): CSSProperties => {
        if (!hole) return { top: '50%', left: '50%', transform: 'translate(-50%, -50%)' };
        const below = hole.y + hole.h + 16;
        const fitsBelow = below + 190 < window.innerHeight;
        const width = Math.min(340, window.innerWidth - 32);
        const left = Math.max(16, Math.min(hole.x + hole.w / 2 - width / 2, window.innerWidth - width - 16));
        return { top: `${fitsBelow ? below : Math.max(16, hole.y - 16 - 190)}px`, left: `${left}px`, width: `${width}px` };
    }, [hole?.x, hole?.y, hole?.w, hole?.h]); // eslint-disable-line react-hooks/exhaustive-deps

    if (!tour || !step) return null;

    return createPortal(
        <div className="fixed inset-0 z-[90]" role="dialog" aria-modal="true" aria-label={TOURS[tour].title}>
            {/* Dim everything except the highlighted element (clicks on the dim layer are blocked). */}
            {hole ? (
                <div
                    className="pointer-events-none absolute rounded-2xl transition-all duration-300 ease-out"
                    style={{ left: `${hole.x}px`, top: `${hole.y}px`, width: `${hole.w}px`, height: `${hole.h}px`, boxShadow: '0 0 0 9999px rgb(10 10 14 / 0.62)' }}
                />
            ) : (
                <div className="absolute inset-0 bg-[rgb(10_10_14/0.62)]" />
            )}
            <div className="absolute inset-0" onClick={(e) => e.target === e.currentTarget && next()} />
            {/* The circle: a pulsing ring around the highlighted element. */}
            {hole && (
                <span
                    className={`${styles.ring} pointer-events-none absolute rounded-2xl border-[3px] border-brand-yellow transition-all duration-300 ease-out`}
                    style={{ left: `${hole.x - 4}px`, top: `${hole.y - 4}px`, width: `${hole.w + 8}px`, height: `${hole.h + 8}px` }}
                />
            )}
            <section className="absolute grid gap-3 rounded-card bg-surface p-4 text-ink shadow-pop transition-all duration-300" style={card}>
                <div className="flex items-center justify-between gap-2 text-xs text-ink-muted">
                    <span className="font-semibold uppercase tracking-wide">{TOURS[tour].title}</span>
                    <span className="tabular-nums">
                        {index + 1} / {steps.length}
                    </span>
                </div>
                <div className="grid gap-1">
                    <h2 className="text-base font-bold">{step.title}</h2>
                    <p className="text-sm text-ink-muted">{step.body}</p>
                </div>
                <div className="h-1 overflow-hidden rounded-full bg-surface-muted">
                    <div className="h-full rounded-full bg-gradient-to-r from-brand-orange to-brand-magenta transition-[width] duration-300" style={{ width: `${((index + 1) / steps.length) * 100}%` }} />
                </div>
                <div className="flex items-center justify-between gap-2">
                    <button type="button" className="text-sm text-ink-muted hover:text-ink" onClick={finish}>
                        Skip guide
                    </button>
                    <div className="flex gap-2">
                        {index > 0 && (
                            <button type="button" className="h-9 rounded-control border border-line px-3 text-sm font-medium" onClick={back}>
                                Back
                            </button>
                        )}
                        <button type="button" className="h-9 rounded-control bg-gradient-to-r from-brand-orange to-brand-magenta px-4 text-sm font-semibold text-white" onClick={next}>
                            {index === steps.length - 1 ? 'Got it' : 'Next'}
                        </button>
                    </div>
                </div>
            </section>
        </div>,
        document.body,
    );
}
