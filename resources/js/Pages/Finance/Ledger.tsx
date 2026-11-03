import ApprovalTracker from '@/Components/forms/ApprovalTracker';
import Badge, { type BadgeTone } from '@/Components/ui/Badge';
import Button from '@/Components/ui/Button';
import Card from '@/Components/ui/Card';
import Checkbox from '@/Components/ui/Checkbox';
import EmptyState from '@/Components/ui/EmptyState';
import FileUpload from '@/Components/ui/FileUpload';
import Icon from '@/Components/ui/Icon';
import Input from '@/Components/ui/Input';
import Modal from '@/Components/ui/Modal';
import Select from '@/Components/ui/Select';
import AppLayout from '@/Layouts/AppLayout';
import { copy } from '@/lib/clone';
import { date, rm } from '@/lib/format';
import type { ApprovalProgress } from '@/types';
import { Head, Link, router, useForm } from '@inertiajs/react';
import { useEffect, useRef, useState, type FormEvent } from 'react';

interface Row {
    id: number;
    date: string;
    description: string;
    order_id: string | null;
    income_sen: number;
    expense_sen: number;
    balance_sen: number;
    category: string;
    event_id: number | null;
    event: string | null;
    method: string | null;
    handled_by: string | null;
    receipt_url: string | null;
    payment_receipt_url: string | null;
    photo_url: string | null;
    document_url: string | null;
}
interface Filters {
    session?: string;
    category?: string;
    event_id?: number | string;
    method?: string;
    search?: string;
    from?: string;
    to?: string;
    sort?: string;
}
interface LedgerProps {
    club: { slug: string; name: string };
    ready: boolean;
    opening: { amount_sen: number; as_of: string; note: string | null } | null;
    report: {
        session: string;
        brought_forward_sen: number;
        closing_sen: number;
        totals: { income_sen: number; expense_sen: number };
        by_category: { key: string; label: string; income_sen: number; expense_sen: number; count: number }[];
        by_event: { event_id: number; event: string; slug: string; income_sen: number; expense_sen: number; count: number }[];
        rows: Row[];
        filtered: boolean;
    };
    filters: Filters;
    sessions: string[];
    categories: Record<string, string>;
    methods: Record<string, string>;
    clubEvents: { value: number; label: string }[];
    balance_sen: number;
    position: { fund_sen: number; advances_sen: number; total_sen: number; by_event: { event_id: number | null; event: string | null; advanced_sen: number; spent_sen: number; outstanding_sen: number }[] };
    payees: { id: number; kind: string; name: string; bank_name: string; account: string }[];
    requests: { id: number; event: string | null; purpose: string | null; total_sen: number; status: string; progress: ApprovalProgress; stage: string | null; scope: string }[];
    events: { id: number; name: string }[];
    fee: { amount_sen: number; payment_url: string | null; account_name: string | null; approved: boolean; qr: { path: string; url: string }[] } | null;
    can: { edit: boolean; sld: boolean };
    workbook: Record<'session' | 'all', { key: string; title: string; count: number }[]>;
}
const TONE: Record<string, BadgeTone> = { membership: 'success', event: 'info', withdrawal: 'warning', other: 'neutral' };
const net = (r: { income_sen: number; expense_sen: number }): number => r.income_sen - r.expense_sen;
const options = (rec: Record<string, string>) => [{ value: '', label: 'All' }, ...Object.entries(rec).map(([value, label]) => ({ value, label }))];
const query = (f: Filters): Record<string, string> => Object.fromEntries(Object.entries(f).filter(([, v]) => v !== undefined && v !== null && v !== '').map(([k, v]) => [k, String(v)]));
const SORTS = [
    { value: 'date_asc', label: 'Oldest first' },
    { value: 'date_desc', label: 'Newest first' },
    { value: 'amount_desc', label: 'Largest first' },
    { value: 'amount_asc', label: 'Smallest first' },
];
const stat = 'rounded-card border border-line bg-surface p-4 shadow-card';
const evidence = 'inline-flex items-center gap-1 font-medium text-brand-blue';

/** A club's finance: money position, the session at a glance, filterable ledger with evidence, and the forms around it. */
export default function Ledger(props: LedgerProps) {
    const { club, ready, opening, report, sessions, categories, methods, clubEvents, position, payees, requests, fee, can, workbook } = props;
    const url = `/clubs/${club.slug}/finance`;
    // Filters live in the address bar, so a filtered view can be shared and the export matches the screen.
    const [f, setF] = useState<Filters>(() => ({ category: '', event_id: '', method: '', search: '', from: '', to: '', sort: 'date_asc', session: report.session, ...copy(props.filters) }));
    const apply = (changes: Filters = {}): void => {
        const next = { ...f, ...changes };
        setF(next);
        router.get(url, query(next), { preserveScroll: true, preserveState: true, replace: true, only: ['report', 'filters'] });
    };
    const first = useRef(true);
    useEffect(() => {
        if (first.current) {
            first.current = false;
            return;
        }
        const timer = setTimeout(() => router.get(url, query(f), { preserveScroll: true, preserveState: true, replace: true, only: ['report', 'filters'] }), 350);
        return () => clearTimeout(timer);
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, [f.search]);
    const clear = (): void => router.get(url, { session: f.session ?? '' }, { preserveScroll: true });
    const exportUrl = (format: string): string => `${url}/export/${format}?${new URLSearchParams(query(f))}`;

    // Workbook export: Overview sheet first, then one sheet per ticked section (drive, normal, each event, other).
    const [exporting, setExporting] = useState(false);
    const [period, setPeriod] = useState<'session' | 'all'>('session');
    const [picked, setPicked] = useState<string[]>([]);
    const bookChoices = workbook[period];
    const openExport = (p: 'session' | 'all' = period): void => {
        setPeriod(p);
        setPicked(workbook[p].filter((c) => c.count > 0).map((c) => c.key));
        setExporting(true);
    };
    const togglePick = (key: string, on: boolean): void => setPicked(on ? [...new Set([...picked, key])] : picked.filter((k) => k !== key));
    const workbookUrl = (format: string): string => {
        const q = new URLSearchParams({ period, ...(period === 'session' ? { session: f.session ?? '' } : {}) });
        (picked.length ? picked : ['none']).forEach((k) => q.append('sections[]', k));
        return `${url}/workbook/${format}?${q}`;
    };
    const showBalance = !report.filtered && (f.sort ?? 'date_asc') === 'date_asc';

    const openingForm = useForm({ amount_rm: String(opening ? opening.amount_sen / 100 : 0), as_of: opening?.as_of ?? new Date().toISOString().slice(0, 10), note: opening?.note ?? '' });
    const entry = useForm({ date: new Date().toISOString().slice(0, 10), description: '', kind: 'expense', amount_rm: '', event_id: '' as number | string, method: 'cash', receipt: [] as File[], original: false });
    const addEntry = (e: FormEvent): void => {
        e.preventDefault();
        entry.transform((d) => ({ ...d, receipt: d.receipt[0] ?? null }));
        entry.post(`${url}/entries`, { preserveScroll: true, onSuccess: () => entry.reset('description', 'amount_rm', 'receipt', 'original') });
    };
    const [attaching, setAttaching] = useState<number | null>(null);
    const attachForm = useForm({ receipt: [] as File[] });
    const attach = (e: FormEvent): void => {
        e.preventDefault();
        attachForm.transform((d) => ({ receipt: d.receipt[0] ?? null }));
        attachForm.post(`${url}/entries/${attaching}/receipt`, {
            preserveScroll: true,
            forceFormData: true,
            onSuccess: () => {
                setAttaching(null);
                attachForm.reset();
            },
        });
    };
    const payee = useForm({ kind: 'vendor', name: '', email: '', contact_no: '', bank_name: '', account_holder: '', account_number: '' });
    // Membership fee for this session: any committee member sets it, SLD approves before students can join.
    const feeForm = useForm({ amount_rm: String((fee?.amount_sen ?? 0) / 100), payment_url: fee?.payment_url ?? '', account_name: fee?.account_name ?? '', qr: [] as File[], remove_qr: [] as string[] });
    const toggleFeeQr = (path: string): void => feeForm.setData('remove_qr', feeForm.data.remove_qr.includes(path) ? feeForm.data.remove_qr.filter((p) => p !== path) : [...feeForm.data.remove_qr, path]);
    const saveFee = (e: FormEvent): void => {
        e.preventDefault();
        feeForm.post(`/clubs/${club.slug}/fee`, { preserveScroll: true, forceFormData: true, onSuccess: () => feeForm.reset('qr', 'remove_qr') });
    };
    const field = <K extends keyof typeof payee.data>(key: K) => ({ value: payee.data[key], onChange: (v: string) => payee.setData(key, v as never), error: payee.errors[key] });

    return (
        <AppLayout wide>
            <Head title={`Finance · ${club.name}`} />
            <div className="grid gap-6">
                <header className="flex flex-wrap items-end justify-between gap-3">
                    <div>
                        <h1 className="text-xl font-bold">Finance · {club.name}</h1>
                        <Link href={`/clubs/${club.slug}`} className="text-sm text-brand-blue">
                            Back to club
                        </Link>
                    </div>
                    <div className="sm:text-right">
                        <p className="text-xs uppercase tracking-wide text-ink-muted">Total club money</p>
                        <p className={`text-3xl font-bold tabular-nums ${position.total_sen < 0 ? 'text-danger' : ''}`}>{rm(position.total_sen)}</p>
                    </div>
                </header>
                {/* Where the money is: the fund held by QIU, and advances (fund withdrawals) not yet spent. */}
                <section className="grid gap-3 sm:grid-cols-3">
                    <div className={stat}>
                        <p className="text-xs text-ink-muted">Fund balance (held by QIU)</p>
                        <p className="text-xl font-bold tabular-nums">{rm(position.fund_sen)}</p>
                    </div>
                    <div className={stat}>
                        <p className="text-xs text-ink-muted">Advances not yet spent</p>
                        <p className={`text-xl font-bold tabular-nums ${position.advances_sen ? 'text-warning' : ''}`}>{rm(position.advances_sen)}</p>
                        {position.by_event.some((a) => a.outstanding_sen) && (
                            <ul className="mt-1 grid gap-0.5 text-xs text-ink-muted">
                                {position.by_event
                                    .filter((x) => x.outstanding_sen)
                                    .map((a) => (
                                        <li key={a.event_id ?? 0}>
                                            {a.event ?? 'Club'}: {rm(a.outstanding_sen)} of {rm(a.advanced_sen)}
                                        </li>
                                    ))}
                            </ul>
                        )}
                    </div>
                    <div className={stat}>
                        <p className="text-xs text-ink-muted">Total club money</p>
                        <p className="text-xl font-bold tabular-nums">{rm(position.total_sen)}</p>
                        <p className="text-xs text-ink-muted">Fund + advances. Advances are not expenses; their purchases are.</p>
                    </div>
                </section>
                {can.sld && (
                    <Card title="Opening balance (SLD only)">
                        <form
                            className="grid gap-3 sm:grid-cols-[10rem_12rem_1fr_auto] sm:items-end"
                            onSubmit={(e) => {
                                e.preventDefault();
                                openingForm.post(`/clubs/${club.slug}/finance/opening`, { preserveScroll: true });
                            }}
                        >
                            <Input value={openingForm.data.amount_rm} onChange={(v) => openingForm.setData('amount_rm', v)} type="number" label="Amount (RM)" error={openingForm.errors.amount_rm} />
                            <Input value={openingForm.data.as_of} onChange={(v) => openingForm.setData('as_of', v)} type="date" label="As of" error={openingForm.errors.as_of} />
                            <Input value={openingForm.data.note} onChange={(v) => openingForm.setData('note', v)} label="Note" />
                            <Button type="submit" loading={openingForm.processing}>
                                {opening ? 'Update' : 'Open ledger'}
                            </Button>
                        </form>
                        <p className="mt-2 text-xs text-ink-muted">Every change is recorded in the audit log.</p>
                    </Card>
                )}
                {!ready ? (
                    <EmptyState title="Finance not opened yet" description="SLD must enter this club's opening balance before the ledger can be used." />
                ) : (
                    <>
                        <section className="grid gap-3">
                            <div className="flex flex-wrap items-end justify-between gap-3">
                                <div className="flex items-end gap-2">
                                    <Select value={f.session ?? ''} onChange={(v) => apply({ session: v })} label="Session" options={sessions.map((s) => ({ value: s, label: s }))} />
                                </div>
                                <div className="flex flex-wrap gap-2">
                                    <Button variant="secondary" onClick={() => openExport()}>
                                        <Icon name="grid" size={16} /> Export workbook
                                    </Button>
                                    <a href={exportUrl('xlsx')}>
                                        <Button variant="secondary">
                                            <Icon name="file" size={16} /> 02 Finance Record (AGM)
                                        </Button>
                                    </a>
                                    <a href={exportUrl('pdf')}>
                                        <Button variant="secondary">PDF</Button>
                                    </a>
                                    {can.edit && (
                                        <Link href={`/clubs/${club.slug}/payments/review`}>
                                            <Button variant="secondary">
                                                <Icon name="check" size={16} /> Payment review
                                            </Button>
                                        </Link>
                                    )}
                                    <Link href={`/clubs/${club.slug}/fund-requests/create`}>
                                        <Button>＋ Fund withdrawal (07)</Button>
                                    </Link>
                                </div>
                            </div>
                            <div className="grid grid-cols-2 gap-3 lg:grid-cols-4">
                                <div className={stat}>
                                    <p className="text-xs text-ink-muted">Brought forward (1 Jul)</p>
                                    <p className="text-xl font-bold tabular-nums">{rm(report.brought_forward_sen)}</p>
                                </div>
                                <div className={stat}>
                                    <p className="text-xs text-ink-muted">Income {report.session}</p>
                                    <p className="text-xl font-bold tabular-nums text-success">{rm(report.totals.income_sen)}</p>
                                </div>
                                <div className={stat}>
                                    <p className="text-xs text-ink-muted">Expenses {report.session}</p>
                                    <p className="text-xl font-bold tabular-nums text-danger">{rm(report.totals.expense_sen)}</p>
                                </div>
                                <div className={stat}>
                                    <p className="text-xs text-ink-muted">Closing balance</p>
                                    <p className={`text-xl font-bold tabular-nums ${report.closing_sen < 0 ? 'text-danger' : ''}`}>{rm(report.closing_sen)}</p>
                                </div>
                            </div>
                            <div className="grid gap-3 lg:grid-cols-2">
                                <Card title="By type">
                                    <ul className="grid gap-1 text-sm">
                                        {report.by_category.map((c) => (
                                            <li key={c.key}>
                                                <button
                                                    type="button"
                                                    className={`grid w-full grid-cols-[1fr_auto_auto] items-center gap-3 rounded-control px-2 py-2 text-left hover:bg-surface-muted ${f.category === c.key ? 'bg-surface-muted' : ''}`}
                                                    onClick={() => apply({ category: f.category === c.key ? '' : c.key, event_id: '' })}
                                                >
                                                    <span className="flex items-center gap-2">
                                                        <Badge tone={TONE[c.key] ?? 'neutral'}>{c.label}</Badge>
                                                        <span className="text-xs text-ink-muted">
                                                            {c.count} {c.count === 1 ? 'item' : 'items'}
                                                        </span>
                                                    </span>
                                                    <span className="text-right tabular-nums text-success">{c.income_sen ? rm(c.income_sen) : ''}</span>
                                                    <span className="w-24 text-right tabular-nums text-danger">{c.expense_sen ? `− ${rm(c.expense_sen)}` : ''}</span>
                                                </button>
                                            </li>
                                        ))}
                                    </ul>
                                </Card>
                                <Card
                                    title="By event"
                                    actions={
                                        <Link href={`/clubs/${club.slug}/finance/membership?session=${encodeURIComponent(report.session)}`} className="text-sm font-medium text-brand-blue">
                                            Membership fees →
                                        </Link>
                                    }
                                >
                                    {report.by_event.length ? (
                                        <ul className="grid gap-1 text-sm">
                                            {report.by_event.map((e) => (
                                                <li key={e.event_id} className="grid grid-cols-[1fr_auto] items-center gap-1">
                                                    <button
                                                        type="button"
                                                        className={`grid w-full grid-cols-[1fr_auto] items-center gap-3 rounded-control px-2 py-2 text-left hover:bg-surface-muted ${Number(f.event_id) === e.event_id ? 'bg-surface-muted' : ''}`}
                                                        onClick={() => apply({ event_id: Number(f.event_id) === e.event_id ? '' : e.event_id, category: '' })}
                                                    >
                                                        <span className="min-w-0">
                                                            <span className="block truncate font-medium">{e.event}</span>
                                                            <span className="text-xs text-ink-muted">
                                                                {e.count} {e.count === 1 ? 'item' : 'items'} · in {rm(e.income_sen)} · out {rm(e.expense_sen)}
                                                            </span>
                                                        </span>
                                                        <span className={`text-right font-semibold tabular-nums ${net(e) < 0 ? 'text-danger' : 'text-success'}`}>{rm(net(e))}</span>
                                                    </button>
                                                    <Link href={`/clubs/${club.slug}/finance/events/${e.slug}`} className="rounded-control px-2 py-2 text-xs font-medium text-brand-blue hover:bg-surface-muted" aria-label={`Who paid for ${e.event}`}>
                                                        Who paid →
                                                    </Link>
                                                </li>
                                            ))}
                                        </ul>
                                    ) : (
                                        <p className="text-sm text-ink-muted">No event transactions this session yet. Fund withdrawals and event expenses appear here automatically.</p>
                                    )}
                                </Card>
                            </div>
                        </section>
                        <section className="grid gap-3">
                            <div className="grid gap-3 rounded-card border border-line bg-surface p-4 shadow-card sm:grid-cols-2 lg:grid-cols-4">
                                <div className="sm:col-span-2">
                                    <Input value={f.search ?? ''} onChange={(v) => setF({ ...f, search: v })} label="Search" placeholder="Description, event, name or order ID" />
                                </div>
                                <Select value={f.category ?? ''} onChange={(v) => apply({ category: v })} label="Type" options={options(categories)} />
                                <Select value={f.event_id ?? ''} onChange={(v) => apply({ event_id: v })} label="Event" options={[{ value: '', label: 'All' }, ...clubEvents]} />
                                <Select value={f.method ?? ''} onChange={(v) => apply({ method: v })} label="Paid by" options={options(methods)} />
                                <Input value={f.from ?? ''} onChange={(v) => apply({ from: v })} type="date" label="From" />
                                <Input value={f.to ?? ''} onChange={(v) => apply({ to: v })} type="date" label="To" />
                                <Select value={f.sort ?? 'date_asc'} onChange={(v) => apply({ sort: v })} label="Sort" options={SORTS} />
                            </div>
                            <p className="flex flex-wrap items-center gap-2 text-sm text-ink-muted">
                                {report.rows.length} {report.rows.length === 1 ? 'transaction' : 'transactions'}
                                {report.filtered && (
                                    <>
                                        {' match · '}
                                        <button type="button" className="font-medium text-brand-blue" onClick={clear}>
                                            Clear filters
                                        </button>
                                    </>
                                )}{' '}
                                · the Excel/PDF export prints exactly this view.
                            </p>
                            <div className="overflow-x-auto rounded-card border border-line bg-surface shadow-card">
                                <table className="w-full text-sm">
                                    <thead className="bg-surface-muted text-left text-xs uppercase tracking-wide text-ink-muted">
                                        <tr>
                                            <th className="px-3 py-3 font-medium">Date</th>
                                            <th className="px-3 py-3 font-medium">Description</th>
                                            <th className="px-3 py-3 font-medium">For</th>
                                            <th className="px-3 py-3 font-medium">Paid by · received by</th>
                                            <th className="px-3 py-3 text-right font-medium">Income</th>
                                            <th className="px-3 py-3 text-right font-medium">Expense</th>
                                            {showBalance && <th className="px-3 py-3 text-right font-medium">Balance</th>}
                                            <th className="px-3 py-3 font-medium">Evidence</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        {report.rows.map((r) => (
                                            <tr key={r.id} className="border-t border-line align-top">
                                                <td className="whitespace-nowrap px-3 py-2.5 tabular-nums">{date(r.date)}</td>
                                                <td className="min-w-44 px-3 py-2.5">
                                                    {r.description}
                                                    {r.order_id && <span className="mt-0.5 block font-mono text-xs text-ink-muted">{r.order_id}</span>}
                                                </td>
                                                <td className="px-3 py-2.5">
                                                    <Badge tone={TONE[r.category] ?? 'neutral'}>{categories[r.category] ?? r.category}</Badge>
                                                    {r.event && <span className="mt-1 block text-xs text-ink-muted">{r.event}</span>}
                                                </td>
                                                <td className="whitespace-nowrap px-3 py-2.5 text-xs">
                                                    {r.method ? (methods[r.method] ?? r.method) : '—'}
                                                    {r.handled_by && <span className="block text-ink-muted">{r.handled_by}</span>}
                                                </td>
                                                <td className="whitespace-nowrap px-3 py-2.5 text-right tabular-nums text-success">{r.income_sen ? rm(r.income_sen) : ''}</td>
                                                <td className="whitespace-nowrap px-3 py-2.5 text-right tabular-nums text-danger">{r.expense_sen ? rm(r.expense_sen) : ''}</td>
                                                {showBalance && <td className="whitespace-nowrap px-3 py-2.5 text-right font-medium tabular-nums">{rm(r.balance_sen)}</td>}
                                                <td className="whitespace-nowrap px-3 py-2.5 text-xs">
                                                    <div className="grid gap-1">
                                                        {r.photo_url && (
                                                            <a href={r.photo_url} target="_blank" rel="noopener" className={evidence} title="Photo taken at the cash handover">
                                                                <Icon name="camera" size={14} /> Photo
                                                            </a>
                                                        )}
                                                        {r.payment_receipt_url && (
                                                            <a href={r.payment_receipt_url} target="_blank" rel="noopener" className={evidence} title="The student's online payment receipt">
                                                                <Icon name="file" size={14} /> Receipt
                                                            </a>
                                                        )}
                                                        {r.receipt_url && (
                                                            <a href={r.receipt_url} target="_blank" rel="noopener" className={evidence}>
                                                                <Icon name="file" size={14} /> Receipt
                                                            </a>
                                                        )}
                                                        {r.document_url && (
                                                            <Link href={r.document_url} className={evidence}>
                                                                <Icon name="check" size={14} /> 07 form
                                                            </Link>
                                                        )}
                                                        {can.edit && !r.receipt_url && !!r.expense_sen && (
                                                            <button type="button" className="text-left font-medium text-warning" onClick={() => setAttaching(r.id)}>
                                                                ＋ Receipt
                                                            </button>
                                                        )}
                                                        {!r.photo_url && !r.payment_receipt_url && !r.receipt_url && !r.document_url && !(can.edit && r.expense_sen) && <span className="text-ink-muted">—</span>}
                                                    </div>
                                                </td>
                                            </tr>
                                        ))}
                                        {!report.rows.length && (
                                            <tr>
                                                <td colSpan={8} className="px-3 py-8 text-center text-ink-muted">
                                                    No transactions match.
                                                </td>
                                            </tr>
                                        )}
                                    </tbody>
                                </table>
                            </div>
                        </section>
                        {can.edit && (
                            <Card title="Add a transaction">
                                <p className="mb-3 text-sm text-ink-muted">Membership fees and approved fund withdrawals are recorded automatically. Add anything else here, under the event it belongs to.</p>
                                <form className="grid gap-3" onSubmit={addEntry}>
                                    <div className="grid gap-3 sm:grid-cols-2 lg:grid-cols-[10rem_1fr_9rem_10rem]">
                                        <Input value={entry.data.date} onChange={(v) => entry.setData('date', v)} type="date" label="Date" error={entry.errors.date} />
                                        <Input value={entry.data.description} onChange={(v) => entry.setData('description', v)} label="Description" error={entry.errors.description} />
                                        <Select
                                            value={entry.data.kind}
                                            onChange={(v) => entry.setData('kind', v)}
                                            label="Type"
                                            options={[
                                                { value: 'expense', label: 'Expense' },
                                                { value: 'income', label: 'Income' },
                                            ]}
                                        />
                                        <Input value={entry.data.amount_rm} onChange={(v) => entry.setData('amount_rm', v)} type="number" label="Amount (RM)" error={entry.errors.amount_rm} />
                                    </div>
                                    <div className="grid gap-3 sm:grid-cols-2">
                                        <Select value={entry.data.event_id} onChange={(v) => entry.setData('event_id', v)} label="For event (optional)" options={[{ value: '', label: 'Not for an event' }, ...clubEvents]} error={entry.errors.event_id} />
                                        <Select value={entry.data.method} onChange={(v) => entry.setData('method', v)} label="Paid by" options={Object.entries(methods).map(([value, label]) => ({ value, label }))} />
                                    </div>
                                    {entry.data.kind === 'expense' && (
                                        <>
                                            <FileUpload files={entry.data.receipt} onChange={(fl) => entry.setData('receipt', fl)} label="Receipt (required for expenses)" accept="image/jpeg,image/png,image/webp,application/pdf" error={entry.errors.receipt} />
                                            <Checkbox checked={entry.data.original} onChange={(v) => entry.setData('original', v)} label="This is the original receipt (bank-transfer screenshots are not accepted)." />
                                            {entry.errors.original && <p className="text-xs text-danger">{entry.errors.original}</p>}
                                        </>
                                    )}
                                    <div>
                                        <Button type="submit" loading={entry.processing}>
                                            Add transaction
                                        </Button>
                                    </div>
                                </form>
                            </Card>
                        )}
                    </>
                )}
                {can.edit && (
                    <Card title="Membership fee & payment">
                        <p className="mb-3 text-sm text-ink-muted">
                            {!fee ? (
                                'No fee set for this session yet. Students can join once SLD approves it.'
                            ) : (
                                <>
                                    Current fee {rm(fee.amount_sen)} · <Badge tone={fee.approved ? 'success' : 'warning'}>{fee.approved ? 'Approved by SLD' : 'Waiting for SLD'}</Badge>
                                </>
                            )}{' '}
                            Changing it sends it back to SLD for approval.
                        </p>
                        <form className="grid gap-3" onSubmit={saveFee}>
                            <div className="grid gap-3 sm:grid-cols-[10rem_1fr_1fr]">
                                <Input value={feeForm.data.amount_rm} onChange={(v) => feeForm.setData('amount_rm', v)} type="number" label="Fee (RM, 0 = free)" error={feeForm.errors.amount_rm} />
                                <Input value={feeForm.data.account_name} onChange={(v) => feeForm.setData('account_name', v)} label="Pay to (account name)" error={feeForm.errors.account_name} />
                                <Input value={feeForm.data.payment_url} onChange={(v) => feeForm.setData('payment_url', v)} label="Payment link" placeholder="https://…" error={feeForm.errors.payment_url} />
                            </div>
                            {!!fee?.qr.length && (
                                <div className="flex flex-wrap gap-2">
                                    {fee.qr.map((q) => {
                                        const removing = feeForm.data.remove_qr.includes(q.path);
                                        return (
                                            <span key={q.path} className="relative">
                                                <img src={q.url} alt="Payment QR" className={`size-20 rounded-control border border-line object-cover ${removing ? 'opacity-30' : ''}`} />
                                                <button type="button" className="absolute -right-1 -top-1 grid size-5 place-items-center rounded-full bg-ink text-xs text-white" aria-label={removing ? 'Keep this QR' : 'Remove this QR'} onClick={() => toggleFeeQr(q.path)}>
                                                    {removing ? '↺' : '×'}
                                                </button>
                                            </span>
                                        );
                                    })}
                                </div>
                            )}
                            {(fee?.qr.length ?? 0) - feeForm.data.remove_qr.length < 2 && (
                                <FileUpload files={feeForm.data.qr} onChange={(fl) => feeForm.setData('qr', fl)} multiple label="Your own payment QR codes (up to 2, optional — a QR of the link is generated anyway)" accept="image/png,image/jpeg,image/webp" error={feeForm.errors.qr} />
                            )}
                            <div>
                                <Button type="submit" loading={feeForm.processing}>
                                    Save and send to SLD
                                </Button>
                            </div>
                        </form>
                    </Card>
                )}
                <div className="grid gap-6 lg:grid-cols-2">
                    <Card title="Fund withdrawals (07)">
                        {!requests.length && <EmptyState title="No withdrawal requests yet" />}
                        <ul className="grid gap-3">
                            {requests.map((r) => (
                                <li key={r.id}>
                                    <Link href={`/withdrawals/${r.id}`} className="grid gap-2 rounded-control border border-line p-3 text-sm hover:shadow-card">
                                        <span className="flex justify-between gap-3">
                                            <span className="min-w-0">
                                                <Badge tone={r.scope === 'Event' ? 'info' : 'neutral'}>{r.scope}</Badge> <strong>{r.event ?? r.purpose}</strong>
                                            </span>
                                            <span className="tabular-nums">{rm(r.total_sen)}</span>
                                        </span>
                                        {!r.stage ? <ApprovalTracker progress={r.progress} compact /> : <span className={`text-xs font-semibold ${r.stage === 'Completed' ? 'text-success' : 'text-warning'}`}>{r.stage}</span>}
                                    </Link>
                                </li>
                            ))}
                        </ul>
                    </Card>
                    <Card title="Payees">
                        <ul className="mb-4 grid gap-1 text-sm">
                            {payees.map((p) => (
                                <li key={p.id} className="flex justify-between gap-3">
                                    <span>
                                        <strong>{p.name}</strong> <span className="text-ink-muted">({p.kind})</span>
                                    </span>
                                    <span className="text-ink-muted">
                                        {p.bank_name} {p.account}
                                    </span>
                                </li>
                            ))}
                            {!payees.length && <li className="text-ink-muted">No payees yet.</li>}
                        </ul>
                        {can.edit && (
                            <form
                                className="grid gap-3 sm:grid-cols-2"
                                onSubmit={(e) => {
                                    e.preventDefault();
                                    payee.post(`/clubs/${club.slug}/finance/payees`, { preserveScroll: true, onSuccess: () => payee.reset() });
                                }}
                            >
                                <Select
                                    value={payee.data.kind}
                                    onChange={(v) => payee.setData('kind', v)}
                                    label="Type"
                                    options={[
                                        { value: 'vendor', label: 'Vendor' },
                                        { value: 'treasurer', label: 'Treasurer account' },
                                    ]}
                                />
                                <Input {...field('name')} label="Individual/Company name" />
                                <Input {...field('email')} type="email" label="Email" />
                                <Input {...field('contact_no')} label="Contact no." />
                                <Input {...field('bank_name')} label="Bank" />
                                <Input {...field('account_holder')} label="Account holder" />
                                <Input {...field('account_number')} label="Account number" />
                                <Button type="submit" className="self-end" loading={payee.processing}>
                                    Save payee
                                </Button>
                            </form>
                        )}
                        <p className="mt-2 text-xs text-ink-muted">Bank details are encrypted and only printed on the official form.</p>
                    </Card>
                </div>
            </div>
            <Modal open={exporting} onClose={() => setExporting(false)} title="Export finance workbook">
                <div className="grid gap-4 text-sm">
                    <div className="grid grid-cols-2 gap-2" role="radiogroup" aria-label="Period">
                        {(
                            [
                                { key: 'session', label: `This session (${f.session})`, hint: 'The committee year, as for the AGM' },
                                { key: 'all', label: 'All transactions', hint: 'Every year since the ledger opened' },
                            ] as const
                        ).map((p) => (
                            <button
                                key={p.key}
                                type="button"
                                role="radio"
                                aria-checked={period === p.key}
                                className={`grid gap-0.5 rounded-control border p-3 text-left transition ${period === p.key ? 'border-brand-cyan bg-brand-cyan/10 ring-2 ring-brand-cyan/30' : 'border-line hover:bg-surface-muted'}`}
                                onClick={() => openExport(p.key)}
                            >
                                <span className="font-semibold">{p.label}</span>
                                <span className="text-xs text-ink-muted">{p.hint}</span>
                            </button>
                        ))}
                    </div>
                    <div className="grid gap-2">
                        <p className="font-medium">
                            Sheets <span className="font-normal text-ink-muted">(Overview is always first)</span>
                        </p>
                        {bookChoices.map((c, i) => (
                            <label key={c.key} className={`flex items-center gap-3 rounded-control border border-line px-3 py-2 ${c.count ? '' : 'opacity-50'}`}>
                                <input type="checkbox" className="size-4 accent-brand-cyan" checked={picked.includes(c.key)} onChange={(e) => togglePick(c.key, e.target.checked)} />
                                <span className="w-14 text-xs text-ink-muted">Sheet {i + 1}</span>
                                <span className="flex-1">{c.key.startsWith('event:') ? `Event · ${c.title}` : c.title}</span>
                                <span className="text-xs text-ink-muted">{c.count}</span>
                            </label>
                        ))}
                        <div className="flex gap-3 text-xs">
                            <button type="button" className="text-brand-blue" onClick={() => setPicked(bookChoices.map((c) => c.key))}>
                                All
                            </button>
                            <button type="button" className="text-brand-blue" onClick={() => setPicked([])}>
                                None (Overview only)
                            </button>
                        </div>
                    </div>
                    <div className="grid grid-cols-2 gap-2">
                        <a href={workbookUrl('xlsx')}>
                            <Button block>
                                <Icon name="file" size={16} /> Excel
                            </Button>
                        </a>
                        <a href={workbookUrl('pdf')}>
                            <Button block variant="secondary">
                                PDF
                            </Button>
                        </a>
                    </div>
                </div>
            </Modal>
            <Modal open={attaching !== null} onClose={() => setAttaching(null)} title="Add the original receipt">
                <form className="grid gap-4" onSubmit={attach}>
                    <FileUpload files={attachForm.data.receipt} onChange={(fl) => attachForm.setData('receipt', fl)} label="Receipt" accept="image/jpeg,image/png,image/webp,application/pdf" error={attachForm.errors.receipt} />
                    <Button type="submit" loading={attachForm.processing} disabled={!attachForm.data.receipt.length}>
                        Attach receipt
                    </Button>
                </form>
            </Modal>
        </AppLayout>
    );
}
