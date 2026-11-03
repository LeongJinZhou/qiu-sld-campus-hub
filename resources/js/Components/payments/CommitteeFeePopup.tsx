import Avatar from '@/Components/ui/Avatar';
import Icon from '@/Components/ui/Icon';
import Modal from '@/Components/ui/Modal';
import { date, rm } from '@/lib/format';
import { Link, usePage } from '@inertiajs/react';
import { useEffect, useState } from 'react';

/** At every sign-in: club committee members who have not paid their own club fee are reminded (7 days to pay). */
export default function CommitteeFeePopup() {
    const fees = usePage().props.committeeFees;
    const [dues, setDues] = useState(fees ?? []);
    const [open, setOpen] = useState(false);

    useEffect(() => {
        if (!fees?.length) return;
        setDues(fees);
        const timer = window.setTimeout(() => setOpen(true), 400);
        return () => window.clearTimeout(timer);
    }, [fees]);

    return (
        <Modal open={open} onClose={() => setOpen(false)}>
            <div className="grid gap-4">
                <div className="grid justify-items-center gap-2 text-center">
                    <span className="grid size-14 animate-float-soft place-items-center rounded-full bg-gradient-to-br from-brand-yellow to-brand-orange text-white shadow-pop">
                        <Icon name="award" size={26} />
                    </span>
                    <h2 className="text-lg font-bold">Committee members pay the club fee too</h2>
                    <p className="text-sm text-ink-muted">Lead by example: pay within 7 days of this reminder. It shows each time you sign in until you have paid.</p>
                </div>
                <ul className="grid gap-2">
                    {dues.map((d) => (
                        <li key={d.club.slug} className={`flex items-center gap-3 rounded-card border p-3 ${d.overdue ? 'border-danger/40 bg-danger/5' : 'border-line'}`}>
                            <Avatar src={d.club.logo_url} name={d.club.name} size="sm" />
                            <span className="min-w-0 flex-1 text-sm">
                                <strong className="block truncate">{d.club.name}</strong>
                                <span className="text-xs text-ink-muted">
                                    {d.position} · {rm(d.fee_sen)} ·{' '}
                                </span>
                                <span className={`text-xs font-semibold ${d.overdue ? 'text-danger' : 'text-warning'}`}>
                                    {d.overdue ? `overdue since ${date(d.due_on)}` : d.days_left === 0 ? 'due today' : `${d.days_left} day${d.days_left === 1 ? '' : 's'} left`}
                                </span>
                            </span>
                            <Link href={d.url} onClick={() => setOpen(false)} className="inline-flex h-8 items-center rounded-control bg-ink px-3 text-sm font-medium text-white hover:bg-ink/90">
                                Pay
                            </Link>
                        </li>
                    ))}
                </ul>
                <button type="button" className="text-sm text-ink-muted underline" onClick={() => setOpen(false)}>
                    Remind me next time
                </button>
            </div>
        </Modal>
    );
}
