import CommitteeFeePopup from '@/Components/payments/CommitteeFeePopup';
import Brand from '@/Components/brand/Brand';
import Avatar from '@/Components/ui/Avatar';
import Celebration from '@/Components/ui/Celebration';
import Drawer from '@/Components/ui/Drawer';
import Toast from '@/Components/ui/Toast';
import { useCardGlow } from '@/hooks/useCardGlow';
import { Link, usePage } from '@inertiajs/react';
import { useState, type ReactNode } from 'react';

export default function PortalLayout({ title, children }: { title: string; children?: ReactNode }) {
    const page = usePage();
    const user = page.props.auth.user;
    const [menuOpen, setMenuOpen] = useState(false);
    useCardGlow();

    const links = [
        { label: 'Dashboard', href: '/admin' },
        { label: 'Club performance', href: '/admin/performance' },
        { label: 'Submissions tracker', href: '/admin/tracking' },
        { label: 'Proposals & exports', href: '/admin/proposals' },
        { label: 'Meetings', href: '/admin/meetings' },
        { label: 'Clubs', href: '/admin/clubs' },
        { label: 'Staff', href: '/admin/staff' },
        { label: 'All members', href: '/admin/members' },
        { label: 'Fees & drives', href: '/admin/fees' },
        { label: 'Venue requests', href: '/admin/venues' },
        { label: 'AGM', href: '/admin/agm' },
        { label: 'Requests to clubs', href: '/admin/requests' },
        { label: 'Club finance', href: '/admin/finance' },
        { label: 'Moderation', href: '/admin/moderation' },
        { label: 'Settings', href: '/admin/settings' },
        { label: 'Admins & roles', href: '/admin/roles' },
        ...(user?.roles.includes('superadmin')
            ? [
                  { label: 'Student records sync', href: '/admin/sync' },
                  { label: 'Audit log', href: '/admin/audit' },
              ]
            : []),
        { label: '← Back to Campus Hub', href: '/feed' },
    ];
    const isActive = (href: string): boolean => (href === '/admin' ? page.url === '/admin' : page.url.startsWith(href));

    return (
        <div className="min-h-dvh md:grid md:grid-cols-[15rem_minmax(0,1fr)]">
            <Toast />
            <Celebration text={page.props.flash.celebrate} />
            <CommitteeFeePopup />
            <aside className="hidden border-r border-line bg-surface md:block">
                <div className="sticky top-0 grid gap-6 p-5">
                    <Link href="/admin" aria-label="SLD Admin home">
                        <Brand tag="ADMIN" />
                    </Link>
                    <p className="eyebrow -mb-3 px-3">SLD portal</p>
                    <nav className="grid gap-1 text-sm">
                        {links.map((link) => (
                            <Link key={link.href} href={link.href} aria-current={isActive(link.href) ? 'page' : undefined}
                                className={`relative rounded-control px-3 py-2 font-semibold transition ${isActive(link.href) ? 'bg-primary-soft text-primary before:absolute before:inset-y-1.5 before:left-0 before:w-[3px] before:rounded-r before:bg-primary' : 'text-ink-muted hover:bg-surface-muted hover:text-ink'}`}>
                                {link.label}
                            </Link>
                        ))}
                    </nav>
                </div>
            </aside>

            <div>
                <header className="sticky top-0 z-30 flex h-[4.5rem] items-center justify-between gap-3 border-b border-line bg-surface/95 px-4 backdrop-blur-md md:px-8">
                    <button type="button" className="md:hidden" aria-label="Open menu" onClick={() => setMenuOpen(true)}>
                        ☰
                    </button>
                    <div className="min-w-0">
                        <p className="eyebrow">SLD Admin</p>
                        <h1 className="truncate text-lg font-extrabold tracking-tight">{title}</h1>
                    </div>
                    {user && (
                        <Link href="/me">
                            <Avatar src={user.avatar_url} name={user.name} size="sm" />
                        </Link>
                    )}
                </header>
                <main className="mx-auto max-w-6xl px-4 py-6 md:px-8 md:py-8">{children}</main>
                <p className="px-4 pb-6 text-center text-xs text-ink-muted">All times are Malaysia time (MYT).</p>
            </div>

            <Drawer open={menuOpen} onClose={() => setMenuOpen(false)} title="SLD Admin" side="left">
                <nav className="grid gap-1 text-sm">
                    {links.map((link) => (
                        <Link key={link.href} href={link.href} className="rounded-control px-3 py-2 hover:bg-surface-muted" onClick={() => setMenuOpen(false)}>
                            {link.label}
                        </Link>
                    ))}
                </nav>
            </Drawer>
        </div>
    );
}
