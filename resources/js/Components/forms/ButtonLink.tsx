import { buttonClass } from '@/Components/ui/Button';
import { url } from '@/lib/url';
import { Link } from '@inertiajs/react';
import type { ReactNode } from 'react';

interface ButtonLinkProps {
    /** An app path ('/events/x') or a full URL; the mount folder is added to app paths here. */
    href: string;
    variant?: Parameters<typeof buttonClass>[0];
    size?: Parameters<typeof buttonClass>[1];
    block?: boolean;
    /** A download, another site or a non-Inertia page: a plain anchor instead of an Inertia link. */
    plain?: boolean;
    target?: string;
    rel?: string;
    className?: string;
    /** The guided-tour anchor (data-tour). */
    tour?: string;
    children: ReactNode;
}

/** A link that looks like Button: one interactive element, not a button inside an anchor. */
export default function ButtonLink({ href, variant = 'secondary', size = 'md', block = false, plain = false, target, rel, className, tour, children }: ButtonLinkProps) {
    const classes = buttonClass(variant, size, block, className);
    return plain ? (
        <a href={url(href)} className={classes} target={target} rel={rel} data-tour={tour}>
            {children}
        </a>
    ) : (
        <Link href={url(href)} className={classes} data-tour={tour}>
            {children}
        </Link>
    );
}
