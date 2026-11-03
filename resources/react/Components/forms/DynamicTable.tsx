interface Column<Row> {
    key: keyof Row & string;
    label: string;
    type?: 'text' | 'time' | 'textarea';
}

interface DynamicTableProps<Row extends Record<string, string>> {
    columns: Column<Row>[];
    rows: Row[];
    onChange: (rows: Row[]) => void;
    blank: Row;
    disabled?: boolean;
    errors?: Record<string, string>;
    errorPrefix: string;
    addLabel?: string;
    /** Rows that always stay (e.g. 2 objectives): their remove button is hidden. */
    min?: number;
    /** Columns that may be left empty, shown as "(optional)". */
    optional?: string[];
}

/** Repeating rows (activities, itinerary, objectives) with add/remove, matching the template tables. */
export default function DynamicTable<Row extends Record<string, string>>({ columns, rows, onChange, blank, disabled, errors, errorPrefix, addLabel, min = 0, optional }: DynamicTableProps<Row>) {
    const set = (index: number, key: keyof Row & string, value: string): void => onChange(rows.map((r, i) => (i === index ? { ...r, [key]: value } : r)));

    return (
        <div className="grid gap-2">
            <div className="overflow-x-auto rounded-control border border-line">
                <table className="w-full text-sm">
                    <thead className="bg-surface-muted text-left text-xs uppercase text-ink-muted">
                        <tr>
                            <th className="w-10 px-3 py-2">No</th>
                            {columns.map((column) => (
                                <th key={column.key} className="px-3 py-2">
                                    {column.label}
                                    {optional?.includes(column.key) && <span className="font-normal normal-case"> (optional)</span>}
                                </th>
                            ))}
                            {!disabled && <th className="w-10" />}
                        </tr>
                    </thead>
                    <tbody>
                        {rows.map((row, index) => (
                            <tr key={index} className="border-t border-line align-top">
                                <td className="px-3 py-2 text-ink-muted">{index + 1}</td>
                                {columns.map((column) => {
                                    const error = errors?.[`${errorPrefix}.${index}.${column.key}`];
                                    return (
                                        <td key={column.key} className="px-2 py-1.5">
                                            {column.type === 'textarea' ? (
                                                <textarea
                                                    value={row[column.key] ?? ''}
                                                    onChange={(e) => set(index, column.key, e.target.value)}
                                                    disabled={disabled}
                                                    rows={2}
                                                    aria-label={`${column.label} ${index + 1}`}
                                                    className="w-full rounded-control border border-line px-2 py-1.5 disabled:bg-surface-muted"
                                                />
                                            ) : (
                                                <input
                                                    value={row[column.key] ?? ''}
                                                    onChange={(e) => set(index, column.key, e.target.value)}
                                                    type={column.type ?? 'text'}
                                                    disabled={disabled}
                                                    aria-label={`${column.label} ${index + 1}`}
                                                    className="h-9 w-full rounded-control border border-line px-2 disabled:bg-surface-muted"
                                                />
                                            )}
                                            {error && <p className="mt-1 text-xs text-danger">{error}</p>}
                                        </td>
                                    );
                                })}
                                {!disabled && (
                                    <td className="px-2 py-2">
                                        {rows.length > min && (
                                            <button type="button" className="text-ink-muted hover:text-danger" aria-label={`Remove row ${index + 1}`} onClick={() => onChange(rows.filter((_, i) => i !== index))}>
                                                ✕
                                            </button>
                                        )}
                                    </td>
                                )}
                            </tr>
                        ))}
                    </tbody>
                </table>
            </div>
            {!disabled && (
                <button type="button" className="justify-self-start text-sm font-medium text-brand-blue" onClick={() => onChange([...rows, { ...blank }])}>
                    ＋ {addLabel ?? 'Add row'}
                </button>
            )}
        </div>
    );
}
