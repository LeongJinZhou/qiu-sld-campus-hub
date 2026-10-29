/** Money is stored in sen and shown as RM with 2 decimals. */
export const rm = (sen: number): string =>
    `RM ${(sen / 100).toLocaleString('en-MY', { minimumFractionDigits: 2, maximumFractionDigits: 2 })}`;

export const dateTime = (iso: string): string =>
    new Date(iso).toLocaleString('en-MY', { weekday: 'short', day: 'numeric', month: 'short', year: 'numeric', hour: 'numeric', minute: '2-digit' });

export const hoursBetween = (start: string, end: string): number => {
    const ms = new Date(end).getTime() - new Date(start).getTime();
    return Number.isFinite(ms) && ms > 0 ? Math.round((ms / 3_600_000) * 10) / 10 : 0;
};
