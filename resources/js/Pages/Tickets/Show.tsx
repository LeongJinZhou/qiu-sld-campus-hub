import IndemnityPrompt from '@/Components/events/IndemnityPrompt';
import TicketCard from '@/Components/tickets/TicketCard';
import Button from '@/Components/ui/Button';
import Icon from '@/Components/ui/Icon';
import AppLayout from '@/Layouts/AppLayout';
import type { Ticket } from '@/types';
import { Head, Link } from '@inertiajs/react';
import { useEffect } from 'react';

export default function Show({ ticket, qrSvg, mine }: { ticket: Ticket; qrSvg: string | null; mine: boolean }) {
    // Keep the screen on while the ticket is shown at the door.
    useEffect(() => {
        if (!mine || ticket.event.past || !('wakeLock' in navigator)) return;
        let lock: WakeLockSentinel | null = null;
        navigator.wakeLock
            .request('screen')
            .then((l) => (lock = l))
            .catch(() => undefined);
        return () => void lock?.release().catch(() => undefined);
    }, [mine, ticket.event.past]);

    return (
        <AppLayout>
            <Head title={`Ticket · ${ticket.event.name}`} />
            <div className="grid gap-5 pb-6">
                <Link href="/activities" className="flex items-center gap-1 text-sm text-primary">
                    <Icon name="ticket" size={16} /> All my activities
                </Link>
                {mine && ticket.event.needs_indemnity && ['paid', 'free'].includes(ticket.status) && (
                    <div className="mx-auto w-full max-w-sm">
                        <IndemnityPrompt slug={ticket.event.slug} name={ticket.event.name} />
                    </div>
                )}
                <TicketCard ticket={ticket} qrSvg={qrSvg} />
                <div className="mx-auto grid w-full max-w-sm gap-2">
                    {mine && ticket.status === 'unpaid' && (
                        <Link href={`/events/${ticket.event.slug}/pay`}>
                            <Button block>Pay now</Button>
                        </Link>
                    )}
                    <Link href={`/events/${ticket.event.slug}`}>
                        <Button block variant="ghost">
                            Event details
                        </Button>
                    </Link>
                </div>
            </div>
        </AppLayout>
    );
}
