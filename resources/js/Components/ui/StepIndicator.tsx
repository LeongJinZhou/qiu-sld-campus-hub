const dots = {
    done: 'bg-success text-white',
    current: 'bg-ink text-white ring-4 ring-ink/10',
    returned: 'bg-warning text-white',
    pending: 'bg-surface-muted text-ink-muted',
};

/** One evenly spaced row on any screen: circles joined by a line, labels underneath. */
export default function StepIndicator({ steps }: { steps: { label: string; state: keyof typeof dots }[] }) {
    return (
        <ol className="grid" style={{ gridTemplateColumns: `repeat(${steps.length}, minmax(0, 1fr))` }}>
            {steps.map((step, index) => (
                <li key={step.label} className="relative grid justify-items-center gap-1.5 text-center">
                    {index < steps.length - 1 && <span className={`absolute left-1/2 top-3.5 h-0.5 w-full ${step.state === 'done' ? 'bg-success' : 'bg-line'}`} aria-hidden="true" />}
                    <span className={`relative grid size-7 place-items-center rounded-full text-xs font-semibold ${dots[step.state]}`} aria-current={step.state === 'current' ? 'step' : undefined}>
                        {step.state === 'done' ? '✓' : index + 1}
                    </span>
                    <span className={`text-xs ${step.state === 'pending' ? 'text-ink-muted' : 'font-medium'}`}>{step.label}</span>
                </li>
            ))}
        </ol>
    );
}
