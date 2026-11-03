import { useState, type KeyboardEvent } from 'react';

export interface BudgetItem {
    description: string;
    price_sen: number;
    quantity: number;
}
export interface Budget {
    revenue: BudgetItem[];
    expenses: BudgetItem[];
}
type Side = keyof Budget;

export const rm = (sen: number): string => (sen / 100).toLocaleString('en-MY', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
const total = (items: BudgetItem[]): number => items.reduce((sum, i) => sum + i.price_sen * i.quantity, 0);
const blockKeys = (e: KeyboardEvent<HTMLInputElement>): void => {
    if (['-', '+', 'e', 'E', '.', ','].includes(e.key)) e.preventDefault();
};
const SECTIONS: { key: Side; label: string; hint: string }[] = [
    { key: 'revenue', label: 'Revenue', hint: 'Ticket sales, sponsorship, club fund…' },
    { key: 'expenses', label: 'Expenses', hint: 'Everything you plan to buy or pay for' },
];
const inputClass = 'h-10 w-full rounded-control border border-line text-sm outline-none transition focus:border-brand-cyan focus:ring-2 focus:ring-brand-cyan/30 disabled:bg-surface-muted';

/**
 * Revenue and expenses, money kept in sen. Prices are typed like on a calculator ("12.5" → RM 12.50 when you
 * leave the box), never negative; quantities are whole numbers from 1. Revenue must at least cover expenses.
 */
export default function BudgetTable({ budget, onChange, disabled, errors }: { budget: Budget; onChange: (b: Budget) => void; disabled?: boolean; errors?: Record<string, string> }) {
    // What the person is typing in a price box, kept as text until they leave it.
    const [typing, setTyping] = useState<Record<string, string>>({});
    const profit = total(budget.revenue) - total(budget.expenses);

    const update = (side: Side, index: number, patch: Partial<BudgetItem>): void =>
        onChange({ ...budget, [side]: budget[side].map((item, i) => (i === index ? { ...item, ...patch } : item)) });
    const priceText = (key: string, item: BudgetItem): string => typing[key] ?? (item.price_sen ? rm(item.price_sen).replace(/,/g, '') : '');
    function typePrice(key: string, side: Side, index: number, raw: string): void {
        // Digits and one dot, at most 2 decimals; no minus sign.
        const clean = raw
            .replace(/[^\d.]/g, '')
            .replace(/(\..*)\./g, '$1')
            .replace(/^(\d*\.\d{0,2}).*$/, '$1');
        setTyping((t) => ({ ...t, [key]: clean }));
        update(side, index, { price_sen: Math.round(Number(clean || 0) * 100) });
    }
    const leavePrice = (key: string): void =>
        setTyping((t) => {
            const { [key]: _gone, ...rest } = t;
            return rest;
        });
    const typeQuantity = (side: Side, index: number, raw: string): void =>
        update(side, index, { quantity: Math.max(1, Math.min(100000, Number.parseInt(raw.replace(/\D/g, '') || '1', 10))) });
    const add = (side: Side): void => onChange({ ...budget, [side]: [...budget[side], { description: '', price_sen: 0, quantity: 1 }] });
    const remove = (side: Side, index: number): void => onChange({ ...budget, [side]: budget[side].filter((_, i) => i !== index) });

    return (
        <div className="grid gap-4">
            {SECTIONS.map((section) => (
                <div key={section.key} className="grid gap-2">
                    <div className="flex items-baseline justify-between gap-2">
                        <h3 className="font-semibold">
                            {section.label} <span className="text-xs font-normal text-ink-muted">{section.hint}</span>
                        </h3>
                        <span className="text-sm font-semibold tabular-nums">RM {rm(total(budget[section.key]))}</span>
                    </div>
                    <ul className="relative grid gap-2">
                        {budget[section.key].map((item, index) => {
                            const key = `${section.key}${index}`;
                            const err = (field: string): string | undefined => errors?.[`budget.${section.key}.${index}.${field}`];
                            return (
                                <li
                                    key={index}
                                    className="fade-up grid grid-cols-[1.5rem_1fr] gap-x-2 gap-y-2 rounded-control border border-line p-2 sm:grid-cols-[1.5rem_1fr_8rem_6rem_7rem_1.5rem] sm:items-center"
                                >
                                    <span className="pt-2 text-xs text-ink-muted sm:pt-0">{index + 1}</span>
                                    <div>
                                        <input
                                            value={item.description}
                                            onChange={(e) => update(section.key, index, { description: e.target.value })}
                                            disabled={disabled}
                                            placeholder="Item description"
                                            className={`${inputClass} px-3`}
                                            aria-label={`${section.label} item ${index + 1}`}
                                        />
                                        {err('description') && <p className="text-xs text-danger">{err('description')}</p>}
                                    </div>
                                    <div className="col-start-2 grid grid-cols-[1fr_5.5rem] gap-2 sm:col-start-auto sm:contents">
                                        <label className="relative block">
                                            <span className="pointer-events-none absolute left-3 top-1/2 -translate-y-1/2 text-xs text-ink-muted">RM</span>
                                            <input
                                                value={priceText(key, item)}
                                                inputMode="decimal"
                                                placeholder="0.00"
                                                disabled={disabled}
                                                className={`${inputClass} pl-9 pr-2 text-right tabular-nums`}
                                                aria-label={`${section.label} price ${index + 1} in RM`}
                                                onChange={(e) => typePrice(key, section.key, index, e.target.value)}
                                                onBlur={() => leavePrice(key)}
                                                onFocus={(e) => e.target.select()}
                                            />
                                        </label>
                                        <label className="relative block">
                                            <span className="pointer-events-none absolute left-2.5 top-1/2 -translate-y-1/2 text-xs text-ink-muted">×</span>
                                            <input
                                                value={item.quantity}
                                                type="number"
                                                inputMode="numeric"
                                                min={1}
                                                step={1}
                                                disabled={disabled}
                                                className={`${inputClass} pl-6 pr-2 text-right tabular-nums`}
                                                aria-label={`${section.label} quantity ${index + 1}`}
                                                onKeyDown={blockKeys}
                                                onChange={(e) => typeQuantity(section.key, index, e.target.value)}
                                            />
                                        </label>
                                    </div>
                                    <span className="col-start-2 text-right text-sm font-semibold tabular-nums sm:col-start-auto">RM {rm(item.price_sen * item.quantity)}</span>
                                    {!disabled && (
                                        <button
                                            type="button"
                                            className="col-start-2 justify-self-end text-ink-muted hover:text-danger sm:col-start-auto"
                                            aria-label={`Remove ${section.label} item ${index + 1}`}
                                            onClick={() => remove(section.key, index)}
                                        >
                                            ✕
                                        </button>
                                    )}
                                    {(err('quantity') || err('price_sen')) && <p className="col-span-full text-xs text-danger">{err('quantity') ?? err('price_sen')}</p>}
                                </li>
                            );
                        })}
                    </ul>
                    {!disabled && (
                        <button
                            type="button"
                            className="justify-self-start rounded-control border border-dashed border-line px-3 py-2 text-sm font-medium text-brand-blue transition hover:border-brand-cyan"
                            onClick={() => add(section.key)}
                        >
                            ＋ Add {section.key === 'revenue' ? 'revenue' : 'expense'}
                        </button>
                    )}
                </div>
            ))}
            <div className={`flex items-center justify-between rounded-control px-4 py-3 text-sm font-semibold transition-colors ${profit < 0 ? 'bg-danger/10 text-danger' : 'bg-success/10 text-success'}`}>
                <span>{profit < 0 ? 'Loss' : profit === 0 ? 'Break even' : 'Profit'}</span>
                <span className="tabular-nums">RM {rm(profit)}</span>
            </div>
            {profit < 0 && (
                <p className="text-sm text-danger" role="alert">
                    Revenue must at least cover expenses (break even). You cannot submit until it does: add RM {rm(-profit)} of revenue or cut costs.
                </p>
            )}
            {errors?.budget && <p className="text-sm text-danger">{errors.budget}</p>}
        </div>
    );
}
