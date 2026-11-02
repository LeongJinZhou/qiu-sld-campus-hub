import { useId } from 'react';
import { FieldLabel, FieldMessage } from './Field';

interface TextareaProps {
    label?: string;
    value: string | null | undefined;
    onChange: (value: string) => void;
    error?: string;
    rows?: number;
    readOnly?: boolean;
    placeholder?: string;
    required?: boolean;
}

export default function Textarea({ label, value, onChange, error, rows = 4, readOnly = false, placeholder, required }: TextareaProps) {
    const id = useId();
    return (
        <div className="grid gap-1.5">
            <FieldLabel id={id} label={label} required={required} />
            <textarea
                id={id}
                value={value ?? ''}
                onChange={(e) => onChange(e.target.value)}
                rows={rows}
                readOnly={readOnly}
                placeholder={placeholder}
                aria-invalid={!!error}
                className="rounded-control border border-line bg-surface px-3 py-2 text-sm outline-none focus:border-brand-cyan focus:ring-2 focus:ring-brand-cyan/30 read-only:bg-surface-muted"
            />
            <FieldMessage id={id} error={error} />
        </div>
    );
}
