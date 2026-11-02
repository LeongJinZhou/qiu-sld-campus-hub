import { Link } from '@inertiajs/react';

/** Laravel paginator links; labels are its own HTML entities (« Previous, Next »). */
export default function Pagination({ links }: { links: { url: string | null; label: string; active: boolean }[] }) {
    if (links.length <= 3) return null;
    return (
        <nav className="flex flex-wrap gap-1" aria-label="Pagination">
            {links.map((link) =>
                link.url ? (
                    <Link key={link.label} href={link.url} preserveScroll className={`rounded-control px-3 py-1.5 text-sm ${link.active ? 'bg-ink text-white' : 'hover:bg-surface-muted'}`}>
                        <span dangerouslySetInnerHTML={{ __html: link.label }} />
                    </Link>
                ) : (
                    <span key={link.label} className="px-3 py-1.5 text-sm text-ink-muted" dangerouslySetInnerHTML={{ __html: link.label }} />
                ),
            )}
        </nav>
    );
}
