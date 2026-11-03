import Avatar from '@react/Components/ui/Avatar';
import { rm } from '@/lib/format';
import type { Ticket } from '@/types';
import type { CSSProperties } from 'react';
import './TicketCard.css';

const THEMES = {
    party: { bg: 'from-brand-orange via-brand-magenta to-brand-blue', label: 'Party' },
    nature: { bg: 'from-emerald-500 via-teal-500 to-brand-cyan', label: 'Outdoor' },
    online: { bg: 'from-indigo-700 via-brand-blue to-brand-cyan', label: 'Online' },
} as const;

// Fixed pseudo-random particles, so the layout is the same on every render.
const PARTICLES = Array.from({ length: 14 }, (_, i) => ({
    left: `${(i * 37) % 100}%`,
    delay: `${(i * 0.73) % 6}s`,
    duration: `${6 + ((i * 1.9) % 5)}s`,
    size: 6 + ((i * 5) % 9),
    hue: ['#FDDA1A', '#1CBDFD', '#E01CEB', '#FD8F1A', '#ffffff'][i % 5],
    rotate: `${(i * 47) % 360}deg`,
}));

function when(ticket: Ticket) {
    const start = new Date(ticket.event.starts_at);
    const end = new Date(ticket.event.ends_at);
    const opts = { timeZone: 'Asia/Kuala_Lumpur' } as const;
    return {
        day: start.toLocaleString('en-MY', { ...opts, day: 'numeric' }),
        month: start.toLocaleString('en-MY', { ...opts, month: 'short' }).toUpperCase(),
        weekday: start.toLocaleString('en-MY', { ...opts, weekday: 'long' }),
        time: `${start.toLocaleString('en-MY', { ...opts, hour: 'numeric', minute: '2-digit' })} – ${end.toLocaleString('en-MY', { ...opts, hour: 'numeric', minute: '2-digit' })}`,
    };
}

/**
 * An event ticket that moves with its event: confetti for a party, drifting leaves outdoors, a glowing grid for
 * online events, and a holographic shine sweeping across. Full size shows the QR stub; compact is a list row.
 * Motion stops for people who ask their device for reduced motion.
 */
export default function TicketCard({ ticket, qrSvg = null, compact = false }: { ticket: Ticket; qrSvg?: string | null; compact?: boolean }) {
    const theme = THEMES[ticket.theme] ?? THEMES.party;
    const w = when(ticket);
    const status = {
        paid: { text: `Paid ${rm(ticket.amount_sen)}`, tone: 'bg-success text-white' },
        free: { text: 'Free entry', tone: 'bg-success text-white' },
        checking: { text: 'Payment being checked', tone: 'bg-brand-cyan text-white' },
        unpaid: { text: 'Not paid yet', tone: 'bg-warning text-white' },
    }[ticket.status];
    const paidUp = ticket.status === 'paid' || ticket.status === 'free';
    // Outdoor events: the QR ticket appears only after the indemnity is signed.
    const valid = paidUp && !ticket.event.needs_indemnity;

    if (compact) {
        return (
            <div className={`tk-ticket relative flex overflow-hidden rounded-2xl bg-gradient-to-r text-white shadow-card ${theme.bg}`}>
                <span className="tk-shine" aria-hidden="true" />
                <div className="relative grid w-16 shrink-0 place-items-center bg-black/15 py-3 text-center leading-none">
                    <span className="text-[10px] font-semibold tracking-wider">{w.month}</span>
                    <span className="text-2xl font-bold">{w.day}</span>
                </div>
                <div className="relative min-w-0 flex-1 px-3 py-3">
                    <p className="truncate font-semibold">{ticket.event.name}</p>
                    <p className="truncate text-xs text-white/80">
                        {w.time}
                        {ticket.event.venue && ` · ${ticket.event.venue}`}
                    </p>
                    <p className="mt-1 flex flex-wrap items-center gap-1.5 text-[11px]">
                        <span className={`rounded-full px-2 py-0.5 font-semibold ${status.tone}`}>{status.text}</span>
                        {ticket.clock_in && <span className="rounded-full bg-white/20 px-2 py-0.5">In {ticket.clock_in}</span>}
                    </p>
                </div>
                <div className="tk-notch-divider relative grid w-14 shrink-0 place-items-center" aria-hidden="true">
                    <svg viewBox="0 0 24 24" className="size-7 opacity-90" fill="none" stroke="currentColor" strokeWidth="1.8">
                        <path d="M3 3h7v7H3zm11 0h7v7h-7zM3 14h7v7H3zm11 0h3v3h-3zm4 4h3v3h-3zm-4 3h1m6-7h1" />
                    </svg>
                </div>
            </div>
        );
    }

    return (
        <article className={`tk-ticket relative mx-auto w-full max-w-sm overflow-hidden rounded-[28px] bg-gradient-to-br text-white shadow-pop ${theme.bg} tk-theme-${ticket.theme}`}>
            <div className="tk-aurora" aria-hidden="true" />
            <div className="pointer-events-none absolute inset-0 overflow-hidden" aria-hidden="true">
                {PARTICLES.map((p, i) => (
                    <span
                        key={i}
                        className="tk-particle"
                        style={
                            {
                                left: p.left,
                                animationDelay: p.delay,
                                animationDuration: p.duration,
                                width: `${p.size}px`,
                                height: `${ticket.theme === 'party' ? p.size * 0.5 : p.size}px`,
                                background: ticket.theme === 'party' ? p.hue : undefined,
                                '--r': p.rotate,
                            } as CSSProperties
                        }
                    />
                ))}
            </div>
            <span className="tk-shine" aria-hidden="true" />

            <header className="relative">
                {/* The whole poster, never cropped: shown in full over a blurred copy of itself. */}
                {ticket.event.poster_url && (
                    <div className="relative m-3 mb-0 aspect-[16/9] overflow-hidden rounded-[20px] bg-black/20 ring-1 ring-white/30">
                        <img src={ticket.event.poster_url} alt="" aria-hidden="true" className="tk-poster absolute inset-0 size-full scale-110 object-cover opacity-60 blur-xl" />
                        <img src={ticket.event.poster_url} alt="" className="relative size-full object-contain" />
                    </div>
                )}
                <div className="relative px-6 pb-4 pt-4">
                    <p className="flex items-center gap-2 text-xs font-semibold uppercase tracking-[0.18em] text-white/90">
                        {ticket.event.club_logo && <img src={ticket.event.club_logo} alt="" className="size-6 rounded-full bg-white object-cover" />}
                        {ticket.event.club}
                    </p>
                    <h1 className="mt-2 text-2xl font-extrabold leading-tight drop-shadow">{ticket.event.name}</h1>
                </div>
            </header>

            <dl className="relative grid grid-cols-[auto_1fr] items-center gap-x-4 gap-y-2 px-6 pb-5 text-sm">
                <div className="row-span-2 grid w-16 place-items-center rounded-2xl bg-white/15 py-2 text-center leading-none backdrop-blur">
                    <span className="text-[11px] font-semibold tracking-wider">{w.month}</span>
                    <span className="text-3xl font-black">{w.day}</span>
                </div>
                <div>
                    <dt className="text-[11px] uppercase tracking-wider text-white/70">{w.weekday}</dt>
                    <dd className="font-semibold">{w.time}</dd>
                </div>
                <div>
                    <dt className="text-[11px] uppercase tracking-wider text-white/70">Venue</dt>
                    <dd className="font-semibold">{ticket.event.venue ?? 'To be announced'}</dd>
                </div>
            </dl>

            <div className="relative flex items-center" aria-hidden="true">
                <span className="-ml-3 size-6 rounded-full bg-surface-muted" />
                <span className="flex-1 border-t-2 border-dashed border-white/50" />
                <span className="-mr-3 size-6 rounded-full bg-surface-muted" />
            </div>

            {/* Stub: above the shine layer, so nothing sweeps across the QR the door scanner reads. */}
            <section className="relative z-[2] m-4 grid gap-4 rounded-3xl bg-surface p-5 text-ink">
                <div className="flex items-center gap-3">
                    <Avatar src={ticket.attendee.photo_url} name={ticket.attendee.name} size="sm" />
                    <div className="min-w-0 flex-1">
                        <p className="truncate font-semibold">{ticket.attendee.name}</p>
                        <p className="text-xs text-ink-muted">{ticket.attendee.student_number}</p>
                    </div>
                    <span className={`whitespace-nowrap rounded-full px-2.5 py-1 text-[11px] font-semibold ${status.tone}`}>{status.text}</span>
                </div>

                {qrSvg && valid ? (
                    <div className="tk-qr-frame relative mx-auto grid place-items-center rounded-2xl p-3">
                        {/* Server-generated SVG of the ticket's own scan URL. */}
                        <div className="size-48 [&_svg]:size-full" dangerouslySetInnerHTML={{ __html: qrSvg }} />
                    </div>
                ) : (
                    !valid && (
                        <p className="rounded-control bg-warning/10 px-3 py-2 text-center text-sm text-warning">
                            {paidUp ? 'Sign the indemnity form and your QR ticket appears here.' : 'Your QR ticket appears once payment is confirmed.'}
                        </p>
                    )
                )}

                <div className="grid grid-cols-2 gap-3 text-xs">
                    <div>
                        <p className="text-ink-muted">Ticket / transaction ID</p>
                        <p className="font-mono font-semibold [overflow-wrap:anywhere]">{ticket.order_id?.replaceAll('_', '_​') ?? '—'}</p>
                    </div>
                    <div className="text-right">
                        <p className="text-ink-muted">Check-in</p>
                        <p className="font-semibold">
                            {ticket.clock_in ? (
                                <>
                                    In {ticket.clock_in}
                                    {ticket.clock_out && ` · Out ${ticket.clock_out}`}
                                </>
                            ) : ticket.event.past ? (
                                'Not attended'
                            ) : (
                                'Not yet'
                            )}
                        </p>
                    </div>
                </div>
                {valid && !ticket.event.past && <p className="text-center text-[11px] text-ink-muted">Show this QR at the door. Screenshots work, but the committee checks the name.</p>}
            </section>
        </article>
    );
}
