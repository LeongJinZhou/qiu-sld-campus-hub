import ApprovalTracker from '@/Components/forms/ApprovalTracker';
import Avatar from '@/Components/ui/Avatar';
import Badge, { type BadgeTone } from '@/Components/ui/Badge';
import Button from '@/Components/ui/Button';
import Card from '@/Components/ui/Card';
import Icon from '@/Components/ui/Icon';
import Input from '@/Components/ui/Input';
import Modal from '@/Components/ui/Modal';
import PersonPicker, { type PersonPickerHandle } from '@/Components/ui/PersonPicker';
import QrScanner from '@/Components/ui/QrScanner';
import SignaturePad from '@/Components/ui/SignaturePad';
import AppLayout from '@/Layouts/AppLayout';
import { rm } from '@/lib/format';
import { matchesSearch } from '@/lib/search';
import type { ApprovalProgress, ApprovalStep } from '@/types';
import { Head, Link, router, useForm } from '@inertiajs/react';
import { useEffect, useRef, useState, type FormEvent } from 'react';

interface Person {
    user_id: number;
    name: string;
    photo_url: string | null;
    student_number: string | null;
    registered: boolean;
    status: 'paid' | 'free' | 'unpaid' | 'checking' | 'committee' | 'walk_in';
    order_id: string | null;
    in: string | null;
    out: string | null;
    method: string | null;
    key: string;
}
interface AttendanceProps {
    event: { slug: string; name: string; is_outdoor: boolean; attendance_open: boolean; fee_sen: number; starts_at: string; club_slug: string };
    action: 'in' | 'out';
    qr: { svg: string; seconds: number; seconds_left: number } | null;
    find: string;
    results: Person[];
    people: Person[];
    counts: { registered: number; paid: number; unpaid: number; in: number; out: number };
    helpers: { id: number; name: string; student_number: string | null; photo_url: string | null }[];
    signOff: { id: number; status: string; progress: ApprovalProgress; steps: ApprovalStep[] } | null;
    can: { manage: boolean };
    limits: { min: number; max: number };
}
type View = 'scan' | 'qr' | 'both';
type Filter = 'all' | 'paid' | 'unpaid' | 'in' | 'notin';
const STATUS: Record<Person['status'], { label: string; tone: BadgeTone }> = {
    paid: { label: 'Paid', tone: 'success' },
    free: { label: 'Registered', tone: 'success' },
    unpaid: { label: 'NOT PAID', tone: 'danger' },
    checking: { label: 'Payment being checked', tone: 'warning' },
    committee: { label: 'Committee', tone: 'info' },
    walk_in: { label: 'Walk-in', tone: 'neutral' },
};
const PRESETS = [10, 15, 30, 60, 120, 300];
const toggle = (on: boolean): string => `rounded-control px-2 py-2 ${on ? 'bg-surface font-semibold shadow-card' : ''}`;

/** The event door desk: scan tickets or profile QRs, show the rotating QR, find anyone, helpers and the 03 list sign-off. */
export default function Attendance({ event, action, qr, find, results, people, counts, helpers, signOff, can, limits }: AttendanceProps) {
    const url = `/events/${event.slug}/attendance`;
    const stats = [
        { l: 'Registered', v: counts.registered, warn: false },
        { l: event.fee_sen ? 'Paid' : 'Confirmed', v: counts.paid, warn: false },
        { l: 'Not paid', v: counts.unpaid, warn: true },
        { l: 'In', v: counts.in, warn: false },
        { l: 'Out', v: counts.out, warn: false },
    ];
    // What the desk shows: scan people's QRs, show the rotating QR for them to scan, or both side by side (remembered on this device).
    const viewKey = `desk-view-${event.slug}`;
    const [view, setViewState] = useState<View>(() => {
        try {
            const saved = localStorage.getItem(viewKey);
            if (saved === 'scan' || saved === 'qr' || saved === 'both') return saved;
        } catch {
            // No storage: use the default.
        }
        return can.manage ? 'both' : 'scan';
    });
    const setView = (v: View): void => {
        setViewState(v);
        try {
            localStorage.setItem(viewKey, v);
        } catch {
            // Not remembered; fine.
        }
    };
    const [scanAction, setScanAction] = useState<'check' | 'in' | 'out'>('in');
    const [scanning, setScanning] = useState(false);
    const lastScan = useRef('');
    const onScan = (code: string): void => {
        if (code === lastScan.current) return;
        lastScan.current = code;
        router.post(`${url}?action=${action}`, { action: scanAction, code }, { preserveScroll: true, preserveState: true, onFinish: () => setTimeout(() => (lastScan.current = ''), 2500) });
    };
    const [query, setQuery] = useState(find);
    const lookup = (text = query): void => router.get(url, { find: text.trim(), action }, { preserveState: true, preserveScroll: true, replace: true });
    const clearLookup = (): void => {
        setQuery('');
        router.get(url, { action }, { preserveState: true, preserveScroll: true, replace: true });
    };
    const act = (p: Person, a: 'in' | 'out' | 'register'): void => router.post(`${url}?action=${action}`, { action: a, code: p.student_number ?? p.key }, { preserveScroll: true, preserveState: true });
    const setAction = (a: 'in' | 'out'): void => router.get(url, { action: a, ...(find ? { find } : {}) }, { preserveState: true, preserveScroll: true, replace: true });

    // Rotating QR: count down, fetch the next code when it expires.
    const [left, setLeft] = useState(qr?.seconds_left ?? 0);
    const leftRef = useRef(left);
    useEffect(() => {
        leftRef.current = qr?.seconds_left ?? 0;
        setLeft(leftRef.current);
    }, [qr?.seconds_left, qr?.svg]);
    useEffect(() => {
        if (!qr) return;
        const timer = setInterval(() => {
            leftRef.current = Math.max(0, leftRef.current - 1);
            setLeft(leftRef.current);
            if (leftRef.current === 0) router.reload({ only: ['qr'] });
        }, 1000);
        return () => clearInterval(timer);
    }, [qr]);
    const pace = useForm({ qr_rotate_seconds: String(qr?.seconds ?? 60) });
    const savePace = (seconds: number): void => {
        pace.transform(() => ({ qr_rotate_seconds: seconds }));
        pace.put(`${url}/settings`, { preserveScroll: true, onSuccess: () => router.reload({ only: ['qr'] }) });
    };
    const qrBox = useRef<HTMLDivElement>(null);
    const fullscreen = (): void => void qrBox.current?.requestFullscreen?.().catch(() => undefined);

    // People list (refreshes by itself).
    useEffect(() => {
        const timer = setInterval(() => router.reload({ only: ['people', 'counts'] }), 15_000);
        return () => clearInterval(timer);
    }, []);
    const [filter, setFilter] = useState<Filter>('all');
    const [search, setSearch] = useState('');
    const filtered = people.filter((p) => {
        const ok = { all: true, paid: ['paid', 'free'].includes(p.status), unpaid: ['unpaid', 'checking'].includes(p.status), in: !!p.in, notin: !p.in }[filter];
        return ok && matchesSearch(search, p.name, p.student_number, p.order_id);
    });

    // Helpers & sign-off (organisers).
    const helper = useForm({ student_number: '' });
    const helperPicker = useRef<PersonPickerHandle>(null);
    const addHelper = (e: FormEvent): void => {
        e.preventDefault();
        helper.post(`/events/${event.slug}/helpers`, {
            preserveScroll: true,
            onSuccess: () => {
                helper.reset();
                helperPicker.current?.clear();
            },
        });
    };
    const [signing, setSigning] = useState(false);
    const sign = useForm({ signature: null as string | null, signature_strokes: null as string | null });
    const submitList = (): void =>
        sign.post(`${url}/submit`, {
            preserveScroll: true,
            onSuccess: () => {
                setSigning(false);
                sign.reset();
            },
        });
    const canSubmit = can.manage && event.attendance_open && (!signOff || ['draft', 'returned'].includes(signOff.status));
    const filters: { k: Filter; l: string }[] = [
        { k: 'all', l: `All ${people.length}` },
        { k: 'paid', l: `Paid ${counts.paid}` },
        { k: 'unpaid', l: `Not paid ${counts.unpaid}` },
        { k: 'in', l: `In ${counts.in}` },
        { k: 'notin', l: 'Not in yet' },
    ];

    return (
        <AppLayout>
            <Head title={`Door · ${event.name}`} />
            <div className="grid gap-5">
                <header className="grid gap-3">
                    <div className="flex flex-wrap items-start justify-between gap-3">
                        <div className="min-w-0">
                            <Link href={`/events/${event.slug}`} className="text-sm text-brand-blue">
                                ← {event.name}
                            </Link>
                            <h1 className="text-xl font-bold">Door &amp; attendance</h1>
                        </div>
                        {can.manage && (
                            <div className="flex flex-wrap gap-2">
                                {canSubmit && (
                                    <Button size="sm" onClick={() => setSigning(true)}>
                                        Sign &amp; submit list
                                    </Button>
                                )}
                                <a href={`${url}/export`}>
                                    <Button size="sm" variant="secondary">
                                        03 Attendance (XLSX)
                                    </Button>
                                </a>
                                {event.is_outdoor && (
                                    <Link href={`/events/${event.slug}/indemnities`}>
                                        <Button size="sm" variant="ghost">
                                            Indemnities
                                        </Button>
                                    </Link>
                                )}
                            </div>
                        )}
                    </div>
                    <dl className="grid grid-cols-5 divide-x divide-line rounded-card border border-line bg-surface text-center shadow-card">
                        {stats.map((s) => (
                            <div key={s.l} className="py-2.5">
                                <dd className={`text-lg font-bold tabular-nums ${s.warn && s.v ? 'text-danger' : ''}`}>{s.v}</dd>
                                <dt className="text-[11px] text-ink-muted">{s.l}</dt>
                            </div>
                        ))}
                    </dl>
                </header>
                {!event.attendance_open && <p className="rounded-card bg-warning/10 px-4 py-3 text-sm text-warning">Clock-in opens once the proposal is approved. You can still check tickets and payments.</p>}
                <div className="grid gap-2 sm:grid-cols-2">
                    <div className="grid grid-cols-3 gap-1 rounded-control bg-surface-muted p-1 text-sm" role="radiogroup" aria-label="Desk view">
                        {(
                            [
                                { k: 'scan', l: 'Scan people' },
                                { k: 'qr', l: 'Show QR' },
                                { k: 'both', l: 'Both' },
                            ] as const
                        ).map((v) => (
                            <button key={v.k} type="button" role="radio" aria-checked={view === v.k} className={toggle(view === v.k)} onClick={() => setView(v.k)}>
                                {v.l}
                            </button>
                        ))}
                    </div>
                    <div className="grid grid-cols-2 gap-1 rounded-control bg-surface-muted p-1 text-sm" role="radiogroup" aria-label="Clock in or out">
                        {(['in', 'out'] as const).map((a) => (
                            <button
                                key={a}
                                type="button"
                                role="radio"
                                aria-checked={action === a}
                                className={`rounded-control px-2 py-2 ${action === a ? (a === 'in' ? 'bg-success font-semibold text-white' : 'bg-ink font-semibold text-white') : ''}`}
                                onClick={() => {
                                    setAction(a);
                                    setScanAction(a);
                                }}
                            >
                                Clock {a}
                            </button>
                        ))}
                    </div>
                </div>
                <div className={`grid gap-5 ${view === 'both' ? 'lg:grid-cols-2' : ''}`}>
                    {view !== 'qr' && (
                        <Card title="Scan tickets & QR">
                            <div className="grid gap-3">
                                <div className="grid grid-cols-3 gap-1 rounded-control bg-surface-muted p-1 text-xs" role="radiogroup" aria-label="What a scan does">
                                    {(
                                        [
                                            { k: 'check', l: 'Check only' },
                                            { k: 'in', l: 'Scan = clock in' },
                                            { k: 'out', l: 'Scan = clock out' },
                                        ] as const
                                    ).map((m) => (
                                        <button key={m.k} type="button" role="radio" aria-checked={scanAction === m.k} className={`rounded-control px-1 py-1.5 ${scanAction === m.k ? 'bg-surface font-semibold shadow-card' : ''}`} onClick={() => setScanAction(m.k)}>
                                            {m.l}
                                        </button>
                                    ))}
                                </div>
                                {scanning && <QrScanner onScan={onScan} />}
                                <Button variant={scanning ? 'secondary' : 'primary'} onClick={() => setScanning(!scanning)}>
                                    <Icon name="scan" size={18} /> {scanning ? 'Stop camera' : 'Start scanning'}
                                </Button>
                                <form
                                    className="flex items-end gap-2"
                                    onSubmit={(e) => {
                                        e.preventDefault();
                                        lookup();
                                    }}
                                >
                                    <div className="flex-1">
                                        <Input value={query} onChange={setQuery} label="Or type a name, ticket ID or student ID" placeholder="e.g. Kavitha, GPS-CLUB_…_0003, QIU-202407-0001" />
                                    </div>
                                    <Button type="submit" variant="secondary">
                                        Find
                                    </Button>
                                </form>
                                <p className="text-xs text-ink-muted">Works with event tickets and personal profile QRs.</p>
                            </div>
                        </Card>
                    )}
                    {view !== 'scan' && (
                        <Card
                            title="Participants scan this"
                            actions={
                                <button type="button" className="text-sm font-medium text-brand-blue" onClick={fullscreen}>
                                    Full screen
                                </button>
                            }
                        >
                            {qr ? (
                                <div ref={qrBox} className="grid justify-items-center gap-3 bg-surface p-2">
                                    <p className={`text-lg font-bold ${action === 'in' ? 'text-success' : 'text-ink'}`}>
                                        Clock {action} · {event.name}
                                    </p>
                                    {/* Server-generated SVG of the event's signed clock-in / clock-out URL. */}
                                    <div className="size-64 rounded-card bg-white p-3 sm:size-72 [&>svg]:size-full" role="img" aria-label={`QR to clock ${action}`} dangerouslySetInnerHTML={{ __html: qr.svg }} />
                                    <div className="grid w-64 gap-1 sm:w-72">
                                        <div className="h-1.5 overflow-hidden rounded-full bg-surface-muted">
                                            <div className="h-full rounded-full bg-gradient-to-r from-brand-cyan to-brand-magenta transition-[width] duration-1000 ease-linear" style={{ width: `${(left / qr.seconds) * 100}%` }} />
                                        </div>
                                        <p className="text-center text-xs text-ink-muted">New code in {left} s · a shared photo stops working</p>
                                    </div>
                                </div>
                            ) : (
                                <p className="text-sm text-ink-muted">The QR appears once attendance opens.</p>
                            )}
                            {can.manage && qr && (
                                <div className="mt-4 grid gap-2 border-t border-line pt-3">
                                    <p className="text-xs font-medium text-ink-muted">Change the code every</p>
                                    <div className="flex flex-wrap gap-1.5">
                                        {PRESETS.map((s) => (
                                            <button key={s} type="button" className={`rounded-full border px-3 py-1 text-xs font-medium ${qr.seconds === s ? 'border-ink bg-ink text-white' : 'border-line hover:bg-surface-muted'}`} onClick={() => savePace(s)}>
                                                {s < 60 ? `${s} s` : `${s / 60} min`}
                                            </button>
                                        ))}
                                        <form
                                            className="flex items-center gap-1"
                                            onSubmit={(e) => {
                                                e.preventDefault();
                                                savePace(Number(pace.data.qr_rotate_seconds));
                                            }}
                                        >
                                            <input
                                                value={pace.data.qr_rotate_seconds}
                                                onChange={(e) => pace.setData('qr_rotate_seconds', e.target.value)}
                                                type="number"
                                                min={limits.min}
                                                max={limits.max}
                                                className="w-16 rounded-full border border-line px-2 py-1 text-xs"
                                                aria-label="Seconds"
                                            />
                                            <button type="submit" className="text-xs font-medium text-brand-blue">
                                                Set
                                            </button>
                                        </form>
                                    </div>
                                    {pace.errors.qr_rotate_seconds && <p className="text-xs text-danger">{pace.errors.qr_rotate_seconds}</p>}
                                </div>
                            )}
                        </Card>
                    )}
                </div>
                {find && (
                    <section className="grid gap-3">
                        <div className="flex items-center justify-between">
                            <h2 className="font-semibold">Result for “{find}”</h2>
                            <button type="button" className="text-sm text-ink-muted underline" onClick={clearLookup}>
                                Clear
                            </button>
                        </div>
                        {!results.length && <p className="rounded-card border border-line bg-surface p-4 text-sm text-ink-muted">No ticket or student found.</p>}
                        {results.map((p) => (
                            <article
                                key={p.user_id}
                                className={`grid gap-3 rounded-card border-2 bg-surface p-4 shadow-card ${['paid', 'free', 'committee'].includes(p.status) ? 'border-success' : p.status === 'walk_in' && !p.registered ? 'border-line' : 'border-danger'}`}
                            >
                                <div className="flex items-center gap-3">
                                    <Avatar src={p.photo_url} name={p.name} size="md" />
                                    <div className="min-w-0 flex-1">
                                        <p className="truncate text-lg font-bold">{p.name}</p>
                                        <p className="text-xs text-ink-muted">
                                            {p.student_number}
                                            {p.order_id && (
                                                <>
                                                    {' · '}
                                                    <span className="font-mono">{p.order_id}</span>
                                                </>
                                            )}
                                        </p>
                                    </div>
                                    <Badge tone={p.registered ? STATUS[p.status].tone : 'neutral'}>{p.registered ? STATUS[p.status].label : 'Not registered'}</Badge>
                                </div>
                                {p.status === 'unpaid' && (
                                    <p className="rounded-control bg-danger/10 px-3 py-2 text-sm text-danger">
                                        Fee {rm(event.fee_sen)} not paid. They can pay in the app (online or cash with a committee member) — clock-in stays blocked until then.
                                    </p>
                                )}
                                <p className="text-sm">
                                    In: <strong>{p.in ?? '—'}</strong> · Out: <strong>{p.out ?? '—'}</strong>
                                </p>
                                <div className="grid grid-cols-2 gap-2 sm:grid-cols-3">
                                    {!p.registered && (
                                        <Button variant="secondary" onClick={() => act(p, 'register')}>
                                            Register
                                        </Button>
                                    )}
                                    <Button disabled={!event.attendance_open || !!p.in} onClick={() => act(p, 'in')}>
                                        Clock in
                                    </Button>
                                    <Button variant="secondary" disabled={!event.attendance_open || !p.in || !!p.out} onClick={() => act(p, 'out')}>
                                        Clock out
                                    </Button>
                                </div>
                            </article>
                        ))}
                    </section>
                )}
                <Card title="People">
                    <div className="grid gap-3">
                        <div className="flex flex-wrap gap-1.5">
                            {filters.map((f) => (
                                <button key={f.k} type="button" className={`rounded-full border px-3 py-1 text-xs font-medium ${filter === f.k ? 'border-ink bg-ink text-white' : 'border-line hover:bg-surface-muted'}`} onClick={() => setFilter(f.k)}>
                                    {f.l}
                                </button>
                            ))}
                        </div>
                        <Input value={search} onChange={setSearch} placeholder="Search name, student ID or ticket ID" />
                        <ul className="divide-y divide-line">
                            {filtered.map((p) => (
                                <li key={p.user_id}>
                                    <button
                                        type="button"
                                        className="flex w-full items-center gap-3 py-2.5 text-left"
                                        onClick={() => {
                                            const text = p.student_number ?? p.name;
                                            setQuery(text);
                                            lookup(text);
                                        }}
                                    >
                                        <Avatar src={p.photo_url} name={p.name} size="sm" />
                                        <span className="grid min-w-0 flex-1 text-sm">
                                            <strong className="truncate">{p.name}</strong>
                                            <span className="truncate text-xs text-ink-muted">
                                                {p.student_number}
                                                {p.order_id && ` · ${p.order_id}`}
                                            </span>
                                        </span>
                                        <span className="grid justify-items-end gap-1 text-xs">
                                            <Badge tone={STATUS[p.status].tone}>{STATUS[p.status].label}</Badge>
                                            <span className="text-ink-muted">
                                                {p.in ? `In ${p.in}` : 'Not in'}
                                                {p.out && ` · Out ${p.out}`}
                                            </span>
                                        </span>
                                    </button>
                                </li>
                            ))}
                            {!filtered.length && <li className="py-6 text-center text-sm text-ink-muted">Nobody here.</li>}
                        </ul>
                    </div>
                </Card>
                {can.manage && (
                    <div className="grid gap-5 lg:grid-cols-2">
                        <Card title="Student helpers">
                            <p className="mb-3 text-sm text-ink-muted">Helpers can scan tickets and clock people in and out for this event only.</p>
                            <ul className="mb-3 grid gap-2">
                                {helpers.map((h) => (
                                    <li key={h.id} className="flex items-center gap-3">
                                        <Avatar src={h.photo_url} name={h.name} size="sm" />
                                        <span className="grid min-w-0 flex-1 text-sm">
                                            <strong className="truncate">{h.name}</strong>
                                            <span className="text-xs text-ink-muted">{h.student_number}</span>
                                        </span>
                                        <button type="button" className="text-xs text-danger" onClick={() => router.delete(`/events/${event.slug}/helpers/${h.id}`, { preserveScroll: true })}>
                                            Remove
                                        </button>
                                    </li>
                                ))}
                                {!helpers.length && <li className="text-sm text-ink-muted">No helpers yet.</li>}
                            </ul>
                            <form className="flex items-end gap-2" onSubmit={addHelper}>
                                <div className="flex-1">
                                    <PersonPicker ref={helperPicker} value={helper.data.student_number} onChange={(v) => helper.setData('student_number', v)} label="Add a helper (name, email or student ID)" error={helper.errors.student_number} />
                                </div>
                                <Button type="submit" variant="secondary" loading={helper.processing}>
                                    Add
                                </Button>
                            </form>
                        </Card>
                        {signOff && (
                            <Card title="Attendance list sign-off">
                                <ApprovalTracker progress={signOff.progress} steps={signOff.steps} />
                            </Card>
                        )}
                    </div>
                )}
            </div>
            <Modal open={signing} onClose={() => setSigning(false)} title="Sign the attendance list">
                <div className="grid gap-4">
                    <p className="text-sm text-ink-muted">{counts.in} present. Your signature goes on the official 03 Attendance List as “Prepared by”, then it goes to your Club Advisor.</p>
                    <SignaturePad value={sign.data.signature} onChange={(png, strokes) => sign.setData({ signature: png, signature_strokes: strokes })} label="Your signature" />
                    <Button disabled={!sign.data.signature} loading={sign.processing} onClick={submitList}>
                        Sign &amp; submit
                    </Button>
                </div>
            </Modal>
        </AppLayout>
    );
}
