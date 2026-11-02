import CommitteeFeePopup from '@react/Components/payments/CommitteeFeePopup';
import Avatar from '@react/Components/ui/Avatar';
import Celebration from '@react/Components/ui/Celebration';
import GuidedTour from '@react/Components/ui/GuidedTour';
import Icon, { type IconName } from '@react/Components/ui/Icon';
import Toast from '@react/Components/ui/Toast';
import { Link, router, usePage } from '@inertiajs/react';
import { useEffect, type ReactNode } from 'react';

interface NavItem {
    label: string;
    icon: IconName;
    href: string;
    match: string;
    badge?: number;
}

export default function AppLayout({ wide = false, children }: { wide?: boolean; children?: ReactNode }) {
    const page = usePage();
    const { auth, nav, flash } = page.props;
    const user = auth.user;

    const items: NavItem[] = [
        { label: 'Feed', icon: 'home', href: '/feed', match: '/feed' },
        { label: 'Search', icon: 'search', href: '/search', match: '/search' },
        { label: 'Activities', icon: 'ticket', href: '/activities', match: '/activities' },
        { label: 'Calendar', icon: 'calendar', href: '/calendar', match: '/calendar' },
        { label: 'Assistant', icon: 'sparkles', href: '/assistant', match: '/assistant' },
        { label: 'Notifications', icon: 'bell', href: '/notifications', match: '/notifications', badge: (nav?.unread ?? 0) + (nav?.actions ?? 0) },
        ...(nav?.isStaff ? [{ label: 'Approvals', icon: 'check' as const, href: '/approvals', match: '/approvals' }] : []),
        { label: 'Profile', icon: 'user', href: '/me', match: '/me' },
    ];
    // Mobile tab bar: Feed, Search, Activities, Notifications, Profile. (Posting lives on the feed and club pages.)
    const mobileItems = items.filter((i) => !['Approvals', 'Calendar', 'Assistant'].includes(i.label));
    // Whole path segments only: /meetings must not light up /me (Profile).
    const path = page.url.split(/[?#]/)[0] ?? '';
    const isActive = (item: NavItem): boolean => path === item.match || path.startsWith(`${item.match}/`);

    // Live badge: refresh the unread count every 20 s while the app is on screen.
    useEffect(() => {
        const timer = window.setInterval(() => {
            if (document.visibilityState === 'visible') router.reload({ only: ['nav'] });
        }, 20_000);
        return () => window.clearInterval(timer);
    }, []);

    return (
        <div className="min-h-dvh bg-surface-muted md:grid md:grid-cols-[4.5rem_minmax(0,1fr)] lg:grid-cols-[15rem_minmax(0,1fr)]">
            <Toast />
            <Celebration text={flash.celebrate} />
            <CommitteeFeePopup />
            <GuidedTour />

            {/* Desktop / tablet sidebar */}
            <aside className="sticky top-0 hidden h-dvh flex-col border-r border-line bg-surface px-3 py-6 md:flex">
                <Link href="/feed" className="mb-8 flex items-center gap-3 px-2">
                    {/* Narrow rail: square icon. Full sidebar: the wide logo. */}
                    <img src="/icons/icon-192.png" alt="QIU Student Activity" className="size-9 rounded-lg lg:hidden" />
                    <img src="/brand/student-activity-black.png" alt="QIU Student Activity" className="hidden h-8 w-auto shrink-0 lg:block" />
                    <span className="hidden whitespace-nowrap text-base font-bold lg:inline">
                        <span className="text-gradient-warm">Campus</span> <span className="text-gradient-cool">Hub</span>
                    </span>
                </Link>
                <nav className="grid gap-1" aria-label="Primary">
                    {items.map((item) => (
                        <Link
                            key={item.label}
                            href={item.href}
                            data-tour={`nav-${item.label.toLowerCase()}`}
                            className={`group relative flex items-center gap-4 rounded-control px-3 py-2.5 text-[15px] transition hover:bg-surface-muted ${isActive(item) ? 'font-semibold text-ink' : 'text-ink/80'}`}
                            aria-current={isActive(item) ? 'page' : undefined}
                        >
                            <span className="relative">
                                <Icon name={item.icon} strokeWidth={isActive(item) ? 2.4 : 1.8} className="transition group-hover:scale-105" />
                                {!!item.badge && (
                                    <span className="absolute -right-1.5 -top-1.5 grid min-w-4 place-items-center rounded-full bg-brand-magenta px-1 text-[10px] font-bold text-white">
                                        {item.badge > 9 ? '9+' : item.badge}
                                    </span>
                                )}
                            </span>
                            <span className="hidden lg:inline">{item.label}</span>
                        </Link>
                    ))}
                </nav>
                <div className="mt-auto grid gap-1">
                    {nav?.isAdmin && (
                        <Link href="/admin" className="flex items-center gap-4 rounded-control px-3 py-2.5 text-[15px] text-ink/80 hover:bg-surface-muted">
                            <Icon name="shield" />
                            <span className="hidden lg:inline">SLD Admin</span>
                        </Link>
                    )}
                    <button type="button" className="flex items-center gap-4 rounded-control px-3 py-2.5 text-left text-[15px] text-ink/80 hover:bg-surface-muted" onClick={() => router.post('/logout')}>
                        <Icon name="logout" />
                        <span className="hidden lg:inline">Sign out</span>
                    </button>
                    {user && (
                        <Link href="/me" className="mt-2 flex items-center gap-3 rounded-control px-2 py-2 hover:bg-surface-muted">
                            <Avatar src={user.avatar_url} name={user.name} size="sm" />
                            <span className="hidden min-w-0 lg:grid">
                                <span className="truncate text-sm font-semibold">{user.name}</span>
                                <span className="truncate text-xs text-ink-muted">{user.email}</span>
                            </span>
                        </Link>
                    )}
                </div>
            </aside>

            <div className="min-w-0 pb-20 md:pb-0">
                {/* Mobile top bar */}
                <header className="sticky top-0 z-30 flex h-14 items-center justify-between border-b border-line bg-surface/90 px-4 backdrop-blur md:hidden">
                    <Link href="/feed" className="flex items-center gap-2">
                        <img src="/brand/student-activity-black.png" alt="QIU Student Activity" className="h-8 w-auto" />
                        <span className="whitespace-nowrap font-bold">
                            <span className="text-gradient-warm">Campus</span> <span className="text-gradient-cool">Hub</span>
                        </span>
                    </Link>
                    <div className="flex items-center gap-4">
                        <Link href="/assistant" aria-label="Assistant">
                            <Icon name="sparkles" />
                        </Link>
                        {nav?.isStaff && (
                            <Link href="/approvals" aria-label="Approvals">
                                <Icon name="check" />
                            </Link>
                        )}
                        {nav?.isAdmin && (
                            <Link href="/admin" aria-label="SLD Admin">
                                <Icon name="shield" />
                            </Link>
                        )}
                    </div>
                </header>

                <main className={`mx-auto w-full min-w-0 px-4 py-6 md:px-8 ${wide ? 'max-w-6xl' : 'max-w-4xl'}`}>{children}</main>
            </div>

            {/* Mobile bottom tab bar */}
            <nav className="fixed inset-x-0 bottom-0 z-30 border-t border-line bg-surface/95 backdrop-blur md:hidden" aria-label="Primary">
                <ul className="flex justify-around">
                    {mobileItems.map((item) => (
                        <li key={item.label}>
                            <Link
                                href={item.href}
                                data-tour={`nav-${item.label.toLowerCase()}`}
                                className={`relative grid h-14 w-14 place-items-center ${isActive(item) ? 'text-ink' : 'text-ink/60'}`}
                                aria-label={item.label}
                            >
                                {item.icon === 'user' && user ? (
                                    <Avatar src={user.avatar_url} name={user.name} size="xs" className={isActive(item) ? 'rounded-full ring-2 ring-ink' : ''} />
                                ) : (
                                    <Icon name={item.icon} size={26} strokeWidth={isActive(item) ? 2.4 : 1.8} />
                                )}
                                {!!item.badge && <span className="absolute right-2.5 top-2.5 size-2.5 rounded-full bg-brand-magenta ring-2 ring-surface" />}
                            </Link>
                        </li>
                    ))}
                </ul>
            </nav>
        </div>
    );
}
