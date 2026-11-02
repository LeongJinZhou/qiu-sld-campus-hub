import { useDebounced } from '@react/hooks/useDebounced';
import { forwardRef, useEffect, useId, useImperativeHandle, useState, type KeyboardEvent } from 'react';
import Icon from './Icon';

export interface Person {
    student_number: string;
    name: string;
    email: string | null;
    programme: string | null;
}

export interface PersonPickerHandle {
    clear: () => void;
}

interface PersonPickerProps {
    /** The chosen student ID. */
    value: string;
    onChange: (studentNumber: string) => void;
    onSelect?: (person: Person | null) => void;
    label?: string;
    error?: string;
    placeholder?: string;
    required?: boolean;
}

/**
 * Type a name, QIU email or student ID; matching students appear as you type. Picking one fills the name,
 * email and student ID from university records (the value is the student ID).
 */
const PersonPicker = forwardRef<PersonPickerHandle, PersonPickerProps>(function PersonPicker(
    { value, onChange, onSelect, label = 'Name, email or student ID', error, placeholder = 'Start typing a name or email…', required = false },
    ref,
) {
    const id = useId();
    const [q, setQ] = useState('');
    const [results, setResults] = useState<Person[]>([]);
    const [picked, setPicked] = useState<Person | null>(null);
    const [open, setOpen] = useState(false);
    const [loading, setLoading] = useState(false);
    const [active, setActive] = useState(0);
    const query = useDebounced(q, 250);

    useEffect(() => {
        if (picked || query.trim().length < 1) {
            setResults([]);
            return;
        }
        let cancelled = false;
        setLoading(true);
        fetch(`/people/lookup?q=${encodeURIComponent(query.trim())}`, { headers: { Accept: 'application/json' } })
            .then(async (res) => (res.ok ? ((await res.json()) as Person[]) : []))
            .then((people) => {
                if (cancelled) return;
                setResults(people);
                setActive(0);
                setOpen(true);
            })
            .catch(() => undefined)
            .finally(() => !cancelled && setLoading(false));
        return () => {
            cancelled = true;
        };
    }, [query, picked]);

    // Cleared from outside (e.g. the form was reset).
    useEffect(() => {
        if (value === '' && picked) setPicked(null);
    }, [value, picked]);

    const choose = (person: Person): void => {
        setPicked(person);
        onChange(person.student_number);
        setOpen(false);
        onSelect?.(person);
    };
    const clear = (): void => {
        setPicked(null);
        setQ('');
        onChange('');
        onSelect?.(null);
    };
    useImperativeHandle(ref, () => ({ clear }));

    const onKey = (e: KeyboardEvent<HTMLInputElement>): void => {
        if (!open || !results.length) return;
        if (e.key === 'ArrowDown') setActive((active + 1) % results.length);
        else if (e.key === 'ArrowUp') setActive((active - 1 + results.length) % results.length);
        else if (e.key === 'Enter') choose(results[active] as Person);
        else if (e.key === 'Escape') setOpen(false);
        else return;
        e.preventDefault();
    };

    return (
        <div className="relative grid gap-1.5">
            <label htmlFor={id} className="text-sm font-medium text-ink">
                {label}
                {required && <span className="text-danger"> *</span>}
            </label>
            {picked ? (
                <div className="fade-up flex items-center gap-3 rounded-control border border-brand-cyan/50 bg-brand-cyan/5 px-3 py-2 text-sm">
                    <span className="grid size-8 shrink-0 place-items-center rounded-full bg-gradient-to-br from-brand-cyan to-brand-blue text-xs font-bold text-white">{picked.name.slice(0, 1)}</span>
                    <span className="min-w-0 flex-1">
                        <span className="block truncate font-semibold">{picked.name}</span>
                        <span className="block truncate text-xs text-ink-muted">
                            {picked.student_number} · {picked.email}
                            {picked.programme && ` · ${picked.programme}`}
                        </span>
                    </span>
                    <button type="button" className="text-xs font-medium text-brand-blue" onClick={clear}>
                        Change
                    </button>
                </div>
            ) : (
                <div className="relative">
                    <Icon name="search" size={16} className="pointer-events-none absolute left-3 top-1/2 -translate-y-1/2 text-ink-muted" />
                    <input
                        id={id}
                        value={q}
                        onChange={(e) => setQ(e.target.value)}
                        type="search"
                        role="combobox"
                        autoComplete="off"
                        aria-expanded={open}
                        aria-controls={`${id}-list`}
                        aria-invalid={!!error}
                        placeholder={placeholder}
                        className="h-10 w-full rounded-control border border-line bg-surface pl-9 pr-9 text-sm outline-none transition focus:border-brand-cyan focus:ring-2 focus:ring-brand-cyan/30 aria-invalid:border-danger"
                        onKeyDown={onKey}
                        onFocus={() => setOpen(results.length > 0)}
                        onBlur={() => window.setTimeout(() => setOpen(false), 150)}
                    />
                    {loading && <span className="absolute right-3 top-1/2 size-4 -translate-y-1/2 animate-spin rounded-full border-2 border-brand-cyan border-t-transparent" />}
                </div>
            )}
            {open && !picked && q.trim().length >= 1 && (
                <ul id={`${id}-list`} role="listbox" className="absolute inset-x-0 top-full z-30 mt-1 max-h-72 overflow-y-auto rounded-control border border-line bg-surface py-1 shadow-pop">
                    {results.map((p, i) => (
                        <li
                            key={p.student_number}
                            role="option"
                            aria-selected={i === active}
                            className={`cursor-pointer px-3 py-2 text-sm ${i === active ? 'bg-surface-muted' : ''}`}
                            onMouseDown={(e) => {
                                e.preventDefault();
                                choose(p);
                            }}
                            onMouseEnter={() => setActive(i)}
                        >
                            <span className="block font-medium">{p.name}</span>
                            <span className="block text-xs text-ink-muted">
                                {p.student_number} · {p.email}
                                {p.programme && ` · ${p.programme}`}
                            </span>
                        </li>
                    ))}
                    {!results.length && !loading && <li className="px-3 py-2 text-sm text-ink-muted">No active student matches.</li>}
                </ul>
            )}
            {error && <p className="text-xs text-danger">{error}</p>}
        </div>
    );
});

export default PersonPicker;
