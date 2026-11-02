/** A value the system already knows: shown, never editable. */
export default function LockedField({ label, value }: { label: string; value: string | number | null | undefined }) {
    return (
        <div className="grid gap-1">
            <span className="text-sm font-medium text-ink">{label}</span>
            <span className="flex min-h-10 items-center gap-2 rounded-control border border-line bg-surface-muted px-3 text-sm text-ink-muted">
                <span aria-hidden="true">🔒</span>
                {value || '—'}
            </span>
        </div>
    );
}
