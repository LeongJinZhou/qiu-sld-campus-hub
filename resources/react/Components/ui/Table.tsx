import { useMemo, useState, type ReactNode } from 'react';

interface Column<Row> {
    key: keyof Row & string;
    label: string;
    sortable?: boolean;
}

interface TableProps<Row extends object> {
    columns: Column<Row>[];
    rows: Row[];
    rowKey: keyof Row & string;
    /** Custom cell content per column key; the raw value is shown otherwise. */
    cells?: Partial<Record<keyof Row & string, (row: Row) => ReactNode>>;
    empty?: ReactNode;
}

export default function Table<Row extends object>({ columns, rows, rowKey, cells = {}, empty = 'Nothing here yet.' }: TableProps<Row>) {
    const [sortKey, setSortKey] = useState<(keyof Row & string) | null>(null);
    const [direction, setDirection] = useState<1 | -1>(1);

    const sortBy = (key: keyof Row & string): void => {
        setDirection(sortKey === key ? (direction === 1 ? -1 : 1) : 1);
        setSortKey(key);
    };
    const sorted = useMemo(() => {
        if (!sortKey) return rows;
        const text = (row: Row): string => String(row[sortKey] ?? '');
        return [...rows].sort((a, b) => text(a).localeCompare(text(b), undefined, { numeric: true }) * direction);
    }, [rows, sortKey, direction]);

    return (
        <div className="overflow-x-auto rounded-card border border-line bg-surface">
            <table className="w-full text-left text-sm">
                <thead className="bg-surface-muted text-xs uppercase tracking-wide text-ink-muted">
                    <tr>
                        {columns.map((column) => (
                            <th key={column.key} className="px-4 py-3 font-medium" aria-sort={sortKey === column.key ? (direction === 1 ? 'ascending' : 'descending') : undefined}>
                                {/* Buttons reset text-transform, so repeat the header style; SVG arrows (not ↕, which macOS draws as an emoji). */}
                                {column.sortable ? (
                                    <button
                                        type="button"
                                        className={`inline-flex items-center gap-1 font-medium uppercase tracking-wide hover:text-ink ${sortKey === column.key ? 'text-ink' : ''}`}
                                        onClick={() => sortBy(column.key)}
                                    >
                                        {column.label}
                                        <svg width="10" height="12" viewBox="0 0 10 12" aria-hidden="true" className="shrink-0">
                                            <path d="M5 1 9 5H1z" fill="currentColor" opacity={sortKey !== column.key ? 0.45 : direction === 1 ? 1 : 0.25} />
                                            <path d="M5 11 1 7h8z" fill="currentColor" opacity={sortKey !== column.key ? 0.45 : direction === 1 ? 0.25 : 1} />
                                        </svg>
                                    </button>
                                ) : (
                                    column.label
                                )}
                            </th>
                        ))}
                    </tr>
                </thead>
                <tbody>
                    {sorted.map((row) => (
                        <tr key={String(row[rowKey])} className="border-t border-line">
                            {columns.map((column) => (
                                <td key={column.key} className="px-4 py-3">
                                    {cells[column.key] ? cells[column.key]!(row) : String(row[column.key] ?? '')}
                                </td>
                            ))}
                        </tr>
                    ))}
                    {sorted.length === 0 && (
                        <tr>
                            <td colSpan={columns.length} className="px-4 py-8 text-center text-ink-muted">
                                {empty}
                            </td>
                        </tr>
                    )}
                </tbody>
            </table>
        </div>
    );
}
