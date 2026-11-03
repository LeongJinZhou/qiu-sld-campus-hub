import Card from '@/Components/ui/Card';
import GuestLayout from '@/Layouts/GuestLayout';
import { Head } from '@inertiajs/react';

interface SignatureRecord {
    signer: string;
    position: string;
    signed_at: string;
    document: string | null;
    club: string | null;
    event: string | null;
    status: string | null;
}

export default function Show({ code, record }: { code: string; record: SignatureRecord | null }) {
    return (
        <GuestLayout>
            <Head title="Verify signature" />
            <Card className="grid gap-4">
                <img src="/brand/student-activity-black.png" alt="QIU Student Activity" className="mx-auto h-12 w-auto" />
                <h1 className="text-center text-lg font-semibold">Signature verification</h1>
                <p className="text-center font-mono text-sm text-ink-muted">{code}</p>
                {record ? (
                    <dl className="grid grid-cols-[7rem_1fr] gap-x-3 gap-y-2 text-sm">
                        <dt className="text-ink-muted">Status</dt>
                        <dd className="font-semibold text-success">✓ Valid signature</dd>
                        <dt className="text-ink-muted">Signed by</dt>
                        <dd>{record.signer}</dd>
                        <dt className="text-ink-muted">Position</dt>
                        <dd>{record.position}</dd>
                        <dt className="text-ink-muted">Signed at</dt>
                        <dd>{record.signed_at}</dd>
                        <dt className="text-ink-muted">Document</dt>
                        <dd>{record.document}</dd>
                        <dt className="text-ink-muted">Organisation</dt>
                        <dd>{record.club}</dd>
                        {record.event && (
                            <>
                                <dt className="text-ink-muted">Event</dt>
                                <dd>{record.event}</dd>
                            </>
                        )}
                        <dt className="text-ink-muted">Document status</dt>
                        <dd className="capitalize">{record.status?.replace('_', ' ')}</dd>
                    </dl>
                ) : (
                    <p className="text-center text-sm font-medium text-danger">No signature matches this code.</p>
                )}
            </Card>
        </GuestLayout>
    );
}
