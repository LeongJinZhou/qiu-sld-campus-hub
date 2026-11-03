import CheckboxGroup from '@/Components/forms/CheckboxGroup';
import Badge, { type BadgeTone } from '@/Components/ui/Badge';
import Button from '@/Components/ui/Button';
import Drawer from '@/Components/ui/Drawer';
import FilterBar from '@/Components/ui/FilterBar';
import Icon from '@/Components/ui/Icon';
import Input from '@/Components/ui/Input';
import Modal from '@/Components/ui/Modal';
import Select from '@/Components/ui/Select';
import Textarea from '@/Components/ui/Textarea';
import AppLayout from '@/Layouts/AppLayout';
import PortalLayout from '@/Layouts/PortalLayout';
import { copy } from '@/lib/clone';
import { date } from '@/lib/format';
import { Head, Link, router, useForm } from '@inertiajs/react';
import { useEffect, useRef, useState, type FormEvent, type ReactNode } from 'react';

type Filters = Record<string, string | number | string[] | string[][] | undefined>;
interface ClubLine {
    club: string;
    club_id: number;
    club_slug: string;
    position: string;
    status: string;
    payment: string;
    method: string | null;
    collected_by: string | null;
    expires: string | null;
}
interface Row {
    id: number | string;
    clubs?: ClubLine[];
    club: string;
    name: string;
    email: string;
    student_number: string | null;
    programme_short: string | null;
    year_group: string | null;
    intake: string | null;
    status: string;
    position: string;
    joined: string | null;
    expires: string | null;
    grace_ends?: string | null;
    alumni_since?: string | null;
    manageable?: boolean;
    payment: string;
    method: string | null;
    collected_by: string | null;
    events: number;
    cca: number;
}
interface MembersProps {
    club: { slug: string; name: string } | null;
    portal?: boolean;
    canManage?: boolean;
    committeeFees?: { name: string; student_number: string | null; position: string | null; status: 'paid' | 'unpaid' | 'checking' | 'no_fee' }[];
    filters: Filters;
    counts: Record<string, number>;
    rows: Row[];
    pagination: { page: number; per_page: number; total: number; last_page: number };
    options: { positions: string[]; year_groups: string[]; programmes: string[]; intakes: string[]; collectors: string[]; sessions: string[]; clubs: { value: number; label: string }[]; categories: string[] };
    views: { id: number; name: string; filters: Filters }[];
}
type ManageAction = 'remind' | 'extend' | 'terminate' | 'alumni' | 'restore';
// Grace / termination / alumni: one dialog per action; terminate needs a reason, extend needs days.
const ACTIONS: Record<string, { key: ManageAction; label: string }[]> = {
    active: [
        { key: 'terminate', label: 'End membership' },
        { key: 'alumni', label: 'Mark alumni' },
    ],
    grace: [
        { key: 'remind', label: 'Remind' },
        { key: 'extend', label: 'Give more days' },
        { key: 'terminate', label: 'End now' },
        { key: 'alumni', label: 'Mark alumni' },
    ],
    terminated: [{ key: 'alumni', label: 'Mark alumni' }],
    alumni: [{ key: 'restore', label: 'Undo alumni' }],
};
const MANAGE_TEXT: Record<ManageAction, string> = {
    remind: 'Send a renewal reminder now.',
    extend: 'Give more days to renew. The member is told the new last day.',
    terminate: 'End this membership now. The member is told the reason and can rejoin later. Payments and CCA history are kept.',
    alumni: 'Mark as alumni (graduated): stays in the club with no fee and no renewal reminders.',
    restore: 'Undo the alumni mark: back to active while paid up, otherwise a fresh 7-day grace to renew.',
};
const FEE_TONE = { paid: 'success', unpaid: 'danger', checking: 'info', no_fee: 'neutral' } as const;
const FEE_LABEL = { paid: 'Paid', unpaid: 'Not paid', checking: 'Payment being checked', no_fee: 'No fee set' } as const;
const LISTS = ['status', 'position', 'payment', 'method', 'collected_by', 'year_group', 'programme_short', 'intake', 'club_id', 'category', 'club_status'] as const;
const SORT_FIELDS: Record<string, string> = { name: 'Name', student_number: 'Student ID', programme: 'Programme', year_group: 'Year group', status: 'Status', joined: 'Joined', expires: 'Expires', events: 'Events', cca: 'CCA', club: 'Club' };
const CARDS = [
    ['total', 'Total'],
    ['active', 'Active'],
    ['grace', 'Grace'],
    ['terminated', 'Terminated'],
    ['alumni', 'Alumni'],
    ['paid', 'Paid'],
    ['pending', 'Pending'],
    ['unpaid', 'Unpaid'],
] as const;
const TONE: Record<string, BadgeTone> = { active: 'success', grace: 'warning', terminated: 'danger', alumni: 'info', pending: 'neutral', paid: 'success', unpaid: 'danger', committee: 'info', advisor: 'neutral', exempt: 'neutral' };
const toOptions = (list: string[]): Record<string, string> => Object.fromEntries(list.map((v) => [v, v]));

// The drawer's state, rebuilt from what the server applied (club ids as numbers, so their boxes show ticked).
function fromServer(filters: Filters): Filters {
    const out: Filters = { ...(copy(filters) as Filters) };
    for (const k of LISTS) out[k] = Array.isArray(out[k]) ? ((out[k] as string[]).map((v) => (k === 'club_id' ? Number(v) : v)) as string[]) : [];
    delete out.sort;
    delete out.search;
    return out;
}

export default function Members({ club, portal = false, canManage, committeeFees = [], filters, counts, rows, pagination, options, views }: MembersProps) {
    // SLD stays inside the admin portal; the club's own committee and advisor use the club page.
    const url = club ? (portal ? `/admin/clubs/${club.slug}/members` : `/clubs/${club.slug}/members`) : '/admin/members';
    const [f, setF] = useState<Filters>(() => fromServer(filters));
    useEffect(() => setF(fromServer(filters)), [filters]);
    const [sort, setSort] = useState<string[][]>(Array.isArray(filters.sort) ? (filters.sort as string[][]) : [['name', 'asc']]);
    const [search, setSearch] = useState(typeof filters.search === 'string' ? filters.search : '');
    const [drawer, setDrawer] = useState(false);

    // Sort levels travel as "field:dir" strings: nested arrays do not survive the query string.
    const visit = (state: { f?: Filters; sort?: string[][]; search?: string; extra?: Filters } = {}): void => {
        const ff = state.f ?? f;
        const query = {
            ...Object.fromEntries(Object.entries(ff).filter(([, v]) => (Array.isArray(v) ? v.length : v !== '' && v !== undefined && v !== null))),
            search: (state.search ?? search) || undefined,
            sort: (state.sort ?? sort).map(([k, d]) => `${k}:${d}`),
            ...(state.extra ?? {}),
        };
        router.get(url, query as never, { preserveState: true, preserveScroll: true, replace: true });
    };
    const first = useRef(true);
    useEffect(() => {
        if (first.current) {
            first.current = false;
            return;
        }
        const timer = setTimeout(() => visit({ search }), 300);
        return () => clearTimeout(timer);
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, [search]);
    // The stat cards replace the status/payment filter (Total clears both) and the drawer shows it.
    const pick = (key: string): void => {
        const next = { ...f, status: ['active', 'grace', 'terminated', 'alumni'].includes(key) ? [key] : [], payment: ['paid', 'pending', 'unpaid'].includes(key) ? [key] : [] };
        setF(next);
        visit({ f: next });
    };
    const activeCount = Object.entries(filters).filter(([k, v]) => !['search', 'sort'].includes(k) && (Array.isArray(v) ? v.length : v !== undefined)).length;
    const clear = (): void => {
        setSearch('');
        router.get(url, {}, { preserveScroll: true });
    };
    const toggleSort = (field: string): void => {
        const [head] = sort;
        const next = [[field, head?.[0] === field && head[1] === 'asc' ? 'desc' : 'asc'], ...sort.filter(([k]) => k !== field)].slice(0, 3);
        setSort(next);
        visit({ sort: next });
    };
    const openView = (saved: Filters): void => {
        const nextSort = Array.isArray(saved.sort) && saved.sort.length ? (saved.sort as unknown[]).map((x) => (Array.isArray(x) ? (x as string[]) : String(x).split(':'))) : [['name', 'asc']];
        const next: Filters = Object.fromEntries(Object.entries(saved).filter(([k]) => k !== 'sort' && k !== 'search'));
        for (const k of LISTS) next[k] = Array.isArray(saved[k]) ? (saved[k] as string[]) : [];
        const nextSearch = typeof saved.search === 'string' ? saved.search : '';
        setSort(nextSort);
        setF(next);
        setSearch(nextSearch);
        visit({ f: next, sort: nextSort, search: nextSearch });
    };
    const saveView = (): void => {
        const name = prompt('Name this view');
        if (name) router.post('/saved-views', { name, filters: { ...filters } } as never, { preserveScroll: true });
    };
    // Export uses the same simple filters (search, session, dates) as the screen.
    const exportQuery = new URLSearchParams(Object.entries(filters).filter((e): e is [string, string] => typeof e[1] === 'string')).toString();
    const list = (key: string): string[] => (f[key] as string[] | undefined) ?? [];
    const setList = (key: string) => (value: (string | number)[]): void => setF({ ...f, [key]: value as string[] });
    const text = (key: string) => ({ value: (f[key] as string | undefined) ?? '', onChange: (v: string) => setF({ ...f, [key]: v }) });

    const [managing, setManaging] = useState<{ row: Row; action: ManageAction } | null>(null);
    const manageForm = useForm({ action: '' as ManageAction | '', days: '7', reason: '' });
    function openManage(row: Row, action: ManageAction): void {
        manageForm.setData({ action, days: '7', reason: '' });
        manageForm.clearErrors();
        setManaging({ row, action });
    }
    function sendManage(e: FormEvent): void {
        e.preventDefault();
        if (!managing || !club) return;
        manageForm.post(`/clubs/${club.slug}/memberships/${managing.row.id}/status`, { preserveScroll: true, onSuccess: () => setManaging(null) });
    }

    const head = (field: string, label: string, right = false): ReactNode => (
        <th className={`px-3 py-3 ${right ? 'text-right' : ''}`}>
            <button type="button" className="font-medium uppercase tracking-wide hover:text-ink" onClick={() => toggleSort(field)}>
                {label}
            </button>
        </th>
    );
    const content = (
        <>
            <Head title={club ? `Members · ${club.name}` : 'All members'} />
            <div className="grid gap-5">
                <header className="flex flex-wrap items-center justify-between gap-3">
                    <div>
                        <h1 className="text-xl font-bold">Members{club && ` · ${club.name}`}</h1>
                        {club && (
                            <Link href={portal ? '/admin/clubs' : `/clubs/${club.slug}`} className="text-sm text-brand-blue">
                                {portal ? 'All clubs' : 'Back to club'}
                            </Link>
                        )}
                    </div>
                    {club && (
                        <div className="flex flex-wrap gap-2">
                            <a href={`/clubs/${club.slug}/members/export/xlsx?${exportQuery}`}>
                                <Button variant="secondary" size="sm">
                                    <Icon name="file" size={16} /> 10 Full Member List (Excel)
                                </Button>
                            </a>
                            <a href={`/clubs/${club.slug}/members/export/pdf?${exportQuery}`}>
                                <Button variant="secondary" size="sm">
                                    PDF
                                </Button>
                            </a>
                            <Button size="sm" variant="secondary" onClick={() => router.post(`/clubs/${club.slug}/members/remind`, {}, { preserveScroll: true })}>
                                <Icon name="bell" size={16} /> Remind unpaid
                            </Button>
                        </div>
                    )}
                </header>
                {committeeFees.length > 0 && (
                    <section className="grid gap-2 rounded-card border border-line bg-surface p-4 shadow-card">
                        <h2 className="text-sm font-semibold">Committee club fees · this session</h2>
                        <ul className="grid gap-2 sm:grid-cols-2">
                            {committeeFees.map((c) => (
                                <li key={`${c.position}-${c.student_number}`} className="flex items-center justify-between gap-2 text-sm">
                                    <span className="min-w-0 truncate">
                                        <strong>{c.position}</strong> · {c.name} <span className="text-ink-muted">{c.student_number}</span>
                                    </span>
                                    <Badge tone={FEE_TONE[c.status]}>{FEE_LABEL[c.status]}</Badge>
                                </li>
                            ))}
                        </ul>
                    </section>
                )}
                <dl className="grid grid-cols-4 gap-2 sm:grid-cols-8">
                    {CARDS.map(([key, label]) => (
                        <button key={key} type="button" className="rounded-card border border-line bg-surface p-3 text-center shadow-card transition hover:shadow-pop" onClick={() => pick(key)}>
                            <dd className="text-xl font-bold tabular-nums">{counts[key] ?? 0}</dd>
                            <dt className="text-xs text-ink-muted">{label}</dt>
                        </button>
                    ))}
                </dl>
                <FilterBar search={search} onSearch={setSearch} placeholder="Search name, student ID, email or programme">
                    <Button variant="secondary" onClick={() => setDrawer(true)}>
                        <Icon name="settings" size={16} /> Filters{activeCount > 0 && ` (${activeCount})`}
                    </Button>
                    {activeCount > 0 && (
                        <Button variant="ghost" onClick={clear}>
                            Clear
                        </Button>
                    )}
                    <Button variant="ghost" onClick={saveView}>
                        <Icon name="bookmark" size={16} /> Save view
                    </Button>
                </FilterBar>
                {views.length > 0 && (
                    <div className="flex flex-wrap gap-2">
                        {views.map((v) => (
                            <span key={v.id} className="inline-flex items-center gap-1 rounded-full bg-surface-muted px-3 py-1 text-sm">
                                <button type="button" className="font-medium" onClick={() => openView(v.filters)}>
                                    {v.name}
                                </button>
                                <button type="button" className="text-ink-muted hover:text-danger" aria-label={`Delete view ${v.name}`} onClick={() => router.delete(`/saved-views/${v.id}`, { preserveScroll: true })}>
                                    ✕
                                </button>
                            </span>
                        ))}
                    </div>
                )}
                <p className="text-xs text-ink-muted">
                    Sorted by {sort.map(([k = '', d]) => `${SORT_FIELDS[k] ?? k} ${d === 'asc' ? '↑' : '↓'}`).join(', then ')} · click a column to sort by it first.
                </p>
                {!club ? (
                    <div className="overflow-x-auto rounded-card border border-line bg-surface">
                        <table className="w-full text-left text-sm">
                            <thead className="bg-surface-muted text-left text-xs uppercase tracking-wide text-ink-muted">
                                <tr>
                                    {head('name', 'Name')}
                                    {head('student_number', 'Student ID')}
                                    {head('programme', 'Programme')}
                                    {head('year_group', 'Year')}
                                    {head('club', 'Clubs')}
                                    {head('events', 'Events', true)}
                                    {head('cca', 'CCA', true)}
                                </tr>
                            </thead>
                            <tbody>
                                {rows.map((r) => (
                                    <tr key={r.id} className="border-t border-line align-top">
                                        <td className="px-3 py-2">
                                            <div className="font-medium">{r.name}</div>
                                            <div className="text-xs text-ink-muted">{r.email}</div>
                                        </td>
                                        <td className="px-3 py-2 font-mono text-xs">{r.student_number}</td>
                                        <td className="px-3 py-2">{r.programme_short}</td>
                                        <td className="whitespace-nowrap px-3 py-2">{r.year_group}</td>
                                        <td className="px-3 py-2">
                                            <ul className="grid min-w-[22rem] gap-2">
                                                {(r.clubs ?? []).map((c) => (
                                                    <li key={c.club_id} className="grid grid-cols-[minmax(0,1fr)_auto] items-center gap-x-3">
                                                        <span className="min-w-0">
                                                            <Link href={`/admin/clubs/${c.club_slug}/members`} className="block truncate font-medium hover:underline">
                                                                {c.club}
                                                            </Link>
                                                            <span className="block truncate text-xs text-ink-muted">
                                                                {c.position}
                                                                {c.expires && ` · until ${date(c.expires)}`}
                                                            </span>
                                                        </span>
                                                        <span className="flex items-center gap-1">
                                                            <Badge tone={TONE[c.status] ?? 'neutral'}>{c.status}</Badge>
                                                            <Badge tone={TONE[c.payment] ?? 'neutral'}>{c.payment}</Badge>
                                                        </span>
                                                    </li>
                                                ))}
                                            </ul>
                                        </td>
                                        <td className="px-3 py-2 text-right tabular-nums">{r.events}</td>
                                        <td className="px-3 py-2 text-right tabular-nums">{r.cca}</td>
                                    </tr>
                                ))}
                                {!rows.length && (
                                    <tr>
                                        <td colSpan={7} className="px-3 py-8 text-center text-ink-muted">
                                            No members match.
                                        </td>
                                    </tr>
                                )}
                            </tbody>
                        </table>
                    </div>
                ) : (
                    <div className="overflow-x-auto rounded-card border border-line bg-surface">
                        <table className="w-full text-left text-sm">
                            <thead className="bg-surface-muted text-left text-xs uppercase tracking-wide text-ink-muted">
                                <tr>
                                    {head('name', 'Name')}
                                    {head('student_number', 'Student ID')}
                                    <th className="px-3 py-3 font-medium">Position</th>
                                    {head('programme', 'Programme')}
                                    {head('year_group', 'Year')}
                                    {head('status', 'Status')}
                                    <th className="px-3 py-3 font-medium">Payment</th>
                                    {head('expires', 'Expires')}
                                    {head('events', 'Events', true)}
                                    {head('cca', 'CCA', true)}
                                    {canManage && <th className="px-3 py-3 font-medium">Manage</th>}
                                </tr>
                            </thead>
                            <tbody>
                                {rows.map((r) => (
                                    <tr key={r.id} className="border-t border-line">
                                        <td className="px-3 py-2">
                                            <div className="font-medium">{r.name}</div>
                                            <div className="text-xs text-ink-muted">{r.email}</div>
                                        </td>
                                        <td className="px-3 py-2 font-mono text-xs">{r.student_number}</td>
                                        <td className="px-3 py-2">{r.position}</td>
                                        <td className="px-3 py-2">{r.programme_short}</td>
                                        <td className="px-3 py-2">{r.year_group}</td>
                                        <td className="px-3 py-2">
                                            <Badge tone={TONE[r.status] ?? 'neutral'}>{r.status}</Badge>
                                            {r.status === 'grace' && r.grace_ends ? (
                                                <div className="mt-0.5 text-xs text-warning">ends {date(r.grace_ends)}</div>
                                            ) : (
                                                r.status === 'alumni' && r.alumni_since && <div className="mt-0.5 text-xs text-ink-muted">since {date(r.alumni_since)}</div>
                                            )}
                                        </td>
                                        <td className="px-3 py-2">
                                            <Badge tone={TONE[r.payment] ?? 'neutral'}>{r.payment}</Badge>
                                            {r.method && (
                                                <span className="ml-1 text-xs text-ink-muted">
                                                    {r.method}
                                                    {r.collected_by && ` · ${r.collected_by}`}
                                                </span>
                                            )}
                                        </td>
                                        <td className="px-3 py-2 text-xs">{r.expires ? date(r.expires) : '—'}</td>
                                        <td className="px-3 py-2 text-right tabular-nums">{r.events}</td>
                                        <td className="px-3 py-2 text-right tabular-nums">{r.cca}</td>
                                        {canManage && (
                                            <td className="px-3 py-2">
                                                {r.manageable && (
                                                    <div className="flex flex-wrap gap-1">
                                                        {(ACTIONS[r.status] ?? []).map((a) => (
                                                            <button key={a.key} type="button" className="rounded-full border border-line px-2 py-0.5 text-xs hover:bg-surface-muted" onClick={() => openManage(r, a.key)}>
                                                                {a.label}
                                                            </button>
                                                        ))}
                                                    </div>
                                                )}
                                            </td>
                                        )}
                                    </tr>
                                ))}
                                {!rows.length && (
                                    <tr>
                                        <td colSpan={11} className="px-3 py-8 text-center text-ink-muted">
                                            No members match.
                                        </td>
                                    </tr>
                                )}
                            </tbody>
                        </table>
                    </div>
                )}
                <nav className="flex flex-wrap items-center justify-between gap-2 text-sm" aria-label="Pages">
                    <p className="text-ink-muted">
                        {pagination.total ? (
                            <>
                                Showing {(pagination.page - 1) * pagination.per_page + 1}–{Math.min(pagination.page * pagination.per_page, pagination.total)} of{' '}
                                <strong className="text-ink">{pagination.total.toLocaleString()}</strong> members
                            </>
                        ) : (
                            '0 members'
                        )}
                    </p>
                    {pagination.last_page > 1 && (
                        <div className="flex items-center gap-1">
                            <button type="button" className="h-9 rounded-control border border-line px-3 disabled:opacity-40" disabled={pagination.page <= 1} onClick={() => visit({ extra: { page: pagination.page - 1 } })}>
                                ‹ Previous
                            </button>
                            <span className="px-2 tabular-nums">
                                Page {pagination.page} of {pagination.last_page}
                            </span>
                            <button
                                type="button"
                                className="h-9 rounded-control border border-line px-3 disabled:opacity-40"
                                disabled={pagination.page >= pagination.last_page}
                                onClick={() => visit({ extra: { page: pagination.page + 1 } })}
                            >
                                Next ›
                            </button>
                        </div>
                    )}
                </nav>
            </div>
            <Drawer open={drawer} onClose={() => setDrawer(false)} title="Filters">
                <form
                    className="grid gap-5"
                    onSubmit={(e) => {
                        e.preventDefault();
                        visit();
                        setDrawer(false);
                    }}
                >
                    <Select value={(f.session as string) ?? ''} onChange={(v) => setF({ ...f, session: v })} label="Session" options={[{ value: '', label: 'Any' }, ...options.sessions.map((s) => ({ value: s, label: s }))]} />
                    <fieldset className="grid gap-2">
                        <legend className="text-sm font-medium">Status</legend>
                        <CheckboxGroup
                            value={list('status')}
                            onChange={setList('status')}
                            options={{ active: 'Active', grace: 'Grace', terminated: 'Terminated', alumni: 'Alumni', pending: 'Pending', committee: 'Committee (not joined yet)', advisor: 'Advisor' }}
                            columns={2}
                        />
                    </fieldset>
                    <fieldset className="grid gap-2">
                        <legend className="text-sm font-medium">Payment</legend>
                        <CheckboxGroup value={list('payment')} onChange={setList('payment')} options={{ paid: 'Paid', pending: 'Pending', unpaid: 'Unpaid' }} columns={3} />
                        <CheckboxGroup value={list('method')} onChange={setList('method')} options={{ online: 'Online', cash: 'Cash', free: 'Free' }} columns={3} />
                    </fieldset>
                    {options.collectors.length > 0 && (
                        <fieldset className="grid gap-2">
                            <legend className="text-sm font-medium">Cash collected by</legend>
                            <CheckboxGroup value={list('collected_by')} onChange={setList('collected_by')} options={toOptions(options.collectors)} />
                        </fieldset>
                    )}
                    <fieldset className="grid gap-2">
                        <legend className="text-sm font-medium">Position</legend>
                        <CheckboxGroup value={list('position')} onChange={setList('position')} options={toOptions(options.positions)} columns={2} />
                    </fieldset>
                    <fieldset className="grid gap-2">
                        <legend className="text-sm font-medium">Year group</legend>
                        <CheckboxGroup value={list('year_group')} onChange={setList('year_group')} options={toOptions(options.year_groups)} columns={2} />
                    </fieldset>
                    <fieldset className="grid gap-2">
                        <legend className="text-sm font-medium">Programme</legend>
                        <CheckboxGroup value={list('programme_short')} onChange={setList('programme_short')} options={toOptions(options.programmes)} columns={3} />
                    </fieldset>
                    <fieldset className="grid gap-2">
                        <legend className="text-sm font-medium">Intake</legend>
                        <CheckboxGroup value={list('intake')} onChange={setList('intake')} options={toOptions(options.intakes)} columns={3} />
                    </fieldset>
                    <div className="grid grid-cols-2 gap-3">
                        <Input {...text('joined_from')} type="date" label="Joined from" />
                        <Input {...text('joined_to')} type="date" label="Joined to" />
                        <Input {...text('expiring_days')} type="number" label="Expiring within (days)" />
                        <Input {...text('min_events')} type="number" label="Min. events attended" />
                        <Input {...text('min_cca')} type="number" label="Min. CCA hours" />
                    </div>
                    {!club && (
                        <>
                            <fieldset className="grid gap-2">
                                <legend className="text-sm font-medium">Club</legend>
                                <CheckboxGroup value={list('club_id')} onChange={setList('club_id')} options={Object.fromEntries(options.clubs.map((c) => [c.value, c.label]))} />
                            </fieldset>
                            <fieldset className="grid gap-2">
                                <legend className="text-sm font-medium">Category</legend>
                                <CheckboxGroup value={list('category')} onChange={setList('category')} options={toOptions(options.categories)} />
                            </fieldset>
                            <fieldset className="grid gap-2">
                                <legend className="text-sm font-medium">Club status</legend>
                                <CheckboxGroup value={list('club_status')} onChange={setList('club_status')} options={{ active: 'Active', at_risk: 'At-risk', inactive: 'Inactive' }} columns={3} />
                            </fieldset>
                        </>
                    )}
                    <Button type="submit">Apply filters</Button>
                </form>
            </Drawer>
            <Modal open={!!managing} onClose={() => setManaging(null)} title={managing?.row.name ?? ''}>
                {managing && (
                    <form className="grid gap-4" onSubmit={sendManage}>
                        <p className="text-sm text-ink-muted">{MANAGE_TEXT[managing.action]}</p>
                        {managing.action === 'extend' && <Input value={manageForm.data.days} onChange={(v) => manageForm.setData('days', v)} type="number" min={1} max={30} label="Extra days (1–30)" error={manageForm.errors.days} />}
                        {managing.action === 'terminate' && <Textarea value={manageForm.data.reason} onChange={(v) => manageForm.setData('reason', v)} label="Reason (sent to the member)" rows={3} error={manageForm.errors.reason} />}
                        <Button type="submit" variant={managing.action === 'terminate' ? 'danger' : 'primary'} loading={manageForm.processing}>
                            Confirm
                        </Button>
                    </form>
                )}
            </Modal>
        </>
    );
    return portal ? <PortalLayout title={club ? `Members · ${club.name}` : 'All members'}>{content}</PortalLayout> : <AppLayout wide>{content}</AppLayout>;
}
