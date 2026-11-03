import ApprovalTracker from '@react/Components/forms/ApprovalTracker';
import Badge from '@react/Components/ui/Badge';
import Button from '@react/Components/ui/Button';
import Card from '@react/Components/ui/Card';
import Checkbox from '@react/Components/ui/Checkbox';
import FileUpload from '@react/Components/ui/FileUpload';
import Icon from '@react/Components/ui/Icon';
import Input from '@react/Components/ui/Input';
import PortalLayout from '@react/Layouts/PortalLayout';
import { date } from '@/lib/format';
import type { ApprovalProgress } from '@/types';
import { Head, Link, router, useForm } from '@inertiajs/react';
import type { FormEvent, ReactNode } from 'react';

interface ClubAgm {
    starts_at: string;
    attended: number;
    report: ApprovalProgress | null;
    report_id: number | null;
    election: string | null;
    flags: { position: string; issue: string }[];
    checklist: Record<string, { label: string; ready: boolean }>;
    package_submitted_at: string | null;
    id: number;
    acknowledged_at: string | null;
    election_id: number | null;
    results_published: boolean;
    handover_started: boolean;
    handover_ends_on: string | null;
    handed_over: boolean;
    archive: boolean;
}
interface ClubRow {
    slug: string;
    name: string;
    agm: ClubAgm | null;
    annual: boolean;
}
interface AgmAdminProps {
    period: { id: number; session: string; session_id: number; starts_on: string; agm_deadline: string; report_deadline: string } | null;
    history: { id: number; session: string; starts_on: string; report_deadline: string; closed: boolean }[];
    clubs: ClubRow[];
    themes: { id: number; name: string; background_color: string; title_color: string; body_color: string; font: string; is_default: boolean; background_url: string | null }[];
    defaultSession: string;
}
const FLAG_TEXT: Record<string, string> = { no_candidate: 'no candidate', uncontested: 'uncontested', tie: 'tie' };

export default function Index({ period, history, clubs, themes, defaultSession }: AgmAdminProps) {
    const open = useForm({ session: defaultSession, starts_on: '', agm_deadline: '', report_deadline: '' });
    const theme = useForm({ name: '', background_color: '#FFFFFF', title_color: '#14213D', body_color: '#333333', font: 'Poppins', background: [] as File[], is_default: false });
    const close = (): void => {
        if (period && confirm('Close the AGM period? Clubs can no longer schedule or edit AGM work.')) router.post(`/admin/agm/periods/${period.id}/close`);
    };
    const openPeriod = (e: FormEvent): void => {
        e.preventDefault();
        open.post('/admin/agm/periods');
    };
    const saveTheme = (e: FormEvent): void => {
        e.preventDefault();
        theme.transform((d) => ({ ...d, background: d.background[0] ?? null }));
        theme.post('/admin/agm/themes', { forceFormData: true, preserveScroll: true, onSuccess: () => theme.reset() });
    };
    const checklistItem = (c: ClubRow, agm: ClubAgm, key: string, item: { label: string; ready: boolean }): ReactNode => {
        if (item.ready && key === 'report' && agm.report_id)
            return (
                <Link href={`/documents/${agm.report_id}`} className="underline">
                    {item.label}
                </Link>
            );
        if (item.ready && key === 'finance')
            return (
                <a href={`/clubs/${c.slug}/finance/export/pdf`} className="underline">
                    {item.label}
                </a>
            );
        if (key === 'members')
            return (
                <Link href={`/admin/clubs/${c.slug}/members`} className="underline">
                    {item.label}
                </Link>
            );
        if (item.ready && key === 'annual' && period)
            return (
                <a href={`/clubs/${c.slug}/annual-report/pdf?session=${period.session_id}`} className="underline">
                    {item.label}
                </a>
            );
        return item.label;
    };

    return (
        <PortalLayout title="AGM, elections and annual reports">
            <Head title="AGM" />
            <div className="grid gap-6">
                {period ? (
                    <Card title={`AGM period ${period.session} is open`}>
                        <p className="text-sm">
                            Opened {date(period.starts_on)} · AGMs by {date(period.agm_deadline)} · packages due {date(period.report_deadline)}
                        </p>
                        <Button variant="secondary" size="sm" className="mt-3" onClick={close}>
                            Close period
                        </Button>
                    </Card>
                ) : (
                    <Card title="Open an AGM period">
                        <form className="grid gap-3 md:grid-cols-[10rem_1fr_1fr_1fr_auto] md:items-end" onSubmit={openPeriod}>
                            <Input value={open.data.session} onChange={(v) => open.setData('session', v)} label="Session" placeholder="2026/2027" error={open.errors.session} />
                            <Input value={open.data.starts_on} onChange={(v) => open.setData('starts_on', v)} type="date" label="Opens" error={open.errors.starts_on} />
                            <Input value={open.data.agm_deadline} onChange={(v) => open.setData('agm_deadline', v)} type="date" label="AGM deadline" error={open.errors.agm_deadline} />
                            <Input value={open.data.report_deadline} onChange={(v) => open.setData('report_deadline', v)} type="date" label="Report deadline" error={open.errors.report_deadline} />
                            <Button type="submit" loading={open.processing}>
                                Open
                            </Button>
                        </form>
                    </Card>
                )}
                {period && (
                    <Card title="Clubs">
                        <ul className="divide-y divide-line">
                            {clubs.map((c) => {
                                const agm = c.agm;
                                return (
                                    <li key={c.slug} className="grid gap-3 py-4 text-sm lg:grid-cols-[14rem_1fr_16rem]">
                                        <div>
                                            <p className="font-semibold">{c.name}</p>
                                            {agm ? (
                                                <p className="text-xs text-ink-muted">
                                                    AGM {agm.starts_at} · {agm.attended} attended
                                                </p>
                                            ) : (
                                                <Badge tone="warning">AGM not scheduled</Badge>
                                            )}
                                        </div>
                                        {agm && (
                                            <div className="grid content-start gap-2">
                                                {agm.report && agm.report.total ? <ApprovalTracker progress={agm.report} compact /> : <p className="text-ink-muted">AGM Report not submitted</p>}
                                                <p className="flex flex-wrap items-center gap-1">
                                                    Election: <Badge>{agm.election ?? 'none'}</Badge>
                                                    {agm.flags.map((f) => (
                                                        <Badge key={f.position + f.issue} tone="warning">
                                                            {f.position}: {FLAG_TEXT[f.issue]}
                                                        </Badge>
                                                    ))}
                                                </p>
                                            </div>
                                        )}
                                        {agm && (
                                            <div className="grid content-start gap-1">
                                                <p className="font-medium">Package {agm.package_submitted_at && <Badge tone="success">submitted</Badge>}</p>
                                                {Object.entries(agm.checklist).map(([key, item]) => (
                                                    <p key={key} className="flex items-center gap-1 text-xs">
                                                        <Icon name={item.ready ? 'check' : 'x'} size={14} className={item.ready ? 'text-success' : 'text-ink-muted'} />
                                                        {checklistItem(c, agm, key, item)}
                                                    </p>
                                                ))}
                                                {/* SLD: acknowledge (archives the year), then start the committee handover. */}
                                                <div className="mt-2 grid gap-1.5 border-t border-line pt-2 text-xs">
                                                    {!agm.acknowledged_at ? (
                                                        <Button size="sm" disabled={!agm.package_submitted_at} onClick={() => router.post(`/admin/agms/${agm.id}/acknowledge`, {}, { preserveScroll: true })}>
                                                            Acknowledge AGM
                                                        </Button>
                                                    ) : (
                                                        <p className="text-success">
                                                            Acknowledged {agm.acknowledged_at}{' '}
                                                            {agm.archive ? (
                                                                <a href={`/clubs/${c.slug}/archive/${period.session_id}`} className="ml-1 underline">
                                                                    Archive (ZIP)
                                                                </a>
                                                            ) : (
                                                                <span className="ml-1 text-ink-muted">· archive being built</span>
                                                            )}
                                                        </p>
                                                    )}
                                                    {agm.acknowledged_at && agm.election_id && agm.results_published && !agm.handover_started ? (
                                                        <Button size="sm" variant="secondary" onClick={() => router.post(`/admin/elections/${agm.election_id}/handover`, {}, { preserveScroll: true })}>
                                                            Start handover
                                                        </Button>
                                                    ) : agm.handed_over ? (
                                                        <p className="text-success">New committee in charge</p>
                                                    ) : (
                                                        agm.handover_started && <p className="text-warning">Handover · training access until {agm.handover_ends_on}</p>
                                                    )}
                                                </div>
                                            </div>
                                        )}
                                    </li>
                                );
                            })}
                        </ul>
                    </Card>
                )}
                <Card title="Annual report themes">
                    <ul className="mb-4 flex flex-wrap gap-3">
                        {themes.map((t) => (
                            <li key={t.id} className="w-40 overflow-hidden rounded-card border border-line text-xs">
                                <div className="grid h-20 place-items-center bg-cover bg-center" style={{ backgroundColor: t.background_color, backgroundImage: t.background_url ? `url(${t.background_url})` : undefined, fontFamily: t.font }}>
                                    <span className="font-bold" style={{ color: t.title_color }}>
                                        Aa
                                    </span>
                                </div>
                                <p className="p-2">
                                    {t.name} {t.is_default && <Badge tone="info">default</Badge>}
                                </p>
                            </li>
                        ))}
                        {!themes.length && <li className="text-sm text-ink-muted">No themes yet — reports use the official template's look.</li>}
                    </ul>
                    <form className="grid gap-3 md:grid-cols-3" onSubmit={saveTheme}>
                        <Input value={theme.data.name} onChange={(v) => theme.setData('name', v)} label="Name" error={theme.errors.name} />
                        <Input value={theme.data.font} onChange={(v) => theme.setData('font', v)} label="Font" error={theme.errors.font} />
                        <div className="grid grid-cols-3 gap-2">
                            <Input value={theme.data.background_color} onChange={(v) => theme.setData('background_color', v)} type="color" label="Background" />
                            <Input value={theme.data.title_color} onChange={(v) => theme.setData('title_color', v)} type="color" label="Titles" />
                            <Input value={theme.data.body_color} onChange={(v) => theme.setData('body_color', v)} type="color" label="Text" />
                        </div>
                        <FileUpload files={theme.data.background} onChange={(f) => theme.setData('background', f)} label="Background image (optional)" accept="image/png,image/jpeg" error={theme.errors.background} />
                        <Checkbox checked={theme.data.is_default} onChange={(v) => theme.setData('is_default', v)} label="Use as default" />
                        <div>
                            <Button type="submit" loading={theme.processing}>
                                Add theme
                            </Button>
                        </div>
                    </form>
                </Card>
                {history.length > 0 && (
                    <Card title="Past periods">
                        <ul className="grid gap-1 text-sm">
                            {history.map((h) => (
                                <li key={h.id}>
                                    {h.session} · {date(h.starts_on)} – {date(h.report_deadline)} {h.closed && <Badge>closed</Badge>}
                                </li>
                            ))}
                        </ul>
                    </Card>
                )}
            </div>
        </PortalLayout>
    );
}
