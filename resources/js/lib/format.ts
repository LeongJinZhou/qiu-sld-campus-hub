/** Money is stored in sen and shown as RM with 2 decimals. */
export const rm = (sen: number): string =>
    `RM ${(sen / 100).toLocaleString('en-MY', { minimumFractionDigits: 2, maximumFractionDigits: 2 })}`;

// One date style everywhere, matching the server: "Thu, 29 Oct 2026, 3:50 PM" and "29 Oct 2026".
const parts = (iso: string): Record<string, string> =>
    Object.fromEntries(
        new Intl.DateTimeFormat('en-GB', { weekday: 'short', day: 'numeric', month: 'short', year: 'numeric', hour: 'numeric', minute: '2-digit', hour12: true, timeZone: 'Asia/Kuala_Lumpur' })
            .formatToParts(new Date(iso))
            .map((p) => [p.type, p.value]),
    );

export const date = (iso: string): string => {
    const p = parts(iso);
    return `${p.day} ${p.month} ${p.year}`;
};

export const dateTime = (iso: string): string => {
    const p = parts(iso);
    return `${p.weekday}, ${p.day} ${p.month} ${p.year}, ${p.hour}:${p.minute} ${(p.dayPeriod ?? '').toUpperCase()}`;
};

export const hoursBetween = (start: string, end: string): number => {
    const ms = new Date(end).getTime() - new Date(start).getTime();
    return Number.isFinite(ms) && ms > 0 ? Math.round((ms / 3_600_000) * 10) / 10 : 0;
};
