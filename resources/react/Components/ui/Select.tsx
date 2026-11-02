import { useId } from 'react';
import { FieldLabel, FieldMessage } from './Field';

export type SelectValue = string | number;

interface SelectProps<T extends SelectValue> {
    label?: string;
    options: { value: T; label: string }[];
    value: T | null | undefined;
    onChange: (value: T) => void;
    error?: string;
    placeholder?: string;
    required?: boolean;
    disabled?: boolean;
}

/** A native select that hands back each option's own value (numbers stay numbers). */
export default function Select<T extends SelectValue>({ label, options, value, onChange, error, placeholder, required, disabled }: SelectProps<T>) {
    const id = useId();
    const index = options.findIndex((o) => o.value === value);
    return (
        <div className="grid gap-1.5">
            <FieldLabel id={id} label={label} required={required} />
            <select
                id={id}
                value={index === -1 ? '' : String(index)}
                onChange={(e) => {
                    const picked = options[Number(e.target.value)];
                    if (picked) onChange(picked.value);
                }}
                required={required}
                disabled={disabled}
                aria-invalid={!!error}
                className="h-10 rounded-control border border-line bg-surface px-3 text-sm outline-none focus:border-brand-cyan focus:ring-2 focus:ring-brand-cyan/30 disabled:bg-surface-muted"
            >
                {(placeholder || index === -1) && (
                    <option value="" disabled>
                        {placeholder ?? ''}
                    </option>
                )}
                {options.map((option, i) => (
                    <option key={String(option.value)} value={String(i)}>
                        {option.label}
                    </option>
                ))}
            </select>
            <FieldMessage id={id} error={error} />
        </div>
    );
}
