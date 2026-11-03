/** A nav item is active on its own page and anything under it, never on a page that merely starts with the same letters. */
export const navMatches = (path: string, match: string): boolean => path === match || path.startsWith(`${match}/`);
