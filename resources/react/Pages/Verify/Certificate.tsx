import Card from '@react/Components/ui/Card';
import GuestLayout from '@react/Layouts/GuestLayout';
import { Head } from '@inertiajs/react';

export default function Certificate({ code, certificate }: { code: string; certificate: { title: string; name: string; statement: string; issued_at: string } | null }) {
    return (
        <GuestLayout>
            <Head title="Verify certificate" />
            <Card className="grid gap-4">
                <img src="/brand/student-activity-black.png" alt="QIU Student Activity" className="mx-auto h-12 w-auto" />
                <h1 className="text-center text-lg font-semibold">Certificate verification</h1>
                <p className="text-center font-mono text-sm text-ink-muted">{code}</p>
                {certificate ? (
                    <dl className="grid grid-cols-[7rem_1fr] gap-x-3 gap-y-2 text-sm">
                        <dt className="text-ink-muted">Status</dt>
                        <dd className="font-semibold text-success">✓ Issued by QIU Student Life Division</dd>
                        <dt className="text-ink-muted">Certificate</dt>
                        <dd>{certificate.title}</dd>
                        <dt className="text-ink-muted">Awarded to</dt>
                        <dd>{certificate.name}</dd>
                        <dt className="text-ink-muted">For</dt>
                        <dd>
                            {certificate.name} {certificate.statement}
                        </dd>
                        <dt className="text-ink-muted">Issued</dt>
                        <dd>{certificate.issued_at}</dd>
                    </dl>
                ) : (
                    <p className="text-center text-sm font-medium text-danger">No certificate matches this code.</p>
                )}
            </Card>
        </GuestLayout>
    );
}
