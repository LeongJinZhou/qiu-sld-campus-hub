type Value = string | number;
const valueOf = (value: string): Value => (Number.isNaN(Number(value)) ? value : Number(value));

interface CheckboxGroupProps {
    options: Record<string | number, string>;
    value: Value[];
    onChange: (value: Value[]) => void;
    disabled?: boolean;
    columns?: 1 | 2 | 3;
    numbered?: boolean;
}

/**
 * Tick-box groups from the template (nature of event, SDGs, source of fund). Each option is a tappable card: box,
 * number and wrapped text line up in fixed columns, so long labels never push the next one out of place.
 * Numeric keys come back as numbers.
 */
export default function CheckboxGroup({ options, value, onChange, disabled, columns, numbered }: CheckboxGroupProps) {
    const cols = columns === 2 ? 'sm:grid-cols-2' : columns === 3 ? 'sm:grid-cols-2 lg:grid-cols-3' : '';
    return (
        <div className={`grid gap-2 ${cols}`}>
            {Object.entries(options).map(([key, label]) => {
                const v = valueOf(key);
                const checked = value.includes(v);
                return (
                    <label
                        key={key}
                        className={`grid min-h-11 cursor-pointer items-center gap-2 rounded-control border px-3 py-2 text-sm leading-snug transition-colors has-[:disabled]:cursor-default ${numbered ? 'grid-cols-[1rem_1.5rem_1fr]' : 'grid-cols-[1rem_1fr]'} ${checked ? 'border-brand-cyan bg-brand-cyan/5' : 'border-line hover:bg-surface-muted'}`}
                    >
                        <input type="checkbox" checked={checked} disabled={disabled} className="size-4 accent-ink" onChange={(e) => onChange(e.target.checked ? [...value, v] : value.filter((x) => x !== v))} />
                        {numbered && <span className="text-right text-xs font-semibold tabular-nums text-ink-muted">{key}.</span>}
                        <span className="min-w-0 break-words">{label}</span>
                    </label>
                );
            })}
        </div>
    );
}
