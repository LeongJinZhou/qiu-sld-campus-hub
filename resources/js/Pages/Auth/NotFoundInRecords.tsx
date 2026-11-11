import Card from '@/Components/ui/Card';
import GuestLayout from '@/Layouts/GuestLayout';
import { Head, Link } from '@inertiajs/react';

export default function NotFoundInRecords() {
    return (
        <GuestLayout>
            <Head title="Not found in QIU records" />
            <Card className="grid gap-4 text-center">
                <p className="text-4xl" aria-hidden="true">
                    🔍
                </p>
                <h1 className="text-lg font-semibold">Not found in QIU records</h1>
                <p className="text-sm text-ink-muted">
                    We could not match this Google account to a current QIU student or staff record. Sign in with your <strong>@qiu.edu.my</strong> account, or contact the Student Life Division (SLD) if you think this
                    is a mistake.
                </p>
                <Link href="/" className="text-sm font-medium text-primary">
                    Try another account
                </Link>
            </Card>
        </GuestLayout>
    );
}
