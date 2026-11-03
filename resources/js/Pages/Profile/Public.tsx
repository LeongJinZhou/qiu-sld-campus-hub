import ProfileView from '@/Components/profile/ProfileView';
import type { ProfileData } from '@/types';
import { Head, Link } from '@inertiajs/react';

export default function Public({ profile }: { profile: ProfileData }) {
    return (
        <div className="min-h-dvh bg-surface-muted">
            <Head title={profile.name} />
            <header className="flex h-14 items-center justify-between border-b border-line bg-surface px-4">
                <Link href="/feed" className="flex items-center gap-2">
                    <img src="/brand/student-activity-black.png" alt="QIU Student Activity" className="h-8 w-auto" />
                    <span className="font-bold">
                        <span className="text-gradient-warm">Campus</span> <span className="text-gradient-cool">Hub</span>
                    </span>
                </Link>
                <span className="text-xs text-ink-muted">Verified QIU profile</span>
            </header>
            <main className="mx-auto max-w-3xl px-4 py-6">
                <ProfileView profile={profile} />
            </main>
        </div>
    );
}
