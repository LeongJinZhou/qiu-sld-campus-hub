import CommitteeFeePopup from '@/Components/payments/CommitteeFeePopup';
import Brand from '@/Components/brand/Brand';
import Avatar from '@/Components/ui/Avatar';
import Celebration from '@/Components/ui/Celebration';
import GuidedTour from '@/Components/ui/GuidedTour';
import Icon, { type IconName } from '@/Components/ui/Icon';
import Toast from '@/Components/ui/Toast';
import { Link, router, usePage } from '@inertiajs/react';
import { useEffect, type ReactNode } from 'react';
import { useCardGlow } from '@/hooks/useCardGlow';
import { navMatches } from '@/lib/nav';

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
    const isActive = (item: NavItem): boolean => navMatches(path, item.match);

    useCardGlow();
    // Live badge: refresh the unread count every 20 s while the app is on screen.
    useEffect(() => {
        const timer = window.setInterval(() => {
            if (document.visibilityState === 'visible') router.reload({ only: ['nav'] });
        }, 20_000);
        return () => window.clearInterval(timer);
    }, []);

    const topItems = items.filter((i) => !['Notifications', 'Profile'].includes(i.label));
    const notifications = items.find((i) => i.label === 'Notifications');

    return (
        <div className="min-h-dvh bg-surface-muted">
            <Toast />
            <Celebration text={flash.celebrate} />
            <CommitteeFeePopup />
            <GuidedTour />

            {/* Desktop / tablet top bar, as in the QIU Industry Day app: brand, sections with a red underline, actions. */}
            <header className="sticky top-0 z-30 hidden h-[4.5rem] items-center gap-6 border-b border-line bg-surface/95 px-[clamp(1.25rem,4vw,4rem)] backdrop-blur-md md:flex lg:gap-10">
                <Link href="/feed" aria-label="Campus Hub home">
                    <Brand tag="SLD" />
                </Link>
                <nav className="flex h-full items-center gap-5 lg:gap-7" aria-label="Primary">
                    {topItems.map((item) => (
                        <Link
                            key={item.label}
                            href={item.href}
                            data-tour={`nav-${item.label.toLowerCase()}`}
                            aria-current={isActive(item) ? 'page' : undefined}
                            aria-label={item.label}
                            className={`relative flex h-full items-center gap-2 text-[0.83rem] font-bold transition ${isActive(item) ? 'text-primary after:absolute after:inset-x-0 after:bottom-0 after:h-[3px] after:rounded-t after:bg-primary' : 'text-ink-muted hover:text-ink'}`}
                        >
                            <Icon name={item.icon} size={18} strokeWidth={isActive(item) ? 2.4 : 2} className="xl:hidden" />
                            <span className="hidden xl:inline">{item.label}</span>
                        </Link>
                    ))}
                </nav>
                <div className="ml-auto flex items-center gap-2">
                    {notifications && (
                        <Link
                            href={notifications.href}
                            data-tour="nav-notifications"
                            aria-label="Notifications"
                            className={`relative grid size-11 place-items-center rounded-control border transition hover:border-primary/40 hover:bg-primary-soft ${isActive(notifications) ? 'border-primary/40 bg-primary-soft text-primary' : 'border-line bg-surface'}`}
                        >
                            <Icon name="bell" size={18} />
                            {!!notifications.badge && (
                                <span className="absolute -right-1 -top-1 grid min-w-5 place-items-center rounded-full bg-primary px-1 text-[10px] font-bold text-white ring-2 ring-surface">
                                    {notifications.badge > 9 ? '9+' : notifications.badge}
                                </span>
                            )}
                        </Link>
                    )}
                    {nav?.isAdmin && (
                        <Link href="/admin" className="inline-flex h-11 items-center gap-1.5 rounded-control border border-line bg-surface px-3.5 text-[0.78rem] font-bold transition hover:border-primary/40 hover:bg-primary-soft">
                            <Icon name="shield" size={16} /> <span className="hidden lg:inline">SLD Admin</span>
                        </Link>
                    )}
                    {user && (
                        <Link href="/me" data-tour="nav-profile" aria-label="Profile" className={`flex h-11 items-center gap-2 rounded-control border px-1.5 transition hover:border-primary/40 lg:pr-3 ${isActive({ label: 'Profile', icon: 'user', href: '/me', match: '/me' }) ? 'border-primary/40 bg-primary-soft' : 'border-line bg-surface'}`}>
                            <Avatar src={user.avatar_url} name={user.name} size="xs" />
                            <span className="hidden max-w-32 truncate text-[0.78rem] font-bold lg:inline">{user.name.split(' ')[0]}</span>
                        </Link>
                    )}
                    <button type="button" aria-label="Sign out" title="Sign out" className="grid size-11 place-items-center rounded-control bg-ink text-white transition hover:bg-ink/85" onClick={() => router.post('/logout')}>
                        <Icon name="logout" size={18} />
                    </button>
                </div>
            </header>

            <div className="min-w-0 pb-20 md:pb-0">
                {/* Mobile top bar */}
                <header className="sticky top-0 z-30 flex h-14 items-center justify-between border-b border-line bg-surface/90 px-4 backdrop-blur md:hidden">
                    <Link href="/feed" aria-label="Campus Hub home">
                        <Brand />
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

                <main className={`mx-auto w-full min-w-0 px-4 py-6 md:px-8 md:py-8 ${wide ? 'max-w-6xl' : 'max-w-4xl'}`}>{children}</main>
                <p className="px-4 pb-6 text-center text-xs text-ink-muted">All times are Malaysia time (MYT).</p>
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
                                    <Avatar src={user.avatar_url} name={user.name} size="xs" className={isActive(item) ? 'rounded-full ring-2 ring-primary' : ''} />
                                ) : (
                                    <Icon name={item.icon} size={26} strokeWidth={isActive(item) ? 2.4 : 1.8} />
                                )}
                                {!!item.badge && <span className="absolute right-2.5 top-2.5 size-2.5 rounded-full bg-primary-strong ring-2 ring-surface" />}
                            </Link>
                        </li>
                    ))}
                </ul>
            </nav>
        </div>
    );
}
