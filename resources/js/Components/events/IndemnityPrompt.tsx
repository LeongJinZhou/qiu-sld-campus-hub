import Button from '@/Components/ui/Button';
import Icon from '@/Components/ui/Icon';
import Modal from '@/Components/ui/Modal';
import { Link } from '@inertiajs/react';
import { useEffect, useState } from 'react';

/** Pops up once a place is confirmed for an outdoor event: sign the indemnity to get the ticket. */
export default function IndemnityPrompt({ slug, name, closesAt }: { slug: string; name: string; closesAt?: string | null }) {
    const [open, setOpen] = useState(false);
    useEffect(() => {
        const timer = window.setTimeout(() => setOpen(true), 350);
        return () => window.clearTimeout(timer);
    }, []);

    return (
        <>
            <div className="flex items-center gap-3 rounded-card border border-warning/40 bg-warning/10 p-3 text-sm">
                <Icon name="file" size={20} className="text-warning" />
                <p className="flex-1">One step left: sign the indemnity form to get your ticket.</p>
                <Link href={`/events/${slug}/indemnity`} className="font-semibold text-primary">
                    Sign
                </Link>
            </div>
            <Modal open={open} onClose={() => setOpen(false)}>
                <div className="grid justify-items-center gap-4 py-2 text-center">
                    <span className="grid size-16 animate-float-soft place-items-center rounded-full bg-gradient-to-br from-accent to-primary-strong text-white shadow-pop">
                        <Icon name="file" size={30} />
                    </span>
                    <div className="grid gap-1">
                        <h2 className="text-lg font-bold">One more step</h2>
                        <p className="text-sm text-ink-muted">{name} is an outdoor event. Sign the indemnity form and your ticket appears. Your name and details are already filled in.</p>
                        {closesAt && <p className="text-xs text-ink-muted">Signing closes {closesAt}.</p>}
                    </div>
                    <Link href={`/events/${slug}/indemnity`} className="w-full">
                        <Button block size="lg">
                            Sign now
                        </Button>
                    </Link>
                    <button type="button" className="text-sm text-ink-muted underline" onClick={() => setOpen(false)}>
                        Later (you will be reminded)
                    </button>
                </div>
            </Modal>
        </>
    );
}
