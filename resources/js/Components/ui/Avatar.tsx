import { useEffect, useMemo, useState } from 'react';

const sizes = { xs: 'size-6 text-[10px]', sm: 'size-9 text-xs', md: 'size-14 text-base', lg: 'size-20 text-xl', xl: 'size-28 text-3xl' };

/** Initials from the first two words; an email falls back to the part before "@" ("jinzhou.leong" -> "JL"). */
export function initials(name: string): string {
    const words = (name.split('@')[0] ?? '').split(/[\s._-]+/).filter((w) => /^[\p{L}\p{N}]/u.test(w));
    const capitals = words.filter((w) => /^\p{Lu}/u.test(w));
    return (capitals.length ? capitals : words).slice(0, 2).map((w) => (w[0] ?? '').toUpperCase()).join('') || '?';
}

interface AvatarProps {
    src?: string | null;
    name: string;
    size?: keyof typeof sizes;
    ring?: boolean;
    className?: string;
}

export default function Avatar({ src = null, name, size = 'md', ring = false, className = '' }: AvatarProps) {
    // A photo that fails to load (expired Google link, deleted file) falls back to initials.
    const [broken, setBroken] = useState(false);
    useEffect(() => setBroken(false), [src]);
    const letters = useMemo(() => initials(name), [name]);

    return (
        <span className={`inline-grid shrink-0 place-items-center rounded-full ${ring ? 'ring-story p-[3px]' : ''} ${className}`.trim()}>
            <span className={`grid place-items-center overflow-hidden rounded-full bg-surface ${sizes[size]} ${ring ? 'border-2 border-surface' : ''}`}>
                {src && !broken ? (
                    <img src={src} alt={name} className="size-full object-cover" referrerPolicy="no-referrer" onError={() => setBroken(true)} />
                ) : (
                    <span className="grid size-full place-items-center bg-gradient-to-br from-accent to-primary-strong font-semibold text-white" aria-hidden="true">
                        {letters}
                    </span>
                )}
            </span>
        </span>
    );
}
