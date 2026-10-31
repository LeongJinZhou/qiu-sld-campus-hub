/**
 * Search-as-you-type helpers shared by every list filter. Each word must match; case and punctuation are
 * ignored, and dates (2026-11-01, 1/11/2026) and times (14:30) match the compact form in transaction IDs.
 */
const compact = (s: string): string => s.toUpperCase().replace(/[^A-Z0-9]/g, '');

const pad = (n: string = ''): string => n.padStart(2, '0');

function normaliseWord(word: string): string {
    let m = word.match(/^(\d{4})[-/.](\d{1,2})[-/.](\d{1,2})$/);
    if (m) return `${m[1]}${pad(m[2])}${pad(m[3])}`;
    m = word.match(/^(\d{1,2})[-/.](\d{1,2})[-/.](\d{4})$/);
    if (m) return `${m[3]}${pad(m[2])}${pad(m[1])}`;
    m = word.match(/^(\d{1,2})[:.](\d{2})(am|pm)?$/i);
    if (m) {
        const suffix = m[3]?.toLowerCase();
        const hour = suffix ? (Number(m[1]) % 12) + (suffix === 'pm' ? 12 : 0) : Number(m[1]);
        return `${pad(String(hour))}${m[2]}`;
    }
    return word;
}

export function searchTokens(query: string): string[] {
    return [...new Set(query.trim().split(/[\s_]+/).map(normaliseWord).map(compact).filter(Boolean))];
}

/** True when every word of the query appears in one of the fields. */
export function matchesSearch(query: string, ...fields: (string | number | null | undefined)[]): boolean {
    const tokens = searchTokens(query);
    if (!tokens.length) return true;
    const haystack = compact(fields.filter((f) => f !== null && f !== undefined).join(' '));
    return tokens.every((t) => haystack.includes(t));
}
