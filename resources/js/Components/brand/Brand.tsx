/** The QIU Campus Hub wordmark: the red QIU mark and the product name, as in the QIU Industry Day app. */
export default function Brand({ tag, compact = false }: { tag?: string; compact?: boolean }) {
    return (
        <span className="flex items-center gap-2.5 whitespace-nowrap">
            <span className="grid size-9 shrink-0 place-items-center rounded-[0.6rem] bg-primary text-[0.7rem] font-extrabold tracking-tight text-white shadow-[0_0.4rem_1rem_color-mix(in_srgb,var(--color-primary)_25%,transparent)]">QIU</span>
            {!compact && (
                <span className="text-[1.1rem] font-extrabold tracking-[-0.03em] text-ink">
                    Campus <span className="text-primary">Hub</span>
                </span>
            )}
            {tag && !compact && <small className="rounded-[0.3rem] border border-line px-1.5 py-0.5 font-mono text-[0.55rem] tracking-[0.08em] text-ink-muted">{tag}</small>}
        </span>
    );
}
