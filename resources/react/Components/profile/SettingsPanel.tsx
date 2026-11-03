import Icon, { type IconName } from '@react/Components/ui/Icon';
import { Link, router, usePage } from '@inertiajs/react';
import { useState } from 'react';

type Theme = 'light' | 'dark' | 'system';
const OPTIONS: { value: Theme; label: string; icon: IconName }[] = [
    { value: 'light', label: 'Light', icon: 'sun' },
    { value: 'dark', label: 'Dark', icon: 'moon' },
    { value: 'system', label: 'System', icon: 'monitor' },
];

/** Profile → Settings: theme, replay the guides, notification settings, then Sign out (always last). */
export default function SettingsPanel() {
    const user = usePage().props.auth.user;
    const [theme, setThemeState] = useState<Theme>((user?.theme as Theme | undefined) ?? 'system');

    function setTheme(value: Theme): void {
        setThemeState(value);
        document.documentElement.dataset.theme = value; // saved to the account, never to the device
        router.put('/me/theme', { theme: value }, { preserveScroll: true, preserveState: true, only: ['auth'] });
    }
    // Replaying a guide marks it unseen, then opens the page where it runs.
    function replay(tour: 'student' | 'committee'): void {
        const to = tour === 'committee' && user?.committee_club ? `/clubs/${user.committee_club.slug}?tour=committee` : '/feed?tour=student';
        router.post('/me/tours', { tour, seen: false }, { onSuccess: () => router.visit(to) });
    }
    const row = 'flex items-center gap-3 rounded-control px-1 py-2 text-left text-sm font-medium hover:bg-surface-muted';

    return (
        <section className="grid gap-3" aria-labelledby="settings-title">
            <h2 id="settings-title" className="text-sm font-semibold uppercase tracking-wide text-ink-muted">
                Settings
            </h2>
            <div className="grid gap-3 rounded-card border border-line bg-surface p-4 shadow-card">
                <div className="grid gap-2">
                    <p className="text-sm font-medium">Appearance</p>
                    <div className="grid grid-cols-3 gap-1 rounded-control bg-surface-muted p-1" role="radiogroup" aria-label="Appearance">
                        {OPTIONS.map((o) => (
                            <button
                                key={o.value}
                                type="button"
                                role="radio"
                                aria-checked={theme === o.value}
                                className={`flex h-9 items-center justify-center gap-1.5 rounded-[0.6rem] text-sm font-medium transition ${theme === o.value ? 'bg-surface text-ink shadow-card' : 'text-ink-muted hover:text-ink'}`}
                                onClick={() => setTheme(o.value)}
                            >
                                <Icon name={o.icon} size={15} />
                                {o.label}
                            </button>
                        ))}
                    </div>
                    <p className="text-xs text-ink-muted">System follows your phone or computer.</p>
                </div>
                <div className="grid gap-1 border-t border-line pt-3">
                    <button type="button" className={row} onClick={() => replay('student')}>
                        <Icon name="sparkles" size={18} className="text-brand-magenta" /> Show me around again
                    </button>
                    {user?.committee_club && (
                        <button type="button" className={row} onClick={() => replay('committee')}>
                            <Icon name="users" size={18} className="text-brand-blue" /> Committee guide · {user.committee_club.name}
                        </button>
                    )}
                    <Link href="/notifications" className={row}>
                        <Icon name="bell" size={18} className="text-ink-muted" /> Notification settings
                    </Link>
                </div>
            </div>
            <button type="button" className="flex items-center gap-3 rounded-card border border-line bg-surface px-4 py-3 text-left text-sm font-medium text-danger shadow-card" onClick={() => router.post('/logout')}>
                <Icon name="logout" size={18} /> Sign out
            </button>
        </section>
    );
}
