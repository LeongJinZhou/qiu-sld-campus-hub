interface CheckboxProps {
    label: string;
    checked: boolean;
    onChange: (checked: boolean) => void;
    disabled?: boolean;
}

export default function Checkbox({ label, checked, onChange, disabled }: CheckboxProps) {
    return (
        <label className="inline-flex cursor-pointer items-start gap-2 text-sm has-disabled:cursor-not-allowed has-disabled:opacity-60">
            <input type="checkbox" checked={checked} onChange={(e) => onChange(e.target.checked)} disabled={disabled} className="mt-0.5 size-4 rounded accent-ink" />
            <span>{label}</span>
        </label>
    );
}
