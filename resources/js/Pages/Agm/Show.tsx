import YearInReview, { type ClubYear } from '@/Components/agm/YearInReview';
import ApprovalTracker from '@/Components/forms/ApprovalTracker';
import EvidencePhotos, { type Evidence } from '@/Components/reports/EvidencePhotos';
import Avatar from '@/Components/ui/Avatar';
import Badge from '@/Components/ui/Badge';
import Button from '@/Components/ui/Button';
import Card from '@/Components/ui/Card';
import Checkbox from '@/Components/ui/Checkbox';
import EmptyState from '@/Components/ui/EmptyState';
import Icon from '@/Components/ui/Icon';
import Input from '@/Components/ui/Input';
import Select from '@/Components/ui/Select';
import Textarea from '@/Components/ui/Textarea';
import AppLayout from '@/Layouts/AppLayout';
import { date, dateTime } from '@/lib/format';
import type { ApprovalProgress } from '@/types';
import { Head, Link, router, useForm, usePoll } from '@inertiajs/react';
import { useEffect, type FormEvent } from 'react';

interface AgmShowProps {
    club: { slug: string; name: string };
    period: { session: string; starts_on: string; agm_deadline: string; report_deadline: string } | null;
    calendarConnected: boolean;
    agm: {
        evidence: Evidence;
        archive_session_id: number | null;
        acknowledged_at: string | null;
        id: number;
        starts_at: string;
        ends_at: string;
        venue: string | null;
        meet_link: string | null;
        attendance_open: boolean;
        qr_svg: string | null;
        present: number[];
        report: ApprovalProgress | null;
        election: string | null;
        package_submitted_at: string | null;
        checklist: Record<string, { label: string; ready: boolean }>;
    } | null;
    people: { id: number; name: string; photo_url: string | null }[];
    annual: { events: { id: number; name: string; date: string; details: string }[]; theme_id: number | null; generated_at: string | null; ai: string | null; ai_available: boolean } | null;
    themes: { id: number; name: string }[];
    year?: ClubYear | null;
}
const daysLeft = (d: string): number => Math.ceil((new Date(`${d}T23:59:59`).getTime() - Date.now()) / 86_400_000);
const detailsOf = (events: { id: number; details: string }[]): Record<number, string> => Object.fromEntries(events.map((e) => [e.id, e.details]));

/** The club's AGM: countdown, year in review, schedule, election, AGM report, annual report and the package to SLD. */
export default function Show({ club, period, calendarConnected, agm, people, annual, themes, year }: AgmShowProps) {
    const schedule = useForm({ starts_at: '', ends_at: '', venue: '', online: false });
    const byId = useForm({ student_number: '' });
    // The attendance QR rotates every minute, so refresh it while attendance is open.
    usePoll(30_000, { only: ['agm'] }, { autoStart: agm?.attendance_open === true });
    const annualForm = useForm({ details: detailsOf(annual?.events ?? []), theme_id: annual?.theme_id ?? (null as number | null) });
    const drafting = annual?.ai === 'pending';
    usePoll(2500, { only: ['annual'] }, { autoStart: drafting });
    useEffect(() => {
        if (!drafting) annualForm.setData('details', detailsOf(annual?.events ?? []));
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, [annual?.ai]);
    const saveAnnual = (then?: () => void): void => annualForm.put(`/clubs/${club.slug}/annual-report`, { preserveScroll: true, onSuccess: () => then?.() });
    const generate = (): void => saveAnnual(() => router.post(`/clubs/${club.slug}/annual-report/generate`, {}, { preserveScroll: true }));
    const packageReady = Object.values(agm?.checklist ?? {}).every((i) => i.ready);
    const mark = (id: number): void => {
        if (agm) router.post(`/agms/${agm.id}/attendance`, { user_id: id }, { preserveScroll: true, only: ['agm'] });
    };
    const submitSchedule = (e: FormEvent): void => {
        e.preventDefault();
        schedule.post(`/clubs/${club.slug}/agm`, { preserveScroll: true });
    };
    const submitById = (e: FormEvent): void => {
        e.preventDefault();
        if (agm) byId.post(`/agms/${agm.id}/attendance`, { preserveScroll: true, onSuccess: () => byId.reset() });
    };

    return (
        <AppLayout wide>
            <Head title={`AGM · ${club.name}`} />
            <div className="grid gap-6">
                <header>
                    <Link href={`/clubs/${club.slug}`} className="text-sm text-ink-muted hover:underline">
                        ← {club.name}
                    </Link>
                    <h1 className="text-2xl font-bold">Annual General Meeting</h1>
                </header>
                {!period ? (
                    <EmptyState title="The AGM module is locked" description="SLD opens it during the AGM period. You'll see a countdown here when it starts." />
                ) : (
                    <>
                        {agm && <EvidencePhotos evidence={agm.evidence} canEdit title="AGM photos" />}
                        {agm?.acknowledged_at && (
                            <p className="rounded-card bg-success/10 px-4 py-3 text-sm text-success">
                                SLD acknowledged this AGM on {agm.acknowledged_at}.{' '}
                                {agm.archive_session_id && (
                                    <a href={`/clubs/${club.slug}/archive/${agm.archive_session_id}`} className="font-semibold underline">
                                        Download the year's archive (ZIP)
                                    </a>
                                )}
                            </p>
                        )}
                        <section className="grid gap-3 rounded-card bg-gradient-to-r from-primary/15 via-primary/10 to-primary-strong/15 p-5 sm:grid-cols-3">
                            <div>
                                <p className="text-xs text-ink-muted">Session</p>
                                <p className="text-lg font-bold">{period.session}</p>
                            </div>
                            <div>
                                <p className="text-xs text-ink-muted">Hold the AGM by {date(period.agm_deadline)}</p>
                                <p className="text-lg font-bold">{Math.max(0, daysLeft(period.agm_deadline))} days left</p>
                            </div>
                            <div>
                                <p className="text-xs text-ink-muted">Submit the package by {date(period.report_deadline)}</p>
                                <p className="text-lg font-bold">{Math.max(0, daysLeft(period.report_deadline))} days left</p>
                            </div>
                        </section>
                        {year && <YearInReview club={club} year={year} />}
                        {!agm ? (
                            <Card title="1. Schedule the AGM">
                                <form className="grid gap-4" onSubmit={submitSchedule}>
                                    <div className="grid gap-4 sm:grid-cols-2">
                                        <Input value={schedule.data.starts_at} onChange={(v) => schedule.setData('starts_at', v)} type="datetime-local" label="Starts" error={schedule.errors.starts_at} />
                                        <Input value={schedule.data.ends_at} onChange={(v) => schedule.setData('ends_at', v)} type="datetime-local" label="Ends" error={schedule.errors.ends_at} />
                                    </div>
                                    <Checkbox checked={schedule.data.online} onChange={(v) => schedule.setData('online', v)} label="Online — create a Google Meet link from my QIU calendar" />
                                    {schedule.data.online && !calendarConnected ? (
                                        <p className="text-sm text-warning">
                                            Connect your QIU Google Calendar first.{' '}
                                            <a href="/google/calendar/connect" className="underline">
                                                Connect
                                            </a>
                                        </p>
                                    ) : (
                                        !schedule.data.online && <Input value={schedule.data.venue} onChange={(v) => schedule.setData('venue', v)} label="Venue" error={schedule.errors.venue} />
                                    )}
                                    <div>
                                        <Button type="submit" loading={schedule.processing}>
                                            Schedule and invite members
                                        </Button>
                                    </div>
                                </form>
                            </Card>
                        ) : (
                            <div className="grid gap-6 lg:grid-cols-[1fr_22rem]">
                                <div className="grid content-start gap-6">
                                    <Card title="AGM">
                                        <p className="flex flex-wrap items-center gap-3 text-sm">
                                            <span className="flex items-center gap-1">
                                                <Icon name="calendar" size={16} />
                                                {dateTime(agm.starts_at)}
                                            </span>
                                            {agm.venue && (
                                                <span className="flex items-center gap-1">
                                                    <Icon name="pin" size={16} />
                                                    {agm.venue}
                                                </span>
                                            )}
                                            {agm.meet_link && (
                                                <a href={agm.meet_link} target="_blank" rel="noopener">
                                                    <Button size="sm">
                                                        <Icon name="camera" size={16} /> Join Google Meet
                                                    </Button>
                                                </a>
                                            )}
                                        </p>
                                        <div className="mt-4 flex flex-wrap gap-2">
                                            <Link href={`/agms/${agm.id}/election`}>
                                                <Button variant="secondary" size="sm">
                                                    Election {agm.election && <Badge>{agm.election}</Badge>}
                                                </Button>
                                            </Link>
                                            <Link href={`/agms/${agm.id}/report`}>
                                                <Button variant="secondary" size="sm">
                                                    AGM Report (01)
                                                </Button>
                                            </Link>
                                        </div>
                                        {agm.report && agm.report.total > 0 && (
                                            <div className="mt-4">
                                                <ApprovalTracker progress={agm.report} compact />
                                            </div>
                                        )}
                                    </Card>
                                    {annual && (
                                        <Card title="2. Annual Activities Report (12)">
                                            <p className="mb-3 text-sm text-ink-muted">One details slide and one photos slide per approved event of {period.session}. Photos come from each event's gallery album (up to 6).</p>
                                            {annual.ai_available && annual.events.length > 0 && (
                                                <div className="mb-3">
                                                    <Button variant="secondary" size="sm" loading={drafting} onClick={() => router.post(`/clubs/${club.slug}/annual-report/draft`, {}, { preserveScroll: true })}>
                                                        <Icon name="sparkles" size={16} /> {drafting ? 'Writing…' : 'Write empty details with AI'}
                                                    </Button>
                                                    {annual.ai === 'failed' && <p className="mt-1 text-sm text-warning">AI couldn't write the details — please write them yourself.</p>}
                                                </div>
                                            )}
                                            <ul className="grid gap-4">
                                                {annual.events.map((e, i) => (
                                                    <li key={e.id}>
                                                        <Textarea
                                                            value={annualForm.data.details[e.id] ?? ''}
                                                            onChange={(v) => annualForm.setData('details', { ...annualForm.data.details, [e.id]: v })}
                                                            rows={3}
                                                            label={`${i + 1}. ${e.name} · ${e.date}`}
                                                            placeholder="Details"
                                                        />
                                                    </li>
                                                ))}
                                                {!annual.events.length && <li className="text-sm text-ink-muted">No approved events this session yet.</li>}
                                            </ul>
                                            <div className="mt-4 grid gap-3 sm:grid-cols-[1fr_auto_auto] sm:items-end">
                                                <Select
                                                    value={annualForm.data.theme_id ?? ''}
                                                    onChange={(v) => annualForm.setData('theme_id', v === '' ? null : Number(v))}
                                                    label="Theme"
                                                    options={[{ value: '', label: 'Template default' }, ...themes.map((t) => ({ value: t.id, label: t.name }))]}
                                                />
                                                <Button variant="secondary" loading={annualForm.processing} onClick={() => saveAnnual()}>
                                                    Save
                                                </Button>
                                                <Button loading={annualForm.processing} onClick={generate}>
                                                    Generate PPTX &amp; PDF
                                                </Button>
                                            </div>
                                            {annual.generated_at && (
                                                <p className="mt-3 flex flex-wrap items-center gap-2 text-sm">
                                                    Generated {annual.generated_at} ·{' '}
                                                    <a href={`/clubs/${club.slug}/annual-report/pptx`} className="text-primary underline">
                                                        PPTX
                                                    </a>{' '}
                                                    ·{' '}
                                                    <a href={`/clubs/${club.slug}/annual-report/pdf`} className="text-primary underline">
                                                        PDF
                                                    </a>
                                                </p>
                                            )}
                                        </Card>
                                    )}
                                    <Card title="3. Submit the AGM package to SLD">
                                        <ul className="grid gap-2 text-sm">
                                            {Object.entries(agm.checklist).map(([key, item]) => (
                                                <li key={key} className="flex items-center gap-2">
                                                    <Icon name={item.ready ? 'check' : 'x'} size={16} className={item.ready ? 'text-success' : 'text-ink-muted'} />
                                                    <span className={item.ready ? '' : 'text-ink-muted'}>{item.label}</span>
                                                </li>
                                            ))}
                                        </ul>
                                        {agm.package_submitted_at ? (
                                            <p className="mt-3 text-sm font-medium text-success">Submitted {agm.package_submitted_at}</p>
                                        ) : (
                                            <Button className="mt-3" disabled={!packageReady} onClick={() => router.post(`/agms/${agm.id}/package`, {}, { preserveScroll: true })}>
                                                Submit package
                                            </Button>
                                        )}
                                    </Card>
                                </div>
                                <aside className="grid content-start gap-4">
                                    <section className="rounded-card border border-line bg-surface p-5 shadow-card">
                                        <h2 className="font-semibold">Attendance</h2>
                                        <p className="mb-3 text-xs text-ink-muted">{agm.present.length} present. Opens 1 hour before the AGM.</p>
                                        {agm.qr_svg && (
                                            <div className="mb-3 grid justify-items-center gap-1">
                                                {/* Server-generated SVG of the AGM's signed attendance URL. */}
                                                <div className="w-56 rounded-card bg-white p-3" dangerouslySetInnerHTML={{ __html: agm.qr_svg }} />
                                                <p className="text-xs text-ink-muted">Members scan to mark themselves present. Refreshes every minute.</p>
                                            </div>
                                        )}
                                        <form className="mb-3 flex gap-2" onSubmit={submitById}>
                                            <div className="flex-1">
                                                <Input value={byId.data.student_number} onChange={(v) => byId.setData('student_number', v)} placeholder="Student ID" />
                                            </div>
                                            <Button type="submit" size="sm" variant="secondary" disabled={!agm.attendance_open}>
                                                Add
                                            </Button>
                                        </form>
                                        <ul className="grid max-h-96 gap-1 overflow-y-auto">
                                            {people.map((p) => {
                                                const here = agm.present.includes(p.id);
                                                return (
                                                    <li key={p.id}>
                                                        <button
                                                            type="button"
                                                            className={`flex w-full items-center gap-3 rounded-control p-2 text-left text-sm transition disabled:cursor-default ${here ? 'bg-success/10' : 'hover:bg-surface-muted'}`}
                                                            disabled={here || !agm.attendance_open}
                                                            onClick={() => mark(p.id)}
                                                        >
                                                            <Avatar src={p.photo_url} name={p.name} size="xs" />
                                                            <span className="flex-1">{p.name}</span>
                                                            {here && <Icon name="check" size={16} className="text-success" />}
                                                        </button>
                                                    </li>
                                                );
                                            })}
                                        </ul>
                                    </section>
                                </aside>
                            </div>
                        )}
                    </>
                )}
            </div>
        </AppLayout>
    );
}
