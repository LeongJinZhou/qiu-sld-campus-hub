import { rm } from '@/lib/format';
import { useState, type PointerEvent } from 'react';
import styles from './MoneyChart.module.css';

interface MoneyRow {
    key: string;
    label?: string;
    income_sen: number;
    expense_sen: number;
}

// "Aug 26" labels: the month on top, and the year underneath only where it changes (first bar, January), so 12 fit.
const axis = (r: MoneyRow, i: number): { top: string; bottom: string | null } => {
    const m = /^(\w{3}) (\d{2})$/.exec(r.label ?? '');
    if (!m) return { top: r.label ?? r.key, bottom: null };
    return { top: m[1]!, bottom: i === 0 || m[1] === 'Jan' ? `'${m[2]}` : null };
};

/**
 * Income and expenses side by side per period (month or year): two series, one axis (RM), a legend, a hover
 * tooltip on every bar and a table view. Colours are the validated blue/orange pair, re-stepped for dark mode.
 */
export default function MoneyChart({ title, rows }: { title: string; rows: MoneyRow[] }) {
    const [tip, setTip] = useState<{ x: number; y: number; lines: string[] } | null>(null);
    const [table, setTable] = useState(false);
    const max = Math.max(1, ...rows.flatMap((r) => [r.income_sen, r.expense_sen]));
    const h = (sen: number): string => `${Math.max(sen ? 2 : 0, (sen / max) * 100)}%`;
    const show = (e: PointerEvent, r: MoneyRow): void =>
        setTip({ x: e.clientX, y: e.clientY, lines: [r.label ?? r.key, `Income ${rm(r.income_sen)}`, `Expenses ${rm(r.expense_sen)}`, `Net ${rm(r.income_sen - r.expense_sen)}`] });

    return (
        <section className={`${styles.chart} grid gap-3 rounded-card border border-line bg-surface p-4 shadow-card`} onPointerLeave={() => setTip(null)}>
            <header className="flex flex-wrap items-center justify-between gap-2">
                <h3 className="font-semibold">{title}</h3>
                <div className="flex items-center gap-3 text-xs text-ink-muted">
                    <span className="flex items-center gap-1">
                        <span className="size-2.5 rounded-sm bg-[var(--income)]" /> Income
                    </span>
                    <span className="flex items-center gap-1">
                        <span className="size-2.5 rounded-sm bg-[var(--expense)]" /> Expenses
                    </span>
                    <button type="button" className="rounded-control border border-line px-2 py-1" aria-pressed={table} onClick={() => setTable(!table)}>
                        {table ? 'Chart' : 'Table'}
                    </button>
                </div>
            </header>
            {table ? (
                <table className="w-full text-sm">
                    <thead className="text-left text-xs text-ink-muted">
                        <tr>
                            <th className="py-1">Period</th>
                            <th className="text-right">Income</th>
                            <th className="text-right">Expenses</th>
                            <th className="text-right">Net</th>
                        </tr>
                    </thead>
                    <tbody>
                        {rows.map((r) => (
                            <tr key={r.key} className="border-t border-line tabular-nums">
                                <td className="py-1.5">{r.label ?? r.key}</td>
                                <td className="text-right">{rm(r.income_sen)}</td>
                                <td className="text-right">{rm(r.expense_sen)}</td>
                                <td className={`text-right ${r.income_sen - r.expense_sen < 0 ? 'text-danger' : ''}`}>{rm(r.income_sen - r.expense_sen)}</td>
                            </tr>
                        ))}
                    </tbody>
                </table>
            ) : (
                <>
                    <div className="flex h-44 items-end gap-1.5 border-b border-line" role="img" aria-label={`${title}: income and expenses per period`}>
                        {rows.map((r) => (
                            <div key={r.key} className="flex h-full flex-1 items-end justify-center gap-[2px]" onPointerMove={(e) => show(e, r)}>
                                <span className="w-[42%] rounded-t-[4px] bg-[var(--income)] transition-[height] duration-700" style={{ height: h(r.income_sen) }} />
                                <span className="w-[42%] rounded-t-[4px] bg-[var(--expense)] transition-[height] duration-700" style={{ height: h(r.expense_sen) }} />
                            </div>
                        ))}
                    </div>
                    <div className="flex gap-1.5 text-center text-[10px] text-ink-muted">
                        {rows.map((r, i) => {
                            const a = axis(r, i);
                            return (
                                <span key={r.key} className="min-w-0 flex-1 leading-tight">
                                    <span className="block truncate">{a.top}</span>
                                    {a.bottom && <span className="block truncate">{a.bottom}</span>}
                                </span>
                            );
                        })}
                    </div>
                </>
            )}
            {tip && (
                <div className="pointer-events-none fixed z-50 grid gap-0.5 rounded-control border border-line bg-surface px-3 py-2 text-xs shadow-pop" style={{ left: `${tip.x + 12}px`, top: `${tip.y + 12}px` }} role="tooltip">
                    <strong>{tip.lines[0]}</strong>
                    {tip.lines.slice(1).map((l) => (
                        <span key={l} className="text-ink-muted">
                            {l}
                        </span>
                    ))}
                </div>
            )}
        </section>
    );
}
