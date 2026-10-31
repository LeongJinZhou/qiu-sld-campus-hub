/**
 * Deep copy of page props for an editable form. structuredClone() throws on Vue's reactive proxies (Inertia props
 * are reactive), so copy through JSON; props are plain JSON from the server anyway.
 */
export const copy = <T>(value: T): T => JSON.parse(JSON.stringify(value)) as T;
