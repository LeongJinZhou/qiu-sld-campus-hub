import { useId } from 'react';
import { FieldLabel, FieldMessage } from './Field';

interface InputProps {
    label?: string;
    type?: string;
    value: string | number | null | undefined;
    onChange: (value: string) => void;
    error?: string;
    hint?: string;
    readOnly?: boolean;
    required?: boolean;
    placeholder?: string;
    maxLength?: number;
    min?: string | number;
    max?: string | number;
    autoComplete?: string;
}

export default function Input({ label, type = 'text', value, onChange, error, hint, readOnly = false, required = false, placeholder, maxLength, min, max, autoComplete }: InputProps) {
    const id = useId();
    return (
        <div className="grid gap-1.5">
            <FieldLabel id={id} label={label} required={required} />
            <input
                id={id}
                type={type}
                value={value ?? ''}
                onChange={(e) => onChange(e.target.value)}
                readOnly={readOnly}
                required={required}
                placeholder={placeholder}
                maxLength={maxLength}
                min={min}
                max={max}
                autoComplete={autoComplete}
                aria-invalid={!!error}
                aria-describedby={error || hint ? `${id}-msg` : undefined}
                className="h-10 rounded-control border border-line bg-surface px-3 text-sm outline-none transition focus:border-primary focus:ring-2 focus:ring-primary/30 read-only:bg-surface-muted read-only:text-ink-muted aria-invalid:border-danger"
            />
            <FieldMessage id={id} error={error} hint={hint} />
        </div>
    );
}
