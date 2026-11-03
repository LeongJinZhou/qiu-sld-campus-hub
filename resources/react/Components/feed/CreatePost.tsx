import Button from '@react/Components/ui/Button';
import FileUpload from '@react/Components/ui/FileUpload';
import Modal from '@react/Components/ui/Modal';
import Select from '@react/Components/ui/Select';
import Textarea from '@react/Components/ui/Textarea';
import { useForm } from '@inertiajs/react';
import { useState, type FormEvent } from 'react';

interface CreatePostProps {
    clubs: { slug: string; name: string }[];
    events?: { id: number; name: string }[];
    open: boolean;
    onClose: () => void;
}

/** A club post (photos + caption, optionally an event recap) or a 24-hour story. */
export default function CreatePost({ clubs, events, open, onClose }: CreatePostProps) {
    const [mode, setMode] = useState<'post' | 'story'>('post');
    const [club, setClub] = useState(clubs[0]?.slug ?? '');
    const post = useForm({ type: 'general', caption: '', event_id: null as number | null, photos: [] as File[] });
    const story = useForm({ photo: [] as File[] });

    function submit(e: FormEvent): void {
        e.preventDefault();
        const done = {
            preserveScroll: true,
            onSuccess: () => {
                onClose();
                post.reset();
                story.reset();
            },
        };
        if (mode === 'story') {
            story.transform((d) => ({ photo: d.photo[0] ?? null }));
            story.post(`/clubs/${club}/stories`, done);
        } else {
            post.post(`/clubs/${club}/posts`, done);
        }
    }

    return (
        <Modal open={open} onClose={onClose} title="Create">
            <form className="grid gap-4" onSubmit={submit}>
                <div className="grid grid-cols-2 gap-1 rounded-control bg-surface-muted p-1 text-sm">
                    {(['post', 'story'] as const).map((m) => (
                        <button key={m} type="button" className={`rounded-control py-1.5 capitalize ${mode === m ? 'bg-surface font-semibold shadow-card' : ''}`} onClick={() => setMode(m)}>
                            {m}
                        </button>
                    ))}
                </div>
                {clubs.length > 1 && <Select value={club} onChange={setClub} label="Post as" options={clubs.map((c) => ({ value: c.slug, label: c.name }))} />}
                {mode === 'post' ? (
                    <>
                        {!!events?.length && (
                            <Select value={post.data.type} onChange={(v) => post.setData('type', v)} label="Type" options={[{ value: 'general', label: 'Post' }, { value: 'recap', label: 'Event recap' }]} />
                        )}
                        {post.data.type === 'recap' && !!events?.length && (
                            <Select
                                value={post.data.event_id}
                                onChange={(v) => post.setData('event_id', v)}
                                label="Event"
                                options={events.map((ev) => ({ value: ev.id, label: ev.name }))}
                                placeholder="Choose event"
                                error={post.errors.event_id}
                            />
                        )}
                        <FileUpload
                            files={post.data.photos}
                            onChange={(f) => post.setData('photos', f)}
                            label="Photos (up to 10)"
                            accept="image/jpeg,image/png,image/webp"
                            multiple
                            error={post.errors.photos ?? (post.errors as Record<string, string>)['photos.0']}
                        />
                        <Textarea value={post.data.caption} onChange={(v) => post.setData('caption', v)} label="Caption" rows={3} error={post.errors.caption} />
                    </>
                ) : (
                    <FileUpload files={story.data.photo} onChange={(f) => story.setData('photo', f)} label="Story photo (visible 24 hours)" accept="image/jpeg,image/png,image/webp" error={story.errors.photo} />
                )}
                <p className="text-xs text-ink-muted">Real QIU photos only — no AI-generated people.</p>
                <Button type="submit" loading={post.processing || story.processing}>
                    Share
                </Button>
            </form>
        </Modal>
    );
}
