import MoneyChart from '@/Components/charts/MoneyChart';
import Badge from '@/Components/ui/Badge';
import { date, rm } from '@/lib/format';
import { Link } from '@inertiajs/react';

export interface ClubYear {
    session: string;
    session_id: number;
    events: { slug: string; name: string; date: string; registered: number; attended: number; package: boolean | null }[];
    weekly: { held: number; reported: number };
    finance: {
        income_sen: number;
        expense_sen: number;
        monthly: { key: string; label: string; income_sen: number; expense_sen: number }[];
        categories: { category: string; income_sen: number; expense_sen: number }[];
    };
    members: number;
}

const label = (c: string): string => c.replace(/_/g, ' ').replace(/^\w/, (m) => m.toUpperCase());

/** The club's whole session compiled for the AGM: events, weekly activities, money, and every download. */
export default function YearInReview({ club, year }: { club: { slug: string; name: string }; year: ClubYear }) {
    const q = `session=${encodeURIComponent(year.session)}`;
    const downloads = [
        { label: 'Financial Report (02)', href: `/clubs/${club.slug}/finance/export/pdf?${q}` },
        { label: 'Finance workbook (Excel)', href: `/clubs/${club.slug}/finance/workbook/xlsx?period=session&${q}` },
        { label: 'Full Member List (10)', href: `/clubs/${club.slug}/members/export/xlsx` },
        { label: 'Annual Activities Report (12)', href: `/clubs/${club.slug}/annual-report/pdf?session=${year.session_id}` },
    ];
    const net = year.finance.income_sen - year.finance.expense_sen;
    const cards = [
        { label: 'Approved events', value: year.events.length },
        { label: 'Weekly activities reported', value: `${year.weekly.reported} / ${year.weekly.held}` },
        { label: 'Net for the year', value: rm(net), danger: net < 0 },
        { label: 'Active members', value: year.members },
    ];

    return (
        <section className="grid gap-4 rounded-card border border-line bg-surface p-5 shadow-card">
            <header className="flex flex-wrap items-end justify-between gap-2">
                <div>
                    <h2 className="text-lg font-bold">{year.session} in review</h2>
                    <p className="text-sm text-ink-muted">Compiled automatically for the AGM from this session's records only.</p>
                </div>
            </header>

            <dl className="grid grid-cols-2 gap-3 sm:grid-cols-4">
                {cards.map((c) => (
                    <div key={c.label} className="rounded-card bg-surface-muted p-3">
                        <dt className="text-xs text-ink-muted">{c.label}</dt>
                        <dd className={`text-2xl font-bold tabular-nums ${c.danger ? 'text-danger' : ''}`}>{c.value}</dd>
                    </div>
                ))}
            </dl>

            <div className="grid gap-2">
                <h3 className="text-sm font-semibold">Events</h3>
                {!year.events.length ? (
                    <p className="text-sm text-ink-muted">No approved events this session.</p>
                ) : (
                    <div className="overflow-x-auto">
                        <table className="w-full text-sm">
                            <thead className="text-left text-xs text-ink-muted">
                                <tr>
                                    <th className="py-1 pr-3">Date</th>
                                    <th className="py-1 pr-3">Event</th>
                                    <th className="py-1 pr-3 text-right">Registered</th>
                                    <th className="py-1 pr-3 text-right">Attended</th>
                                    <th className="py-1">Completion package</th>
                                </tr>
                            </thead>
                            <tbody>
                                {year.events.map((e) => (
                                    <tr key={e.slug} className="border-t border-line">
                                        <td className="whitespace-nowrap py-2 pr-3">{date(e.date)}</td>
                                        <td className="py-2 pr-3">
                                            <Link href={`/events/${e.slug}/documents`} className="font-medium hover:underline">
                                                {e.name}
                                            </Link>
                                        </td>
                                        <td className="py-2 pr-3 text-right tabular-nums">{e.registered}</td>
                                        <td className="py-2 pr-3 text-right tabular-nums">{e.attended}</td>
                                        <td className="py-2">
                                            {e.package === null ? <Badge tone="neutral">Upcoming</Badge> : <Badge tone={e.package ? 'success' : 'warning'}>{e.package ? 'Complete' : 'Missing items'}</Badge>}
                                        </td>
                                    </tr>
                                ))}
                            </tbody>
                        </table>
                    </div>
                )}
            </div>

            <div className="grid gap-4 lg:grid-cols-[1fr_18rem]">
                <MoneyChart title="Money in and out, month by month" rows={year.finance.monthly} />
                <div className="grid content-start gap-2">
                    <h3 className="text-sm font-semibold">By category</h3>
                    {!year.finance.categories.length && <p className="text-sm text-ink-muted">No ledger entries this session.</p>}
                    <ul className="grid gap-1 text-sm">
                        {year.finance.categories.map((c) => (
                            <li key={c.category} className="flex justify-between gap-2 border-b border-line py-1">
                                <span>{label(c.category)}</span>
                                <span className="tabular-nums">
                                    {!!c.income_sen && `+${rm(c.income_sen)}`} {!!c.expense_sen && <span className="text-danger">−{rm(c.expense_sen)}</span>}
                                </span>
                            </li>
                        ))}
                        <li className="flex justify-between gap-2 pt-1 font-semibold">
                            <span>Total</span>
                            <span className="tabular-nums">
                                +{rm(year.finance.income_sen)} <span className="text-danger">−{rm(year.finance.expense_sen)}</span>
                            </span>
                        </li>
                    </ul>
                </div>
            </div>

            <div className="grid gap-2">
                <h3 className="text-sm font-semibold">AGM documents for {year.session}</h3>
                <div className="flex flex-wrap gap-2">
                    {downloads.map((d) => (
                        <a key={d.label} href={d.href} className="rounded-control border border-line px-3 py-1.5 text-sm hover:bg-surface-muted">
                            {d.label}
                        </a>
                    ))}
                </div>
            </div>
        </section>
    );
}
