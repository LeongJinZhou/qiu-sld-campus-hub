import Avatar from '@/Components/ui/Avatar';
import { useEffect, useState, type ReactNode } from 'react';

export interface StoryViewerGroup {
    name: string;
    logo_url: string | null;
    stories: { id: number; url: string; at: string | null }[];
}

/** Full-screen stories: each shows 5 seconds, tap left/right to move. `children` renders extra controls for the current story. */
export default function StoryViewer({ group, onClose, children }: { group: StoryViewerGroup | null; onClose: () => void; children?: (storyId: number | undefined) => ReactNode }) {
    const [index, setIndex] = useState(0);
    useEffect(() => setIndex(0), [group]);

    const next = (): void => {
        if (!group) return;
        if (index < group.stories.length - 1) setIndex(index + 1);
        else onClose();
    };
    useEffect(() => {
        if (!group) return;
        const timer = setTimeout(next, 5000);
        return () => clearTimeout(timer);
    });

    if (!group) return null;
    const story = group.stories[index];
    return (
        <div className="fixed inset-0 z-50 grid place-items-center bg-ink/95" role="dialog" aria-modal="true" aria-label={`${group.name} story`}>
            <div className="relative aspect-[9/16] h-[min(90dvh,48rem)] max-w-full overflow-hidden rounded-card bg-ink">
                <div className="absolute inset-x-3 top-3 z-10 flex gap-1">
                    {group.stories.map((s, i) => (
                        <span key={s.id} className="h-0.5 flex-1 rounded bg-white/40">
                            <span className={`block h-full rounded bg-white ${i <= index ? 'w-full' : 'w-0'}`} />
                        </span>
                    ))}
                </div>
                <div className="absolute inset-x-3 top-6 z-10 flex items-center gap-2 text-sm font-semibold text-white">
                    <Avatar src={group.logo_url} name={group.name} size="xs" />
                    {group.name}
                    <span className="font-normal text-white/70">{story?.at}</span>
                    <button type="button" className="ml-auto text-xl" aria-label="Close" onClick={onClose}>
                        ✕
                    </button>
                </div>
                <img src={story?.url} alt="" className="size-full object-contain" />
                <button type="button" className="absolute inset-y-0 left-0 w-1/3" aria-label="Previous" onClick={() => setIndex(Math.max(0, index - 1))} />
                <button type="button" className="absolute inset-y-0 right-0 w-2/3" aria-label="Next" onClick={next} />
                {children?.(story?.id)}
            </div>
        </div>
    );
}
