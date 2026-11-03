/** Calendar maths, kept free of React so it can be tested on its own (in any device time zone). */
export const TZ = 'Asia/Kuala_Lumpur';

export type Filter = 'all' | 'followed' | 'mine';
interface Span {
    starts_at: string;
    ends_at: string;
}
export interface FilterableEvent extends Span {
    registered?: boolean;
    followed?: boolean;
}

/** The campus day (Kuala Lumpur) an instant falls on, as YYYY-MM-DD. */
export const dayKey = (iso: string): string => new Date(iso).toLocaleDateString('en-CA', { timeZone: TZ });

/** The last day an item covers. The end is exclusive: an event ending at 00:00 does not appear on the next day. */
export const lastDayKey = (startIso: string, endIso: string): string => {
    const end = new Date(endIso).getTime();
    return dayKey(new Date(Math.max(new Date(startIso).getTime(), end - 1)).toISOString());
};

export const covers = (item: Span, key: string): boolean => dayKey(item.starts_at) <= key && lastDayKey(item.starts_at, item.ends_at) >= key;

/** Which events: everything, clubs I follow, or events I registered for. */
export const applyFilter = <E extends FilterableEvent>(events: E[], filter: Filter): E[] => events.filter((e) => filter === 'all' || (filter === 'mine' ? e.registered : e.followed));

const pad = (n: number): string => String(n).padStart(2, '0');

/** Monday-first grid covering the whole month (5 or 6 weeks). Items (meetings, reports…) are hidden on "Clubs I follow". */
export function monthDays<E extends FilterableEvent, I extends Span>(month: string, events: E[], items: I[], filter: Filter): { key: string; day: number; inMonth: boolean; events: E[]; items: I[] }[] {
    const [year, mon] = month.split('-').map(Number) as [number, number];
    const shown = applyFilter(events, filter);
    const first = new Date(year, mon - 1, 1);
    const start = new Date(first);
    start.setDate(1 - ((first.getDay() + 6) % 7));
    return Array.from({ length: 42 }, (_, i) => {
        const d = new Date(start);
        d.setDate(start.getDate() + i);
        const key = `${d.getFullYear()}-${pad(d.getMonth() + 1)}-${pad(d.getDate())}`;
        return {
            key,
            day: d.getDate(),
            inMonth: d.getMonth() === mon - 1,
            events: shown.filter((e) => covers(e, key)),
            items: filter === 'followed' ? [] : items.filter((it) => covers(it, key)),
        };
    }).filter((d, i, all) => i < 35 || all.slice(35).some((x) => x.inMonth));
}

/** Year view: 12 small months; each day shows how many things are on. */
export function yearMonths(year: number, events: FilterableEvent[], items: Span[], filter: Filter): { key: string; label: string; cells: ({ key: string; day: number; n: number } | null)[] }[] {
    const shown = applyFilter(events, filter);
    return Array.from({ length: 12 }, (_, m) => {
        const first = new Date(year, m, 1);
        const lead = (first.getDay() + 6) % 7;
        const count = new Date(year, m + 1, 0).getDate();
        const cells = Array.from({ length: lead + count }, (_, i) => {
            if (i < lead) return null;
            const key = `${year}-${pad(m + 1)}-${pad(i - lead + 1)}`;
            return { key, day: i - lead + 1, n: shown.filter((e) => covers(e, key)).length + items.filter((it) => covers(it, key)).length };
        });
        return { key: `${year}-${pad(m + 1)}`, label: first.toLocaleDateString('en-MY', { month: 'long' }), cells };
    });
}
