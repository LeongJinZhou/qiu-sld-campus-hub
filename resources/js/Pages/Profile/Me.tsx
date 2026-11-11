import PhotoEditor from '@/Components/profile/PhotoEditor';
import ProfileView from '@/Components/profile/ProfileView';
import SettingsPanel from '@/Components/profile/SettingsPanel';
import Badge from '@/Components/ui/Badge';
import Button from '@/Components/ui/Button';
import Card from '@/Components/ui/Card';
import Checkbox from '@/Components/ui/Checkbox';
import Drawer from '@/Components/ui/Drawer';
import EmptyState from '@/Components/ui/EmptyState';
import Icon from '@/Components/ui/Icon';
import Input from '@/Components/ui/Input';
import Modal from '@/Components/ui/Modal';
import QrCode from '@/Components/ui/QrCode';
import AppLayout from '@/Layouts/AppLayout';
import { dateTime } from '@/lib/format';
import type { ProfileData } from '@/types';
import { Deferred, Head, Link, useForm, usePage } from '@inertiajs/react';
import { useState, type FormEvent, type ReactNode } from 'react';

interface MeProps {
    profile: ProfileData;
    account: { email: string; student_number: string | null; status: string | null; has_photo_override: boolean; has_cover: boolean; personal_email: string | null; hidden_sections: string[] };
    hideableSections: string[];
    saved?: { id: number; url: string; image: string | null; caption: string | null }[];
    publicUrl: string;
    qrSvg: string;
    certificates: { kind: string; source_id: number; title: string; statement: string; date: string; code: string | null }[];
    upcoming: { name: string; slug: string; club: string; starts_at: string; venue: string | null; poster_url: string | null; needs_payment: boolean; needs_indemnity: boolean }[];
}
const LABELS: Record<string, string> = { clubs: 'Clubs', positions: 'Positions', events: 'Events attended', cca: 'CCA record' };

/** The signed-in person's own profile: first steps, photo and cover, QR, upcoming events, certificates, saved posts, settings. */
export default function Me({ profile, account, hideableSections, saved, publicUrl, qrSvg, certificates, upcoming }: MeProps) {
    const nav = usePage().props.nav;
    const [editing, setEditing] = useState(false);
    const [photo, setPhoto] = useState(false);
    const [cover, setCover] = useState(false);
    const [qr, setQr] = useState(false);
    const [copied, setCopied] = useState(false);
    const form = useForm({ hidden_sections: [...account.hidden_sections], personal_email: account.personal_email ?? '' });
    const save = (e: FormEvent): void => {
        e.preventDefault();
        form.post('/me', { preserveScroll: true, onSuccess: () => setEditing(false) });
    };
    const show = (section: string, on: boolean): void => form.setData('hidden_sections', on ? form.data.hidden_sections.filter((s) => s !== section) : [...form.data.hidden_sections, section]);
    async function share(): Promise<void> {
        if (navigator.share) await navigator.share({ title: profile.name, url: publicUrl }).catch(() => undefined);
        else {
            await navigator.clipboard.writeText(publicUrl);
            setCopied(true);
            setTimeout(() => setCopied(false), 2000);
        }
    }
    // First steps for a new student; the card disappears once every step is done.
    const steps: { label: string; done: boolean; href?: string; action?: () => void }[] = [
        { label: 'Add a profile photo', done: account.has_photo_override || !!profile.photo_url, action: () => setPhoto(true) },
        { label: 'Join a club', done: (profile.clubs?.length ?? 0) > 0, href: '/search?type=clubs' },
        { label: 'Sign up for an event', done: upcoming.length > 0 || (profile.events?.length ?? 0) > 0, href: '/feed' },
        { label: 'Link a personal email (keeps your access after graduation)', done: !!account.personal_email, action: () => setEditing(true) },
    ];
    const stepsDone = steps.filter((s) => s.done).length;

    const tabs = (key: string): ReactNode => {
        if (key === 'upcoming')
            return (
                <>
                    {!upcoming.length && <EmptyState title="Nothing coming up" description="Events you register for show here, with anything you still need to do." />}
                    <ul className="grid gap-2">
                        {upcoming.map((e) => (
                            <li key={e.slug}>
                                <Link
                                    href={e.needs_payment ? `/events/${e.slug}/pay` : e.needs_indemnity ? `/events/${e.slug}/indemnity` : `/events/${e.slug}`}
                                    className="flex items-center gap-3 rounded-card border border-line bg-surface p-3 transition hover:shadow-card"
                                >
                                    {e.poster_url ? (
                                        <img src={e.poster_url} alt="" className="size-14 shrink-0 rounded-control object-cover" />
                                    ) : (
                                        <span className="grid size-14 shrink-0 place-items-center rounded-control bg-gradient-to-br from-primary to-primary text-white">
                                            <Icon name="calendar" size={22} />
                                        </span>
                                    )}
                                    <span className="grid min-w-0 flex-1 text-sm">
                                        <strong className="truncate">{e.name}</strong>
                                        <span className="truncate text-xs text-ink-muted">
                                            {dateTime(e.starts_at)}
                                            {e.venue && ` · ${e.venue}`} · {e.club}
                                        </span>
                                    </span>
                                    {e.needs_payment ? <Badge tone="warning">Pay now</Badge> : e.needs_indemnity ? <Badge tone="warning">Sign indemnity</Badge> : <Badge tone="success">Ready</Badge>}
                                </Link>
                            </li>
                        ))}
                    </ul>
                </>
            );
        if (key === 'certificates')
            return (
                <>
                    {!certificates.length && <EmptyState title="No certificates yet" description="Committee positions and events you attend (clock in and out) appear here as downloadable certificates." />}
                    <ul className="grid gap-2">
                        {certificates.map((c) => (
                            <li key={`${c.kind}-${c.source_id}`} className="flex flex-wrap items-center justify-between gap-3 rounded-card border border-line bg-surface p-4">
                                <span className="min-w-0 flex-1">
                                    <strong className="block">{c.title}</strong>
                                    <span className="text-sm text-ink-muted">You {c.statement}</span>
                                    {c.code && (
                                        <a href={`/certificates/${c.code}`} className="block text-xs text-primary">
                                            Verification code {c.code}
                                        </a>
                                    )}
                                </span>
                                <a href={`/me/certificates/${c.kind}/${c.source_id}`}>
                                    <Button size="sm" variant="secondary">
                                        Download PDF
                                    </Button>
                                </a>
                            </li>
                        ))}
                    </ul>
                </>
            );
        if (key === 'saved')
            return (
                <Deferred data="saved" fallback={<p className="text-sm text-ink-muted">Loading…</p>}>
                    <>
                        {!saved?.length && <EmptyState title="No saved posts" description="Tap the bookmark on any post to keep it here." />}
                        <div className="grid grid-cols-3 gap-1">
                            {saved?.map((p) => (
                                <Link key={p.id} href={p.url} className="aspect-square overflow-hidden bg-surface-muted">
                                    {p.image ? <img src={p.image} alt={p.caption ?? ''} className="size-full object-cover" /> : <span className="grid size-full place-items-center p-2 text-center text-xs">{p.caption}</span>}
                                </Link>
                            ))}
                        </div>
                    </>
                </Deferred>
            );
        return null;
    };

    return (
        <AppLayout>
            <Head title="My profile" />
            {account.student_number && stepsDone < steps.length && (
                <section className="mb-4 rounded-card border border-line bg-surface p-4 shadow-card">
                    <div className="mb-3 flex items-center justify-between gap-3">
                        <h2 className="font-semibold">Make the most of Campus Hub</h2>
                        <span className="text-xs font-medium text-ink-muted">
                            {stepsDone}/{steps.length} done
                        </span>
                    </div>
                    <div className="mb-3 h-1.5 overflow-hidden rounded-full bg-surface-muted">
                        <div className="h-full rounded-full bg-gradient-to-r from-accent to-primary-strong" style={{ width: `${(stepsDone / steps.length) * 100}%` }} />
                    </div>
                    <ul className="grid gap-1 text-sm">
                        {steps.map((s) => {
                            const inner = (
                                <>
                                    <span className={`grid size-5 shrink-0 place-items-center rounded-full text-[11px] ${s.done ? 'bg-success text-white' : 'border border-line'}`}>{s.done ? '✓' : ''}</span>
                                    <span className={s.done ? 'line-through' : 'font-medium'}>{s.label}</span>
                                </>
                            );
                            const cls = `flex w-full items-center gap-3 rounded-control px-2 py-2 text-left ${s.done ? 'text-ink-muted' : 'hover:bg-surface-muted'}`;
                            return (
                                <li key={s.label}>
                                    {s.href && !s.done ? (
                                        <Link href={s.href} className={cls}>
                                            {inner}
                                        </Link>
                                    ) : (
                                        <button type="button" className={cls} disabled={s.done} onClick={() => !s.done && s.action?.()}>
                                            {inner}
                                        </button>
                                    )}
                                </li>
                            );
                        })}
                    </ul>
                </section>
            )}
            <ProfileView
                profile={profile}
                extraTabs={[
                    { key: 'upcoming', label: 'Coming up', icon: 'clock' },
                    { key: 'certificates', label: 'Certificates', icon: 'check' },
                    { key: 'saved', label: 'Saved', icon: 'bookmark' },
                ]}
                coverActions={
                    <button type="button" className="flex items-center gap-1.5 rounded-full bg-black/50 px-3 py-1.5 text-xs font-semibold text-white backdrop-blur hover:bg-black/60" onClick={() => setCover(true)}>
                        <Icon name="camera" size={14} /> {account.has_cover ? 'Edit cover' : 'Add cover'}
                    </button>
                }
                avatarAction={
                    <button type="button" className="absolute bottom-1 right-1 grid size-9 place-items-center rounded-full border-2 border-surface bg-ink text-white shadow-pop" aria-label="Change photo" onClick={() => setPhoto(true)}>
                        <Icon name="camera" size={18} />
                    </button>
                }
                actions={
                    <>
                        <Button variant="secondary" size="sm" onClick={() => setEditing(true)}>
                            <Icon name="edit" size={16} /> Edit profile
                        </Button>
                        <Button variant="secondary" size="sm" onClick={() => setQr(true)}>
                            <Icon name="qr" size={16} /> My QR
                        </Button>
                        <Button variant="secondary" size="sm" onClick={() => void share()}>
                            <Icon name="send" size={16} /> {copied ? 'Copied' : 'Share'}
                        </Button>
                    </>
                }
                meta={<p className="text-xs text-ink-muted">{[account.student_number, account.email].filter(Boolean).join(' · ')}</p>}
                renderTab={tabs}
            />
            {/* Phones have no sidebar, so account actions sit at the bottom of the profile. */}
            <section className="mt-6 grid gap-2 md:hidden">
                <Link href="/activities" className="flex items-center gap-3 rounded-card border border-line bg-surface px-4 py-3 text-sm font-medium shadow-card">
                    <Icon name="ticket" size={18} /> Activities &amp; tickets
                </Link>
                {nav?.isAdmin && (
                    <Link href="/admin" className="flex items-center gap-3 rounded-card border border-line bg-surface px-4 py-3 text-sm font-medium shadow-card">
                        <Icon name="shield" size={18} /> SLD Admin
                    </Link>
                )}
            </section>
            <div className="mt-6">
                <SettingsPanel />
            </div>
            <PhotoEditor open={photo} onClose={() => setPhoto(false)} current={profile.photo_url} name={profile.name} hasOverride={account.has_photo_override} />
            <PhotoEditor open={cover} onClose={() => setCover(false)} kind="cover" current={profile.cover_url} name={profile.name} hasOverride={account.has_cover} />
            <Modal open={qr} onClose={() => setQr(false)} title="My QIU profile QR">
                <div className="grid justify-items-center gap-3">
                    <QrCode svg={qrSvg} label="Anyone who scans sees your public profile" downloadName="my-qiu-profile" />
                    <a href={publicUrl} className="text-sm font-medium text-primary">
                        Open public profile
                    </a>
                </div>
            </Modal>
            <Drawer open={editing} onClose={() => setEditing(false)} title="Edit profile">
                <form className="grid gap-5" onSubmit={save}>
                    <Card>
                        <p className="mb-3 text-sm font-medium">Show on my public profile</p>
                        <div className="grid gap-2">
                            {hideableSections.map((section) => (
                                <Checkbox key={section} checked={!form.data.hidden_sections.includes(section)} onChange={(on) => show(section, on)} label={LABELS[section] ?? section} />
                            ))}
                        </div>
                    </Card>
                    <Input value={form.data.personal_email} onChange={(v) => form.setData('personal_email', v)} type="email" label="Personal email" hint="Keep signing in as alumni after graduation." error={form.errors.personal_email} />
                    <p className="text-xs text-ink-muted">Your name, student ID and programme come from university records and cannot be edited here.</p>
                    <Button type="submit" loading={form.processing}>
                        Save
                    </Button>
                </form>
            </Drawer>
        </AppLayout>
    );
}
