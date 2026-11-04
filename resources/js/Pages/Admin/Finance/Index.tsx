import MoneyChart from '@/Components/charts/MoneyChart';
import Badge from '@/Components/ui/Badge';
import Icon from '@/Components/ui/Icon';
import Select from '@/Components/ui/Select';
import Table from '@/Components/ui/Table';
import PortalLayout from '@/Layouts/PortalLayout';
import { dateTime, rm } from '@/lib/format';
import { searchTokens } from '@/lib/search';
import { Head, Link, router } from '@inertiajs/react';
import { Fragment, useEffect, useRef, useState } from 'react';

interface Transaction {
    key: string;
    order_id: string | null;
    kind: string;
    club: string;
    club_slug: string;
    purpose: string;
    name: string | null;
    student_number: string | null;
    amount_sen: number;
    direction: 'in' | 'out';
    method: string | null;
    status: string;
    reference: string | null;
    at: string;
    receipt_url: string | null;
}
interface ClubBalance {
    slug: string;
    name: string;
    ready: boolean;
    balance_sen: number;
    last_entry: string | null;
}
interface FinanceProps {
    q: string;
    transactions: Transaction[];
    clubs: ClubBalance[];
    money: { monthly: { key: string; label: string; income_sen: number; expense_sen: number }[]; yearly: { key: string; income_sen: number; expense_sen: number }[] };
    moneyClub: string | null;
}
const COLUMNS = [
    { key: 'name' as const, label: 'Club', sortable: true },
    { key: 'ready' as const, label: 'Ledger' },
    { key: 'balance_sen' as const, label: 'Balance', sortable: true },
    { key: 'last_entry' as const, label: 'Last entry', sortable: true },
];
const tone = (s: string): 'success' | 'warning' | 'danger' | 'info' | 'neutral' =>
    ['confirmed', 'auto_confirmed', 'recorded'].includes(s) ? 'success' : s === 'rejected' ? 'danger' : s === 'pending_review' || s === 'reading' ? 'info' : 'warning';

/** SLD finance: find any transaction by any part of its ID, money in and out, and every club's balance. */
export default function Index({ q: initial, transactions, clubs, money, moneyClub }: FinanceProps) {
    // Searches as you type: no Enter, no button.
    const [q, setQ] = useState(initial);
    const [searching, setSearching] = useState(false);
    const first = useRef(true);
    useEffect(() => {
        if (first.current) {
            first.current = false;
            return;
        }
        const timer = setTimeout(
            () =>
                router.get(
                    '/admin/finance',
                    { ...(q.trim() ? { q: q.trim() } : {}), ...(moneyClub ? { club: moneyClub } : {}) },
                    { only: ['q', 'transactions'], preserveState: true, preserveScroll: true, replace: true, onStart: () => setSearching(true), onFinish: () => setSearching(false) },
                ),
            300,
        );
        return () => clearTimeout(timer);
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, [q]);
    const moneyFor = (club: string): void =>
        router.get('/admin/finance', { ...(initial ? { q: initial } : {}), ...(club ? { club } : {}) }, { only: ['money', 'moneyClub'], preserveState: true, preserveScroll: true, replace: true });
    const tokens = searchTokens(initial);
    /** Splits an ID into its parts and marks the ones the search matched. */
    const parts = (id: string): { text: string; hit: boolean }[] => id.split('_').map((text) => ({ text, hit: tokens.some((t) => text.replace(/-/g, '').includes(t)) }));

    return (
        <PortalLayout title="Club finance">
            <Head title="Club finance" />
            <section className="grid gap-3">
                <div className="flex items-center gap-3 rounded-card border border-line bg-surface px-4 shadow-card focus-within:border-primary focus-within:ring-2 focus-within:ring-primary/30">
                    <Icon name="search" size={20} className="text-ink-muted" />
                    <input
                        value={q}
                        onChange={(e) => setQ(e.target.value)}
                        type="search"
                        className="h-12 flex-1 bg-transparent text-[15px] outline-none"
                        placeholder="Find a transaction: any part of the ID, e.g. 2026-11-01 14:30 jinzhou"
                        aria-label="Find a transaction"
                    />
                    {searching && <span className="size-4 animate-spin rounded-full border-2 border-primary border-t-transparent" aria-hidden="true" />}
                </div>
                <p className="text-xs text-ink-muted">
                    IDs read <span className="font-mono">CLUB_EVENT or MEMBERSHIPDRIVE / NORMALREGISTER_STUDENTID_NAME_YYYYMMDD_HHMM_TRANSACTION</span>. Type any parts in any order: club, event, student ID, name, date
                    (2026-11-01 or 1/11/2026), time (14:30) or bank reference.
                </p>
                {initial && (
                    <>
                        <p className="text-sm text-ink-muted">
                            {transactions.length} {transactions.length === 1 ? 'match' : 'matches'}
                            {transactions.length >= 50 ? ' (first 50, add more words to narrow)' : ''}
                        </p>
                        <ul className="grid gap-2">
                            {transactions.map((t) => (
                                <li key={t.key} className="fade-up grid gap-2 rounded-card border border-line bg-surface p-3 shadow-card sm:grid-cols-[1fr_auto] sm:items-center">
                                    <div className="grid min-w-0 gap-1">
                                        {t.order_id && (
                                            <p className="flex flex-wrap gap-x-0.5 break-all font-mono text-xs leading-5">
                                                {parts(t.order_id).map((part, i) => (
                                                    <Fragment key={i}>
                                                        {i > 0 && <span className="text-ink-muted">_</span>}
                                                        {part.hit ? <mark className="rounded bg-primary/20 px-0.5 text-ink">{part.text}</mark> : <span>{part.text}</span>}
                                                    </Fragment>
                                                ))}
                                            </p>
                                        )}
                                        <p className="text-sm">
                                            <strong>{t.name ?? t.purpose}</strong>{' '}
                                            <span className="text-ink-muted">
                                                · {t.club}
                                                {t.name && ` · ${t.purpose}`} · {t.at.length > 10 ? dateTime(t.at) : t.at}
                                            </span>
                                        </p>
                                    </div>
                                    <div className="flex flex-wrap items-center gap-2 sm:justify-end">
                                        <span className={`font-semibold tabular-nums ${t.direction === 'out' ? 'text-danger' : ''}`}>
                                            {t.direction === 'out' ? '−' : ''}
                                            {rm(t.amount_sen)}
                                        </span>
                                        {t.method && <Badge tone="neutral">{t.method}</Badge>}
                                        <Badge tone={tone(t.status)}>{t.status.replace('_', ' ')}</Badge>
                                        {t.receipt_url && (
                                            <a href={t.receipt_url} target="_blank" rel="noopener" className="text-sm font-medium text-primary">
                                                Receipt
                                            </a>
                                        )}
                                        <Link href={`/clubs/${t.club_slug}/finance`} className="text-sm font-medium text-primary">
                                            Ledger
                                        </Link>
                                    </div>
                                </li>
                            ))}
                        </ul>
                        {!transactions.length && <p className="rounded-card border border-dashed border-line p-6 text-center text-sm text-ink-muted">No transaction matches every word.</p>}
                    </>
                )}
            </section>
            <section className="mt-8 grid gap-3">
                <div className="flex flex-wrap items-end justify-between gap-3">
                    <h2 className="text-lg font-semibold">Money in and out{moneyClub ? ` · ${clubs.find((c) => c.slug === moneyClub)?.name ?? ''}` : ' · all clubs'}</h2>
                    <Select value={moneyClub ?? ''} onChange={moneyFor} label="Club" options={[{ value: '', label: 'All clubs' }, ...clubs.map((c) => ({ value: c.slug, label: c.name }))]} />
                </div>
                <div className="grid gap-4 lg:grid-cols-2">
                    <MoneyChart title="This session, month by month" rows={money.monthly} />
                    <MoneyChart title="Year by year" rows={money.yearly} />
                </div>
                <p className="text-xs text-ink-muted">Fund withdrawals are advances, not expenses, so they are not counted here; the purchases they pay for are.</p>
            </section>
            <section className="mt-8 grid gap-3">
                <h2 className="text-lg font-semibold">All clubs · balances</h2>
                <Table
                    columns={COLUMNS}
                    rows={clubs}
                    rowKey="slug"
                    cells={{
                        name: (row) => (
                            <Link href={`/clubs/${row.slug}/finance`} className="font-medium hover:underline">
                                {row.name}
                            </Link>
                        ),
                        ready: (row) => <Badge tone={row.ready ? 'success' : 'warning'}>{row.ready ? 'Open' : 'Needs opening balance'}</Badge>,
                        balance_sen: (row) => <span className={`tabular-nums ${row.balance_sen < 0 ? 'text-danger' : ''}`}>{rm(row.balance_sen)}</span>,
                    }}
                />
            </section>
        </PortalLayout>
    );
}
