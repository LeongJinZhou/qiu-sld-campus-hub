import Icon from '@react/Components/ui/Icon';
import type { SessionOption } from '@/types';
import { router } from '@inertiajs/react';
import { useLayoutEffect, useRef, useState } from 'react';

const short = (name: string): string => name.replace(/^(\d{4})\/\d{2}(\d{2})$/, '$1/$2');

/**
 * The committee year as a timeline: one card per session, newest first, the current one marked "Now".
 * Choosing a year slides the highlight across and loads that year's committee.
 */
export default function SessionSelector({ sessions, selected, url }: { sessions: SessionOption[]; selected: string; url: string }) {
    const rail = useRef<HTMLDivElement>(null);
    const [pill, setPill] = useState({ left: 0, width: 0, ready: false });
    const [loading, setLoading] = useState<string | null>(null);

    useLayoutEffect(() => {
        const el = rail.current?.querySelector<HTMLElement>('[aria-current="true"]');
        if (!el) return;
        setPill({ left: el.offsetLeft, width: el.offsetWidth, ready: true });
        el.scrollIntoView({ behavior: 'smooth', block: 'nearest', inline: 'center' });
    }, [selected, sessions]);

    function choose(name: string): void {
        if (name === selected) return;
        setLoading(name);
        router.get(url, { session: name }, { preserveScroll: true, preserveState: true, replace: true, onFinish: () => setLoading(null) });
    }
    const step = (dir: -1 | 1): void => {
        const i = sessions.findIndex((s) => s.name === selected);
        const next = sessions[i - dir];
        if (next) choose(next.name);
    };
    const arrow = 'grid size-7 place-items-center rounded-full border border-line text-ink-muted transition hover:bg-surface-muted disabled:opacity-30';

    return (
        <div className="grid gap-2">
            <div className="flex items-center justify-between gap-2">
                <p className="flex items-center gap-2 text-xs font-semibold uppercase tracking-wide text-ink-muted">
                    <Icon name="calendar" size={14} /> Committee year
                </p>
                {sessions.length > 1 && (
                    <div className="flex gap-1">
                        <button type="button" className={arrow} aria-label="Earlier year" disabled={sessions.at(-1)?.name === selected} onClick={() => step(-1)}>
                            ‹
                        </button>
                        <button type="button" className={arrow} aria-label="Later year" disabled={sessions[0]?.name === selected} onClick={() => step(1)}>
                            ›
                        </button>
                    </div>
                )}
            </div>
            <div ref={rail} className="relative -mx-1 flex snap-x gap-2 overflow-x-auto px-1 pb-1 [scrollbar-width:none]" role="tablist" aria-label="Committee year">
                <span
                    className={`absolute top-0 h-full rounded-card bg-gradient-to-br from-brand-orange via-brand-magenta to-brand-blue shadow-pop transition-all duration-500 ease-[cubic-bezier(0.2,0.8,0.2,1)] ${pill.ready ? 'opacity-100' : 'opacity-0'}`}
                    style={{ left: `${pill.left}px`, width: `${pill.width}px` }}
                    aria-hidden="true"
                />
                {sessions.map((s) => {
                    const on = s.name === selected;
                    return (
                        <button
                            key={s.id}
                            type="button"
                            role="tab"
                            aria-selected={on}
                            aria-current={on}
                            className={`relative z-10 grid min-w-28 shrink-0 snap-center gap-0.5 rounded-card border px-4 py-2.5 text-left transition-colors duration-300 ${on ? 'border-transparent text-white' : 'border-line bg-surface hover:border-brand-cyan/60 hover:bg-surface-muted'}`}
                            onClick={() => choose(s.name)}
                        >
                            <span className={`flex items-center gap-1.5 text-[11px] font-semibold uppercase tracking-wide ${on ? 'text-white/80' : 'text-ink-muted'}`}>
                                {s.current && (
                                    <span className="relative flex size-2">
                                        <span className="absolute inline-flex size-full animate-ping rounded-full bg-success opacity-60" />
                                        <span className="relative inline-flex size-2 rounded-full bg-success" />
                                    </span>
                                )}
                                {s.current ? 'Now' : 'Session'}
                            </span>
                            <span className="text-lg font-black tabular-nums leading-tight">{short(s.name)}</span>
                            <span className={`text-[11px] ${on ? 'text-white/80' : 'text-ink-muted'}`}>
                                {loading === s.name ? (
                                    <span className="inline-block size-3 animate-spin rounded-full border-2 border-current border-t-transparent align-middle" />
                                ) : s.size ? (
                                    `${s.size} on the committee`
                                ) : (
                                    'No committee yet'
                                )}
                            </span>
                        </button>
                    );
                })}
            </div>
        </div>
    );
}
