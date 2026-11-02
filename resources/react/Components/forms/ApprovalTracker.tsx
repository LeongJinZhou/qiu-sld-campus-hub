import Icon, { type IconName } from '@react/Components/ui/Icon';
import { cx } from '@react/lib/cx';
import type { ApprovalProgress, ApprovalStep } from '@/types';
import { useEffect, useState } from 'react';

const tones: Record<string, string> = {
    success: 'from-success/15 to-brand-cyan/10 text-success',
    warning: 'from-warning/15 to-brand-yellow/10 text-warning',
    danger: 'from-danger/15 to-brand-magenta/10 text-danger',
    active: 'from-brand-magenta/10 to-brand-cyan/10 text-ink',
    neutral: 'from-surface-muted to-surface-muted text-ink-muted',
};
type StepState = 'done' | 'current' | 'returned' | 'rejected' | 'todo';
const stateOf = (s: ApprovalStep): StepState =>
    s.decision === 'approved' ? 'done' : s.is_current ? 'current' : s.decision === 'returned' ? 'returned' : s.decision === 'rejected' ? 'rejected' : 'todo';

function headline(p: ApprovalProgress): { tone: string; icon: IconName; title: string; body: string } {
    switch (p.status) {
        case 'approved':
            return { tone: 'success', icon: 'award', title: 'Fully approved', body: `All ${p.total} signatures collected.` };
        case 'returned':
            return { tone: 'warning', icon: 'edit', title: 'Returned to the club', body: 'Edit and resubmit. Earlier approvals stay unless the budget or dates change.' };
        case 'rejected':
            return { tone: 'danger', icon: 'x', title: 'Rejected', body: 'This document will not continue.' };
        case 'in_review':
            return { tone: 'active', icon: 'clock', title: `Waiting for ${p.current_role}`, body: p.waiting_on.length ? p.waiting_on.join(', ') : 'No one holds this role yet — SLD has been notified.' };
        default:
            return { tone: 'neutral', icon: 'file', title: 'Draft', body: 'Not submitted yet.' };
    }
}

/** Shows exactly where a document is in its approval chain and whose approval it is waiting for. */
export default function ApprovalTracker({ progress, steps = [], compact = false }: { progress: ApprovalProgress; steps?: ApprovalStep[]; compact?: boolean }) {
    const percent = progress.total ? Math.round((progress.done / progress.total) * 100) : 0;
    // Animate the bar from 0 on first paint so the movement is visible.
    const [shown, setShown] = useState(0);
    useEffect(() => {
        const frame = requestAnimationFrame(() => setShown(percent));
        return () => cancelAnimationFrame(frame);
    }, [percent]);
    const bar = cx(
        'h-full rounded-full transition-[width] duration-1000 ease-out',
        progress.status === 'approved' ? 'bg-success' : progress.status === 'returned' ? 'bg-warning' : progress.status === 'rejected' ? 'bg-danger' : 'bg-gradient-to-r from-brand-orange via-brand-magenta to-brand-cyan',
        progress.status === 'in_review' && 'animate-tracker-flow',
    );

    if (compact) {
        const [first, ...others] = progress.waiting_on;
        return (
            <div className="grid gap-1.5">
                <div className="h-1.5 overflow-hidden rounded-full bg-surface-muted">
                    <div className={bar} style={{ width: `${shown}%` }} />
                </div>
                <p className="text-xs text-ink-muted">
                    {progress.done}/{progress.total} signed
                    {progress.status === 'in_review' && (
                        <>
                            {' '}
                            · waiting for <strong className="text-ink">{progress.current_role}</strong>
                            {first && ` (${first}${others.length ? ` +${others.length}` : ''})`}
                        </>
                    )}
                </p>
            </div>
        );
    }

    const h = headline(progress);
    return (
        <section className="grid gap-5" aria-label="Approval progress">
            {/* Where it is now */}
            <div className={`grid gap-3 rounded-card bg-gradient-to-r p-4 ${tones[h.tone]}`}>
                <div className="flex items-start gap-3">
                    <span className={cx('grid size-10 shrink-0 place-items-center rounded-full bg-surface shadow-card', progress.status === 'in_review' && 'animate-tracker-pulse')}>
                        <Icon name={h.icon} size={20} />
                    </span>
                    <div className="grid gap-0.5">
                        <p className="font-semibold">{h.title}</p>
                        <p className="text-sm text-ink-muted">{h.body}</p>
                        {progress.status === 'in_review' && progress.due_at && (
                            <p className={`text-xs font-medium ${progress.overdue ? 'text-danger' : 'text-ink-muted'}`}>
                                {progress.overdue ? 'Overdue since' : 'Due'} {progress.due_at}
                            </p>
                        )}
                    </div>
                </div>
                <div>
                    <div className="mb-1 flex justify-between text-xs font-medium text-ink-muted">
                        <span>
                            {progress.done} of {progress.total} signed
                        </span>
                        <span>{percent}%</span>
                    </div>
                    <div className="h-2.5 overflow-hidden rounded-full bg-surface/70">
                        <div className={bar} style={{ width: `${shown}%` }} />
                    </div>
                </div>
            </div>

            {/* The chain at a glance */}
            {steps.length > 0 && (
                <ol className="flex items-start overflow-x-auto pb-1">
                    {steps.map((step, i) => {
                        const s = stateOf(step);
                        return (
                            <li key={step.id} className="relative flex min-w-[5.5rem] flex-1 flex-col items-center text-center">
                                {i > 0 && (
                                    <span className="absolute right-1/2 top-4 h-0.5 w-full -translate-y-1/2 overflow-hidden bg-line" aria-hidden="true">
                                        <span className="block h-full bg-success transition-[width] duration-700 ease-out" style={{ width: s === 'done' || s === 'current' ? '100%' : '0%', transitionDelay: `${i * 120}ms` }} />
                                    </span>
                                )}
                                <span
                                    className={cx(
                                        'relative z-10 grid size-8 place-items-center rounded-full text-xs font-bold',
                                        s === 'done' && 'animate-tracker-pop bg-success text-white',
                                        s === 'current' && 'animate-tracker-pulse bg-brand-magenta text-white',
                                        s === 'returned' && 'bg-warning text-white',
                                        s === 'rejected' && 'bg-danger text-white',
                                        s === 'todo' && 'border-2 border-line bg-surface text-ink-muted',
                                    )}
                                    style={{ animationDelay: s === 'done' ? `${i * 120}ms` : undefined }}
                                    aria-current={step.is_current ? 'step' : undefined}
                                >
                                    {s === 'done' ? <Icon name="check" size={14} strokeWidth={3} /> : step.step_no}
                                </span>
                                <span className={`mt-1.5 px-1 text-[11px] font-medium leading-tight ${s === 'todo' ? 'text-ink-muted' : 'text-ink'}`}>{step.role}</span>
                            </li>
                        );
                    })}
                </ol>
            )}

            {/* Detail per step */}
            {steps.length > 0 && (
                <ol className="grid gap-2">
                    {steps.map((step) => {
                        const s = stateOf(step);
                        const tone = s === 'done' ? 'text-success' : step.is_current ? 'text-brand-magenta' : s === 'returned' ? 'text-warning' : s === 'rejected' ? 'text-danger' : 'text-ink-muted';
                        return (
                            <li key={step.id} className={`grid gap-1 rounded-control border p-3 text-sm transition ${step.is_current ? 'border-brand-magenta/40 bg-brand-magenta/5' : 'border-line'}`}>
                                <div className="flex flex-wrap items-center justify-between gap-2">
                                    <strong>
                                        {step.step_no}. {step.role}
                                    </strong>
                                    <span className={`text-xs font-medium ${tone}`}>{s === 'done' ? 'Signed' : step.is_current ? 'Waiting' : s === 'todo' ? 'Next' : step.decision}</span>
                                </div>
                                {step.approver ? (
                                    <p className="text-ink-muted">
                                        {step.approver}
                                        {step.position && ` · ${step.position}`} · {step.decided_at}
                                    </p>
                                ) : (
                                    step.is_current && (
                                        <p className="text-ink-muted">
                                            Waiting for {step.waiting_on.join(', ') || 'an approver'}
                                            {step.due_at && ` · due ${step.due_at}`}
                                        </p>
                                    )
                                )}
                                {step.comment && <p className="rounded-control bg-surface-muted px-3 py-2">“{step.comment}”</p>}
                                {step.verification_code && (
                                    <a href={`/verify/${step.verification_code}`} className="text-xs text-brand-blue">
                                        Verify signature {step.verification_code}
                                    </a>
                                )}
                            </li>
                        );
                    })}
                </ol>
            )}
        </section>
    );
}
