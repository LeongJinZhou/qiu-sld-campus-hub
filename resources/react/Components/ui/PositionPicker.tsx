import { useEffect, useMemo, useState } from 'react';
import Input from './Input';
import Select from './Select';

const COMMON = [
    'Program Director',
    'Logistics Head',
    'Publicity Head',
    'Multimedia Head',
    'Sponsorship Head',
    'Food & Beverage Head',
    'Safety Officer',
    'Registration Head',
    'Assistant Secretary',
    'Assistant Treasurer',
    'Committee Member',
];
const OTHER = '__other__';

interface PositionPickerProps {
    value: string;
    onChange: (position: string) => void;
    label?: string;
    required?: string[];
    taken?: string[];
    error?: string;
}

/** Choose a committee position from the usual list (compulsory ones first), or "Other" to type your own. */
export default function PositionPicker({ value, onChange, label = 'Role', required = ['President', 'Vice President', 'Secretary', 'Treasurer'], taken = [], error }: PositionPickerProps) {
    const options = useMemo(
        () => [
            ...required.filter((p) => !taken.includes(p)).map((p) => ({ value: p, label: `${p} (compulsory)` })),
            ...COMMON.filter((p) => !required.includes(p)).map((p) => ({ value: p, label: p })),
            { value: OTHER, label: 'Other…' },
        ],
        [required, taken],
    );
    const [choice, setChoice] = useState<string | null>(value && !options.some((o) => o.value === value) ? OTHER : value || null);
    const [custom, setCustom] = useState(choice === OTHER ? value : '');

    useEffect(() => {
        if (value === '') {
            setChoice(null);
            setCustom('');
        }
    }, [value]);

    const pick = (next: string): void => {
        setChoice(next);
        onChange(next === OTHER ? custom : next);
    };

    return (
        <div className="grid gap-2">
            <Select value={choice} onChange={pick} label={label} options={options} placeholder="Choose a role" error={choice === OTHER ? undefined : error} />
            {choice === OTHER && (
                <Input
                    value={custom}
                    onChange={(text) => {
                        setCustom(text);
                        onChange(text);
                    }}
                    label="Role name"
                    placeholder="e.g. Decoration Head"
                    error={error}
                />
            )}
        </div>
    );
}
