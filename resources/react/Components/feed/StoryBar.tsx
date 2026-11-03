import Avatar from '@react/Components/ui/Avatar';
import type { StoryGroup } from '@/types';
import { useState } from 'react';
import StoryViewer from './StoryViewer';

export default function StoryBar({ groups }: { groups: StoryGroup[] }) {
    const [open, setOpen] = useState<StoryGroup | null>(null);
    if (!groups.length) return null;
    return (
        <>
            <div className="flex gap-4 overflow-x-auto pb-1 [scrollbar-width:none]">
                {groups.map((group) => (
                    <button key={group.slug} type="button" className="grid w-16 shrink-0 justify-items-center gap-1" onClick={() => setOpen(group)}>
                        <Avatar src={group.logo_url} name={group.name} size="md" ring />
                        <span className="w-full truncate text-center text-[11px]">{group.name}</span>
                    </button>
                ))}
            </div>
            <StoryViewer group={open} onClose={() => setOpen(null)} />
        </>
    );
}
