import Button from '@react/Components/ui/Button';
import Checkbox from '@react/Components/ui/Checkbox';
import EmptyState from '@react/Components/ui/EmptyState';
import Icon, { type IconName } from '@react/Components/ui/Icon';
import Modal from '@react/Components/ui/Modal';
import AppLayout from '@react/Layouts/AppLayout';
import { Head, Link, router, usePoll } from '@inertiajs/react';
import { useState, type ReactNode } from 'react';

interface NotificationsProps {
    actions: { key: string; kind: string; title: string; body: string; url: string; since: string | null }[];
    items: { id: string; title: string; body: string; url: string | null; category: string; group: string; at: string | null; unread: boolean }[];
    hasMore: boolean;
    limit: number;
    group: string | null;
    groups: { key: string; label: string; count: number }[];
    total: number;
    mutable: Record<string, string>;
    muted: string[];
}
const ICONS: Record<string, IconName> = {
    payment: 'send',
    handover: 'qr',
    review: 'check',
    indemnity: 'file',
    approval: 'check',
    likes: 'heart',
    comments: 'message',
    saves: 'bookmark',
    follows: 'users',
    club_posts: 'calendar',
};
const chip = (on: boolean): string => `shrink-0 rounded-full border px-3 py-1.5 text-xs font-semibold transition ${on ? 'border-ink bg-ink text-white' : 'border-line bg-surface text-ink-muted hover:bg-surface-muted'}`;

export default function Index({ actions, items, hasMore, limit, group, groups, total, mutable, muted }: NotificationsProps) {
    // Live: new notifications and finished actions appear without reloading.
    usePoll(15_000, { only: ['actions', 'items', 'groups', 'total', 'hasMore'] });
    const [removing, setRemoving] = useState<Set<string>>(new Set());
    const [settings, setSettings] = useState(false);
    const [mutes, setMutes] = useState<string[]>([...muted]);
    // Phone notifications (used for "you left this half-way" reminders) need the person's permission once.
    const [permission, setPermission] = useState<NotificationPermission | 'unsupported'>('Notification' in window ? Notification.permission : 'unsupported');

    // Filter by group, load more, clear one or many. "Action needed" is never cleared: it goes when the task is done.
    const show = (g: string | null): void => router.get('/notifications', g ? { group: g } : {}, { preserveScroll: true, preserveState: true, replace: true });
    const more = (): void => router.get('/notifications', { ...(group ? { group } : {}), limit: limit + 30 }, { preserveScroll: true, preserveState: true, replace: true });
    const clearOne = (id: string): void => {
        setRemoving((s) => new Set(s).add(id));
        router.delete(`/notifications/${id}`, {
            preserveScroll: true,
            preserveState: true,
            onFinish: () =>
                setRemoving((s) => {
                    const next = new Set(s);
                    next.delete(id);
                    return next;
                }),
        });
    };
    const clearAll = (): void => {
        const what = group ? (groups.find((g) => g.key === group)?.label ?? 'these') : 'all';
        if (confirm(`Clear ${what} notifications? Action needed items stay until they are done.`)) router.delete('/notifications', { data: group ? { group } : {}, preserveScroll: true });
    };
    const toggle = (key: string, on: boolean): void => setMutes(on ? mutes.filter((k) => k !== key) : [...mutes, key]);
    const saveMutes = (): void => router.put('/notifications/mutes', { muted: mutes }, { preserveScroll: true, onSuccess: () => setSettings(false) });

    return (
        <AppLayout>
            <Head title="Notifications" />
            <div className="mx-auto grid max-w-xl gap-5">
                <header className="flex items-center justify-between gap-3">
                    <h1 className="text-xl font-bold">Notifications</h1>
                    <button type="button" className="inline-flex items-center gap-1.5 rounded-control px-3 py-1.5 text-sm font-medium text-ink-muted hover:bg-surface-muted" onClick={() => setSettings(true)}>
                        <Icon name="settings" size={16} /> Settings
                    </button>
                </header>
                {permission === 'default' && (
                    <div className="flex flex-wrap items-center justify-between gap-3 rounded-card border border-brand-cyan/40 bg-brand-cyan/5 p-4 text-sm">
                        <span>Get a phone notification when you leave a payment or form half-way.</span>
                        <Button size="sm" onClick={() => void Notification.requestPermission().then(setPermission)}>
                            Turn on
                        </Button>
                    </div>
                )}
                {actions.length > 0 && (
                    <section className="grid gap-2">
                        <h2 className="text-sm font-semibold uppercase tracking-wide text-ink-muted">Action needed</h2>
                        {actions.map((a) => (
                            <Link key={a.key} href={a.url} className="flex gap-3 rounded-card border-2 border-warning/40 bg-warning/5 p-4 transition hover:shadow-card">
                                <span className="grid size-10 shrink-0 place-items-center rounded-full bg-warning text-white">
                                    <Icon name={ICONS[a.kind] ?? 'bell'} size={18} />
                                </span>
                                <span className="grid min-w-0 flex-1 gap-0.5">
                                    <strong className="text-sm">{a.title}</strong>
                                    <span className="text-sm text-ink-muted">{a.body}</span>
                                </span>
                                <span className="self-center text-sm font-semibold text-warning">Continue</span>
                            </Link>
                        ))}
                    </section>
                )}
                <section className="grid gap-3">
                    <div className="flex items-center justify-between gap-2">
                        <h2 className="text-sm font-semibold uppercase tracking-wide text-ink-muted">Updates</h2>
                        {items.length > 0 && (
                            <button type="button" className="text-sm font-medium text-danger hover:underline" onClick={clearAll}>
                                Clear {group ? 'these' : 'all'}
                            </button>
                        )}
                    </div>
                    <div className="-mx-4 flex gap-2 overflow-x-auto px-4 pb-1 [scrollbar-width:none]">
                        <button type="button" className={chip(!group)} onClick={() => show(null)}>
                            All {total}
                        </button>
                        {groups
                            .filter((x) => x.count > 0)
                            .map((g) => (
                                <button key={g.key} type="button" className={chip(group === g.key)} onClick={() => show(g.key)}>
                                    {g.label} {g.count}
                                </button>
                            ))}
                    </div>
                    {!items.length && !actions.length && <EmptyState title="You're all caught up" description="Payments, approvals, club activity and reminders show up here." />}
                    <ul className="relative grid gap-2">
                        {items.map((n) => {
                            const cls = `flex gap-3 rounded-card border p-4 pr-11 transition hover:shadow-card ${n.unread ? 'border-brand-magenta/40 bg-brand-magenta/5' : 'border-line bg-surface'}`;
                            const body: ReactNode = (
                                <>
                                    <span className="grid size-10 shrink-0 place-items-center rounded-full bg-gradient-to-br from-brand-orange to-brand-magenta text-white">
                                        <Icon name={ICONS[n.category] ?? 'bell'} size={18} />
                                    </span>
                                    <span className="grid min-w-0 gap-0.5">
                                        <strong className="text-sm">{n.title}</strong>
                                        <span className="break-words text-sm text-ink-muted">{n.body}</span>
                                        <span className="text-xs text-ink-muted">{n.at}</span>
                                    </span>
                                </>
                            );
                            return (
                                <li key={n.id} className={`fade-up group relative ${removing.has(n.id) ? 'opacity-40' : ''}`}>
                                    {n.url ? (
                                        <Link href={n.url} className={cls}>
                                            {body}
                                        </Link>
                                    ) : (
                                        <div className={cls}>{body}</div>
                                    )}
                                    <button
                                        type="button"
                                        className="absolute right-2 top-2 grid size-8 place-items-center rounded-full text-ink-muted transition hover:bg-surface-muted hover:text-danger"
                                        aria-label={`Clear: ${n.title}`}
                                        onClick={() => clearOne(n.id)}
                                    >
                                        <Icon name="x" size={16} />
                                    </button>
                                </li>
                            );
                        })}
                    </ul>
                    {hasMore && (
                        <Button variant="secondary" block onClick={more}>
                            Show older
                        </Button>
                    )}
                </section>
            </div>
            <Modal open={settings} onClose={() => setSettings(false)} title="Notification settings">
                <div className="grid gap-4">
                    <p className="text-sm text-ink-muted">Choose which club activity you hear about. Payments, approvals and reminders always come through.</p>
                    <div className="grid gap-2">
                        {Object.entries(mutable).map(([key, label]) => (
                            <Checkbox key={key} checked={!mutes.includes(key)} label={label} onChange={(on) => toggle(key, on)} />
                        ))}
                    </div>
                    <Button onClick={saveMutes}>Save</Button>
                </div>
            </Modal>
        </AppLayout>
    );
}
