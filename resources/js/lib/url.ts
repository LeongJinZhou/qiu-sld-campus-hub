// Campus Hub runs at a domain root (Render) or in a subfolder (Plesk: /campus-hub). Every in-app path goes
// through url() so a link, form or download works in both places. The base comes from app.blade.php.

/** Joins an app path ('/feed') to a base ('/campus-hub'). Full URLs, relative paths and already-based paths pass through. */
export function withBase(path: string, base: string): string {
    const root = base.replace(/\/+$/, '');
    if (!root || !path.startsWith('/') || path.startsWith('//')) return path;
    if (path === root || path.startsWith(`${root}/`) || path.startsWith(`${root}?`) || path.startsWith(`${root}#`)) return path;
    return `${root}${path}`;
}

/** Takes the base off a path, so '/campus-hub/clubs/x' reads as '/clubs/x'. Other paths pass through. */
export function withoutBase(path: string, base: string): string {
    const root = base.replace(/\/+$/, '');
    if (!root) return path;
    if (path !== root && !path.startsWith(`${root}/`) && !path.startsWith(`${root}?`) && !path.startsWith(`${root}#`)) return path;
    const rest = path.slice(root.length);
    return rest.startsWith('/') ? rest : `/${rest}`;
}

let cachedBase: string | null = null;

/** Where the app is mounted: '' at a domain root, '/campus-hub' in a subfolder. */
export function basePath(): string {
    if (cachedBase === null) {
        const meta = typeof document === 'undefined' ? null : document.querySelector<HTMLMetaElement>('meta[name="app-base"]');
        cachedBase = (meta?.content ?? '').replace(/\/+$/, '');
    }
    return cachedBase;
}

/** An app path as a link that works wherever the app is mounted: url('/feed') is '/campus-hub/feed' on Plesk. */
export const url = (path: string): string => withBase(path, basePath());

/** The current page path without the mount folder, for comparing with app paths. */
export const appPath = (path: string): string => withoutBase(path, basePath());
