import Button from '@react/Components/ui/Button';
import Card from '@react/Components/ui/Card';
import Input from '@react/Components/ui/Input';
import Textarea from '@react/Components/ui/Textarea';
import PortalLayout from '@react/Layouts/PortalLayout';
import { date } from '@/lib/format';
import { Head, useForm } from '@inertiajs/react';
import type { FormEvent } from 'react';

interface SettingsProps {
    programmes: { programme_code: string; abbreviation: string }[];
    unmappedCodes: string[];
    intakes: { intake_number: number; month: number }[];
    statuses: { active: string[]; alumni: string[] };
    sessions: { name: string; starts_on: string; ends_on: string }[];
    currentSession: string;
    ccaLimit: number;
    rules: { proposal_lead_days: number; min_photos: number; transfer_days: number; handover_days: number };
}
const lines = (text: string): string[] =>
    text
        .split('\n')
        .map((s) => s.trim())
        .filter(Boolean);
const options = { preserveScroll: true };
const removeButton = 'grid h-10 place-items-center rounded-control text-ink-muted hover:bg-surface-muted hover:text-danger';
const listHeader = 'grid grid-cols-[1fr_1fr_2rem] gap-2 px-1 text-xs font-medium uppercase tracking-wide text-ink-muted';

/** SLD settings: programme short forms, rules, CCA limit, camsys status map, intake months, sessions. */
export default function Index({ programmes: programmeList, unmappedCodes, intakes: intakeList, statuses: statusLists, sessions, currentSession, ccaLimit, rules: initialRules }: SettingsProps) {
    const rules = useForm({ ...initialRules } as Record<keyof typeof initialRules, number | string>);
    const cca = useForm({ max_hours: ccaLimit as number | string });
    const programmes = useForm({ items: [...programmeList, ...unmappedCodes.map((code) => ({ programme_code: code, abbreviation: '' }))] });
    const intakes = useForm({ items: intakeList.map((i) => ({ intake_number: String(i.intake_number), month: String(i.month) })) });
    const statuses = useForm({ active: statusLists.active.join('\n'), alumni: statusLists.alumni.join('\n') });
    const session = useForm({ name: '' });
    const on = (fn: () => void) => (e: FormEvent): void => {
        e.preventDefault();
        fn();
    };
    const setProgramme = (index: number, key: 'programme_code' | 'abbreviation', value: string): void =>
        programmes.setData('items', programmes.data.items.map((item, i) => (i === index ? { ...item, [key]: value } : item)));
    const setIntake = (index: number, key: 'intake_number' | 'month', value: string): void => intakes.setData('items', intakes.data.items.map((item, i) => (i === index ? { ...item, [key]: value } : item)));
    const pErr = programmes.errors as Record<string, string>;
    const rule = (key: keyof typeof initialRules) => ({ value: rules.data[key], onChange: (v: string) => rules.setData(key, v), error: rules.errors[key], type: 'number', required: true });

    return (
        <PortalLayout title="Settings">
            <Head title="Settings" />
            <div className="grid items-start gap-6 lg:grid-cols-2">
                <Card title="Programme short forms">
                    <p className="mb-4 text-sm text-ink-muted">
                        Short names shown on profiles and forms (for example BCS). The university system has no short form, so set one for each programme code. New codes appear here with the short form blank.
                    </p>
                    <form className="grid gap-2" onSubmit={on(() => programmes.put('/admin/settings/programmes', options))}>
                        <div className={listHeader}>
                            <span>Programme code</span>
                            <span>Short form</span>
                            <span />
                        </div>
                        {programmes.data.items.map((item, index) => (
                            <div key={index} className="grid grid-cols-[1fr_1fr_2rem] items-start gap-2">
                                <Input value={item.programme_code} onChange={(v) => setProgramme(index, 'programme_code', v)} placeholder="CS101" error={pErr[`items.${index}.programme_code`]} />
                                <Input value={item.abbreviation} onChange={(v) => setProgramme(index, 'abbreviation', v)} placeholder="BCS" error={pErr[`items.${index}.abbreviation`]} />
                                <button
                                    type="button"
                                    className={removeButton}
                                    aria-label={`Remove ${item.programme_code}`}
                                    onClick={() => programmes.setData('items', programmes.data.items.filter((_, i) => i !== index))}
                                >
                                    ✕
                                </button>
                            </div>
                        ))}
                        <div className="mt-2 flex gap-2">
                            <Button variant="secondary" onClick={() => programmes.setData('items', [...programmes.data.items, { programme_code: '', abbreviation: '' }])}>
                                Add programme
                            </Button>
                            <Button type="submit" loading={programmes.processing}>
                                Save
                            </Button>
                        </div>
                    </form>
                </Card>
                <Card title="Deadlines and requirements">
                    <p className="mb-4 text-sm text-ink-muted">Rules every club works to. Exceptions (a late proposal, a budget deficit) need a reason and your approval.</p>
                    <form className="grid items-start gap-x-4 gap-y-3 sm:grid-cols-2" onSubmit={on(() => rules.put('/admin/settings/rules', options))}>
                        <Input {...rule('proposal_lead_days')} label="Proposal lead time (days)" hint="Days before the event a proposal must be in" />
                        <Input {...rule('min_photos')} label="Minimum photos" hint="Weekly report, AGM and completed event" />
                        <Input {...rule('transfer_days')} label="Transfer deadline (days)" hint="Finance transfers an approved withdrawal within" />
                        <Input {...rule('handover_days')} label="Handover overlap (days)" hint="Incoming committee's view-only training" />
                        <div className="sm:col-span-2">
                            <Button type="submit" loading={rules.processing}>
                                Save rules
                            </Button>
                        </div>
                    </form>
                </Card>
                <Card title="CCA hours limit">
                    <p className="mb-4 text-sm text-ink-muted">
                        The most CCA hours one event can give participants or its committee. Committees can set any value up to this on their proposal; only SLD admins change the limit.
                    </p>
                    <form className="flex items-end gap-3" onSubmit={on(() => cca.put('/admin/settings/cca', options))}>
                        <Input value={cca.data.max_hours} onChange={(v) => cca.setData('max_hours', v)} type="number" label="Maximum CCA hours per event" required error={cca.errors.max_hours} />
                        <Button type="submit" loading={cca.processing}>
                            Save
                        </Button>
                    </form>
                </Card>
                <Card title="Student status">
                    <p className="mb-4 text-sm text-ink-muted">How each status from the university system is treated. Write one status per line, exactly as the university system spells it.</p>
                    <form
                        className="grid gap-4"
                        onSubmit={on(() => {
                            statuses.transform((d) => ({ active: lines(d.active), alumni: lines(d.alumni) }));
                            statuses.put('/admin/settings/statuses', options);
                        })}
                    >
                        <Textarea value={statuses.data.active} onChange={(v) => statuses.setData('active', v)} label="Counts as an active student" rows={4} error={statuses.errors.active} />
                        <Textarea value={statuses.data.alumni} onChange={(v) => statuses.setData('alumni', v)} label="Counts as alumni / no longer studying" rows={4} error={statuses.errors.alumni} />
                        <div>
                            <Button type="submit" loading={statuses.processing}>
                                Save
                            </Button>
                        </div>
                    </form>
                </Card>
                <Card title="Intake months">
                    <p className="mb-4 text-sm text-ink-muted">
                        Real student IDs (QIU-YYYYMM-NNNN) already include the intake month. This table is only used for older IDs without it: intake 1, 2, 3 of the year map to a month.
                    </p>
                    <form className="grid gap-2" onSubmit={on(() => intakes.put('/admin/settings/intakes', options))}>
                        <div className={listHeader}>
                            <span>Intake no.</span>
                            <span>Month (1–12)</span>
                            <span />
                        </div>
                        {intakes.data.items.map((item, index) => (
                            <div key={index} className="grid grid-cols-[1fr_1fr_2rem] items-start gap-2">
                                <Input value={item.intake_number} onChange={(v) => setIntake(index, 'intake_number', v)} type="number" placeholder="1" />
                                <Input value={item.month} onChange={(v) => setIntake(index, 'month', v)} type="number" placeholder="3" />
                                <button type="button" className={removeButton} aria-label={`Remove intake ${item.intake_number}`} onClick={() => intakes.setData('items', intakes.data.items.filter((_, i) => i !== index))}>
                                    ✕
                                </button>
                            </div>
                        ))}
                        <div className="mt-2 flex gap-2">
                            <Button variant="secondary" onClick={() => intakes.setData('items', [...intakes.data.items, { intake_number: String(intakes.data.items.length + 1), month: '1' }])}>
                                Add intake
                            </Button>
                            <Button type="submit" loading={intakes.processing}>
                                Save
                            </Button>
                        </div>
                    </form>
                </Card>
                <Card title="Academic sessions">
                    <p className="mb-4 text-sm text-ink-muted">
                        A session runs 1 July – 30 June and starts automatically each July. Current session: <strong className="text-ink">{currentSession}</strong>.
                    </p>
                    <ul className="mb-4 divide-y divide-line rounded-control border border-line text-sm">
                        {sessions.map((s) => (
                            <li key={s.name} className="flex items-center justify-between gap-3 px-3 py-2">
                                <span className="font-medium">
                                    {s.name}
                                    {s.name === currentSession && <span className="ml-2 rounded-full bg-success/10 px-2 py-0.5 text-xs text-success">current</span>}
                                </span>
                                <span className="text-ink-muted">
                                    {date(s.starts_on)} – {date(s.ends_on)}
                                </span>
                            </li>
                        ))}
                    </ul>
                    <form className="grid gap-2" onSubmit={on(() => session.put('/admin/settings/sessions', { ...options, onSuccess: () => session.reset() }))}>
                        <p className="text-sm font-medium">Add a future session</p>
                        <div className="flex items-start gap-2">
                            <div className="flex-1">
                                <Input value={session.data.name} onChange={(v) => session.setData('name', v)} placeholder="2027/2028" error={session.errors.name} />
                            </div>
                            <Button type="submit" loading={session.processing}>
                                Add
                            </Button>
                        </div>
                    </form>
                </Card>
            </div>
        </PortalLayout>
    );
}
