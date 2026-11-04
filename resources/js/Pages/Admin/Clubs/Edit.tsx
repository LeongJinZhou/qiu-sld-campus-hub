import Avatar from '@/Components/ui/Avatar';
import Badge from '@/Components/ui/Badge';
import Button from '@/Components/ui/Button';
import Card from '@/Components/ui/Card';
import FileUpload from '@/Components/ui/FileUpload';
import Input from '@/Components/ui/Input';
import PersonPicker from '@/Components/ui/PersonPicker';
import PositionPicker from '@/Components/ui/PositionPicker';
import Select from '@/Components/ui/Select';
import Textarea from '@/Components/ui/Textarea';
import PortalLayout from '@/Layouts/PortalLayout';
import type { MemberCard, Option } from '@/types';
import { Head, router, useForm } from '@inertiajs/react';
import { useRef, type FormEvent } from 'react';

interface ClubData {
    id: number;
    slug: string;
    name: string;
    category_id: number | null;
    established_year: number | null;
    bio: string | null;
    instagram_handle: string | null;
    managed_by: string;
    logo_url: string | null;
    transferred_at: string | null;
}
interface EditProps {
    club: ClubData | null;
    categories: Option[];
    session?: string;
    isCurrentSession?: boolean;
    sessions?: string[];
    committee?: MemberCard[];
    advisors?: MemberCard[];
    compulsoryPositions?: string[];
    missingCompulsory?: string[];
    staff?: { id: number; user_id: number | null; label: string }[];
}
const slugify = (value: string): string =>
    value
        .toLowerCase()
        .replace(/[^a-z0-9]+/g, '-')
        .replace(/^-|-$/g, '');

/** SLD sets up a club: details, the committee and advisors for a session, then hands it to the committee. */
export default function Edit({ club, categories, session = '', isCurrentSession, sessions = [], committee = [], advisors = [], compulsoryPositions = [], missingCompulsory = [], staff = [] }: EditProps) {
    const details = useForm({
        name: club?.name ?? '',
        slug: club?.slug ?? '',
        category_id: club?.category_id ?? (null as number | null),
        established_year: club?.established_year ? String(club.established_year) : '',
        bio: club?.bio ?? '',
        instagram_handle: club?.instagram_handle ?? '',
        logo: [] as File[],
    });
    // New clubs get a slug from the name until it is edited by hand.
    const setName = (name: string): void =>
        details.setData((d) => ({ ...d, name, slug: !club && (d.slug === '' || d.slug === slugify(d.name)) ? slugify(name) : d.slug }));
    const saveDetails = (e: FormEvent): void => {
        e.preventDefault();
        details.transform((d) => ({ ...d, logo: d.logo[0] ?? null, ...(club ? { _method: 'put' } : {}) }));
        details.post(club ? `/admin/clubs/${club.slug}` : '/admin/clubs', { preserveScroll: true });
    };

    const assign = useForm({ session, student_number: '', position: '' });
    const advisor = useForm({ session, staff_id: null as number | null });
    const assignBox = useRef<HTMLFormElement>(null);
    const holders = new Map(committee.map((m) => [m.position, m]));
    const optional = committee.filter((m) => !m.is_compulsory);

    // "Assign"/"Replace" beside a post: pick that post in the form below, bring it into view and put the cursor in the name box.
    function assignTo(position: string): void {
        assign.setData('position', position);
        assign.clearErrors();
        assignBox.current?.scrollIntoView({ behavior: 'smooth', block: 'center' });
        setTimeout(() => assignBox.current?.querySelector<HTMLInputElement>('input')?.focus({ preventScroll: true }), 50);
    }
    const submitAssign = (e: FormEvent): void => {
        e.preventDefault();
        assign.post(`/admin/clubs/${club?.slug}/committee`, { preserveScroll: true, onSuccess: () => assign.reset('student_number', 'position') });
    };
    const submitAdvisor = (e: FormEvent): void => {
        e.preventDefault();
        advisor.post(`/admin/clubs/${club?.slug}/advisors`, { preserveScroll: true, onSuccess: () => advisor.reset('staff_id') });
    };
    const remove = (member: MemberCard): void => {
        if (confirm(`Remove ${member.name} (${member.position})?`)) router.delete(`/admin/clubs/${club?.slug}/assignments/${member.assignment_id}`, { preserveScroll: true });
    };
    const changeSession = (value: string): void => router.get(`/admin/clubs/${club?.slug}/edit`, { session: value }, { preserveScroll: true });

    return (
        <PortalLayout title={club ? club.name : 'New club'}>
            <Head title={club ? club.name : 'New club'} />
            <div className="grid gap-6 lg:grid-cols-[1fr_22rem]">
                <div className="grid content-start gap-6">
                    <Card title="Club details">
                        <form className="grid gap-4" onSubmit={saveDetails}>
                            <div className="flex items-center gap-4">
                                <Avatar src={club?.logo_url} name={details.data.name || 'Club'} size="lg" />
                                <div className="flex-1">
                                    <FileUpload files={details.data.logo} onChange={(f) => details.setData('logo', f)} label="Logo" accept="image/jpeg,image/png,image/webp" error={details.errors.logo} />
                                </div>
                            </div>
                            <div className="grid gap-4 sm:grid-cols-2">
                                <Input value={details.data.name} onChange={setName} label="Name" required error={details.errors.name} />
                                <Input value={details.data.slug} onChange={(v) => details.setData('slug', v)} label="URL slug" required error={details.errors.slug} />
                                <Select value={details.data.category_id} onChange={(v) => details.setData('category_id', Number(v))} label="Category" options={categories} placeholder="Choose a category" error={details.errors.category_id} />
                                <Input value={details.data.established_year} onChange={(v) => details.setData('established_year', v)} type="number" label="Established year" error={details.errors.established_year} />
                                <Input value={details.data.instagram_handle} onChange={(v) => details.setData('instagram_handle', v)} label="Instagram handle" placeholder="gps_qiu" error={details.errors.instagram_handle} />
                            </div>
                            <Textarea value={details.data.bio} onChange={(v) => details.setData('bio', v)} label="Bio" rows={3} error={details.errors.bio} />
                            <Button type="submit" loading={details.processing} className="justify-self-start">
                                {club ? 'Save' : 'Create club'}
                            </Button>
                        </form>
                    </Card>

                    {club && (
                        <Card
                            title="Committee"
                            actions={
                                <select value={session} className="h-9 rounded-control border border-line px-2 text-sm" aria-label="Session" onChange={(e) => changeSession(e.target.value)}>
                                    {sessions.map((name) => (
                                        <option key={name} value={name}>
                                            {name}
                                        </option>
                                    ))}
                                </select>
                            }
                        >
                            {!isCurrentSession && <p className="mb-4 text-sm text-ink-muted">Past session — kept as history.</p>}
                            <ul className="grid gap-2">
                                {compulsoryPositions.map((position) => {
                                    const holder = holders.get(position);
                                    return (
                                        <li key={position} className="flex flex-wrap items-center justify-between gap-3 rounded-control border border-line p-3">
                                            <div className="flex items-center gap-3">
                                                <Avatar src={holder?.photo_url} name={holder?.name ?? '?'} size="sm" />
                                                <div className="grid">
                                                    <span className="text-sm font-medium">
                                                        {position} <Badge tone="info">Compulsory</Badge>
                                                    </span>
                                                    <span className="text-xs text-ink-muted">{holder?.name ?? 'Vacant'}</span>
                                                </div>
                                            </div>
                                            <div className="flex flex-wrap gap-2">
                                                <Button size="sm" variant="secondary" onClick={() => assignTo(position)}>
                                                    {holder ? 'Replace' : 'Assign'}
                                                </Button>
                                                {holder && (
                                                    <Button size="sm" variant="ghost" onClick={() => remove(holder)}>
                                                        Remove
                                                    </Button>
                                                )}
                                            </div>
                                        </li>
                                    );
                                })}
                                {optional.map((member) => (
                                    <li key={member.assignment_id} className="flex items-center justify-between gap-3 rounded-control border border-line p-3">
                                        <div className="flex items-center gap-3">
                                            <Avatar src={member.photo_url} name={member.name} size="sm" />
                                            <div className="grid">
                                                <span className="text-sm font-medium">{member.position}</span>
                                                <span className="text-xs text-ink-muted">{member.name}</span>
                                            </div>
                                        </div>
                                        <Button size="sm" variant="ghost" onClick={() => remove(member)}>
                                            Remove
                                        </Button>
                                    </li>
                                ))}
                            </ul>
                            <form
                                ref={assignBox}
                                className={`mt-5 grid gap-3 rounded-control bg-surface-muted p-4 sm:grid-cols-[1.4fr_1fr_auto] sm:items-end ${assign.data.position ? 'ring-2 ring-primary' : ''}`}
                                onSubmit={submitAssign}
                            >
                                {assign.data.position && <p className="text-sm font-medium sm:col-span-3">Assign someone as {assign.data.position}</p>}
                                <PersonPicker value={assign.data.student_number} onChange={(v) => assign.setData('student_number', v)} required error={assign.errors.student_number} />
                                <PositionPicker
                                    value={assign.data.position}
                                    onChange={(v) => assign.setData('position', v)}
                                    required={compulsoryPositions}
                                    taken={committee.flatMap((m) => (m.position ? [m.position] : []))}
                                    error={assign.errors.position}
                                />
                                <Button type="submit" loading={assign.processing} disabled={!assign.data.student_number || !assign.data.position}>
                                    Assign
                                </Button>
                                {assign.errors.session && <p className="text-sm text-danger sm:col-span-3">{assign.errors.session}</p>}
                            </form>
                            {missingCompulsory.length > 0 && <p className="mt-3 text-sm text-warning">Still missing: {missingCompulsory.join(', ')}</p>}
                        </Card>
                    )}
                </div>
                {club && (
                    <aside className="grid content-start gap-6">
                        <Card title="Advisors">
                            <ul className="mb-4 grid gap-2">
                                {advisors.map((member) => (
                                    <li key={member.assignment_id} className="flex items-center justify-between gap-2 text-sm">
                                        <span>{member.name}</span>
                                        <Button size="sm" variant="ghost" onClick={() => remove(member)}>
                                            Remove
                                        </Button>
                                    </li>
                                ))}
                                {!advisors.length && <li className="text-sm text-ink-muted">No advisor yet.</li>}
                            </ul>
                            <form className="grid gap-3" onSubmit={submitAdvisor}>
                                <Select
                                    value={advisor.data.staff_id}
                                    onChange={(v) => advisor.setData('staff_id', Number(v))}
                                    options={staff.map((s) => ({ value: s.id, label: s.label }))}
                                    placeholder="Choose staff"
                                    error={advisor.errors.staff_id ?? advisor.errors.session}
                                />
                                {!staff.length && <p className="text-xs text-ink-muted">No active staff yet. Add staff under Staff first.</p>}
                                <Button type="submit" variant="secondary" loading={advisor.processing} disabled={!advisor.data.staff_id}>
                                    Add advisor
                                </Button>
                            </form>
                        </Card>
                        <Card title="Ownership">
                            <p className="mb-4 text-sm text-ink-muted">
                                {club.managed_by === 'committee'
                                    ? `Managed by the committee and advisor since ${club.transferred_at}. SLD keeps oversight.`
                                    : 'SLD is setting this club up. Transfer it once the committee and advisor are in place.'}
                            </p>
                            {club.managed_by === 'committee' ? (
                                <Button variant="secondary" block onClick={() => router.delete(`/admin/clubs/${club.slug}/transfer`, { preserveScroll: true })}>
                                    Take back
                                </Button>
                            ) : (
                                <Button block disabled={missingCompulsory.length > 0 || !advisors.length} onClick={() => router.post(`/admin/clubs/${club.slug}/transfer`, {}, { preserveScroll: true })}>
                                    Transfer to committee
                                </Button>
                            )}
                        </Card>
                    </aside>
                )}
            </div>
        </PortalLayout>
    );
}
