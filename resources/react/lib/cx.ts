/** Joins class names, skipping empty and false values. */
export function cx(...parts: (string | false | null | undefined)[]): string {
    return parts.filter(Boolean).join(' ');
}
