import type { ReactNode } from 'react';

/** Label, control and message, laid out the same way for every form field. */
export function FieldLabel({ id, label, required }: { id: string; label?: string; required?: boolean }) {
    if (!label) return null;
    return (
        <label htmlFor={id} className="text-sm font-medium text-ink">
            {label}
            {required && <span className="text-danger"> *</span>}
        </label>
    );
}

export function FieldMessage({ id, error, hint }: { id: string; error?: string; hint?: string }): ReactNode {
    if (!error && !hint) return null;
    return (
        <p id={`${id}-msg`} className={`text-xs ${error ? 'text-danger' : 'text-ink-muted'}`}>
            {error ?? hint}
        </p>
    );
}
