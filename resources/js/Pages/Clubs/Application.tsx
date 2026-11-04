import ApplicationForm from '@/Components/clubs/ApplicationForm';
import Badge from '@/Components/ui/Badge';
import Button from '@/Components/ui/Button';
import SignaturePad from '@/Components/ui/SignaturePad';
import AppLayout from '@/Layouts/AppLayout';
import { Head, Link, router, useForm } from '@inertiajs/react';

interface ApplicationProps {
    application: {
        id: number;
        status: string;
        status_label: string;
        data: { name: string; category_id: number | null; bio: string; objectives: string; officers: Record<string, string> };
        officers: { position: string; student_number: string; name: string | null }[];
        note: string | null;
        category: string | null;
        applicant: string;
        advisor: string;
        advisor_id: number;
        advisor_signed_at: string | null;
        created_at: string | null;
        club_slug: string | null;
    };
    can: { sign: boolean; decide: boolean; resubmit: boolean };
    categories: { id: number; name: string }[];
    advisors: { id: number; name: string }[];
    positions: string[];
}

export default function Application({ application, can, categories, advisors, positions }: ApplicationProps) {
    const sign = useForm({ signature: null as string | null, signature_strokes: null as string | null });
    const back = (): void => {
        const note = prompt('What needs to change? (sent to the applicant)');
        if (note) router.post(`/club-applications/${application.id}/return`, { note }, { preserveScroll: true });
    };
    const decide = (approve: boolean): void => {
        const note = approve ? null : prompt('Why is it not approved? (sent to the applicant)');
        if (!approve && !note) return;
        router.post(`/club-applications/${application.id}/decide`, { approve, note }, { preserveScroll: true });
    };
    const approved = application.status === 'approved';

    return (
        <AppLayout>
            <Head title={`Club application · ${application.data.name}`} />
            <div className="grid gap-5">
                <header className="flex flex-wrap items-start justify-between gap-3">
                    <div>
                        <h1 className="text-2xl font-bold">{application.data.name}</h1>
                        <p className="text-sm text-ink-muted">
                            New club application by {application.applicant} · {application.created_at}
                        </p>
                    </div>
                    <Badge tone={approved ? 'success' : ['returned', 'rejected'].includes(application.status) ? 'danger' : 'warning'}>{application.status_label}</Badge>
                </header>
                <ol className="grid grid-cols-3 gap-1 text-center text-xs">
                    <li className="rounded-control bg-success/10 p-2 text-success">✓ President signed</li>
                    <li className={`rounded-control p-2 ${application.advisor_signed_at ? 'bg-success/10 text-success' : 'bg-surface-muted'}`}>
                        {application.advisor_signed_at ? '✓' : '2.'} Advisor ({application.advisor})
                    </li>
                    <li className={`rounded-control p-2 ${approved ? 'bg-success/10 text-success' : 'bg-surface-muted'}`}>{approved ? '✓' : '3.'} SLD approves</li>
                </ol>
                {application.note && <p className="rounded-card bg-warning/10 px-4 py-3 text-sm">{application.note}</p>}
                {application.club_slug && (
                    <p className="rounded-card bg-success/10 px-4 py-3 text-sm text-success">
                        The club is open.{' '}
                        <Link href={`/clubs/${application.club_slug}`} className="font-semibold underline">
                            Go to the club page
                        </Link>
                    </p>
                )}
                <section className="grid gap-3 rounded-card border border-line bg-surface p-4 text-sm shadow-card">
                    <p>
                        <strong>Category:</strong> {application.category ?? '—'}
                    </p>
                    <p>
                        <strong>Description:</strong> {application.data.bio}
                    </p>
                    <p className="whitespace-pre-line">
                        <strong>Aims and activities:</strong> {application.data.objectives}
                    </p>
                    <ul className="grid gap-1">
                        {application.officers.map((o) => (
                            <li key={o.position}>
                                <strong>{o.position}:</strong> {o.name} · {o.student_number}
                            </li>
                        ))}
                    </ul>
                </section>
                {can.sign && (
                    <section className="grid gap-3 rounded-card border-2 border-primary/40 bg-primary/5 p-4">
                        <h2 className="font-semibold">Your signature as advisor</h2>
                        <SignaturePad value={sign.data.signature} onChange={(png, strokes) => sign.setData({ signature: png, signature_strokes: strokes })} label="Sign to support this club" />
                        <div className="flex flex-wrap gap-2">
                            <Button disabled={!sign.data.signature} loading={sign.processing} onClick={() => sign.post(`/club-applications/${application.id}/sign`, { preserveScroll: true })}>
                                Sign
                            </Button>
                            <Button variant="secondary" onClick={back}>
                                Decline / ask for changes
                            </Button>
                        </div>
                    </section>
                )}
                {can.decide && (
                    <section className="flex flex-wrap gap-2 rounded-card border-2 border-primary/40 bg-primary/5 p-4">
                        <Button onClick={() => decide(true)}>Approve &amp; open the club</Button>
                        <Button variant="secondary" onClick={back}>
                            Return for changes
                        </Button>
                        <Button variant="danger" onClick={() => decide(false)}>
                            Reject
                        </Button>
                    </section>
                )}
                {can.resubmit && (
                    <>
                        <h2 className="font-semibold">Correct and sign again</h2>
                        <ApplicationForm
                            action={`/club-applications/${application.id}/resubmit`}
                            categories={categories}
                            advisors={advisors}
                            positions={positions}
                            initial={application.data}
                            advisorId={application.advisor_id}
                        />
                    </>
                )}
            </div>
        </AppLayout>
    );
}
