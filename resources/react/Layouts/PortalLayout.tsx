import CommitteeFeePopup from '@react/Components/payments/CommitteeFeePopup';
import Avatar from '@react/Components/ui/Avatar';
import Celebration from '@react/Components/ui/Celebration';
import Drawer from '@react/Components/ui/Drawer';
import Toast from '@react/Components/ui/Toast';
import { Link, usePage } from '@inertiajs/react';
import { useState, type ReactNode } from 'react';

export default function PortalLayout({ title, children }: { title: string; children?: ReactNode }) {
    const page = usePage();
    const user = page.props.auth.user;
    const [menuOpen, setMenuOpen] = useState(false);

    const links = [
        { label: 'Dashboard', href: '/admin' },
        { label: 'Club performance', href: '/admin/performance' },
        { label: 'Submissions tracker', href: '/admin/tracking' },
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
        ...(user?.roles.includes('superadmin')
            ? [
                  { label: 'Roles', href: '/admin/roles' },
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
                    <Link href="/clubs">
                        <img src="/brand/student-activity-black.png" alt="QIU Student Activity" className="h-10 w-auto" />
                    </Link>
                    <nav className="grid gap-1 text-sm">
                        {links.map((link) => (
                            <Link key={link.href} href={link.href} className={`rounded-control px-3 py-2 ${isActive(link.href) ? 'bg-surface-muted font-medium' : 'text-ink-muted hover:bg-surface-muted'}`}>
                                {link.label}
                            </Link>
                        ))}
                    </nav>
                </div>
            </aside>

            <div>
                <header className="sticky top-0 z-30 flex h-14 items-center justify-between gap-3 border-b border-line bg-surface/90 px-4 backdrop-blur">
                    <button type="button" className="md:hidden" aria-label="Open menu" onClick={() => setMenuOpen(true)}>
                        ☰
                    </button>
                    <h1 className="truncate text-base font-semibold">{title}</h1>
                    {user && (
                        <Link href="/me">
                            <Avatar src={user.avatar_url} name={user.name} size="sm" />
                        </Link>
                    )}
                </header>
                <main className="mx-auto max-w-6xl px-4 py-6">{children}</main>
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
