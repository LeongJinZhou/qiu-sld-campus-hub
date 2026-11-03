import ApplicationForm from '@react/Components/clubs/ApplicationForm';
import AppLayout from '@react/Layouts/AppLayout';
import { Head, Link } from '@inertiajs/react';

interface ApplyProps {
    categories: { id: number; name: string }[];
    advisors: { id: number; name: string }[];
    positions: string[];
    me: { student_number: string; name: string } | null;
    mine: { id: number; name: string; status: string }[];
}

export default function Apply({ categories, advisors, positions, me, mine }: ApplyProps) {
    return (
        <AppLayout>
            <Head title="Start a new club" />
            <div className="grid gap-5">
                <header>
                    <h1 className="text-2xl font-bold">Start a new club</h1>
                    <p className="text-sm text-ink-muted">You apply and sign as President → your advisor signs → SLD reviews. The club opens once SLD approves.</p>
                </header>
                {mine.length > 0 && (
                    <section className="grid gap-2 rounded-card border border-line bg-surface p-4 shadow-card">
                        <h2 className="text-sm font-semibold">Your applications</h2>
                        {mine.map((a) => (
                            <Link key={a.id} href={`/club-applications/${a.id}`} className="flex justify-between text-sm hover:underline">
                                <span>{a.name}</span>
                                <span className="text-ink-muted">{a.status}</span>
                            </Link>
                        ))}
                    </section>
                )}
                <ApplicationForm action="/clubs-apply" categories={categories} advisors={advisors} positions={positions} me={me} />
            </div>
        </AppLayout>
    );
}
