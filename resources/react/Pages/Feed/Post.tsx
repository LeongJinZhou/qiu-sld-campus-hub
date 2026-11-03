import PostCard from '@react/Components/feed/PostCard';
import Avatar from '@react/Components/ui/Avatar';
import Modal from '@react/Components/ui/Modal';
import AppLayout from '@react/Layouts/AppLayout';
import type { CommentData, PostCardData } from '@/types';
import { Head, Link, router } from '@inertiajs/react';
import { useState } from 'react';

interface Liker {
    name: string;
    photo_url: string | null;
    url: string;
}

export default function Post({ post, comments, likers }: { post: PostCardData; comments: CommentData[]; likers: Liker[] }) {
    const [showLikers, setShowLikers] = useState(false);
    // SLD and the club's committee can delete comments.
    const removeComment = (id: number): void => {
        if (confirm('Delete this comment for everyone?')) router.delete(`/comments/${id}`, { preserveScroll: true });
    };
    const [first, second] = likers;
    const rest = post.likes_count - (second ? 2 : 1);
    const likedBy = first ? `${first.name}${second ? `${rest > 0 ? ', ' : ' and '}${second.name}` : ''}${rest > 0 ? ` and ${rest} other${rest === 1 ? '' : 's'}` : ''}` : '';

    return (
        <AppLayout>
            <Head title={post.event?.name ?? post.club?.name ?? 'Post'} />
            <div className="mx-auto grid max-w-xl gap-4">
                <PostCard post={post} full />
                {likers.length > 0 && (
                    <button type="button" className="reveal flex items-center gap-2 text-left text-sm" onClick={() => setShowLikers(true)}>
                        <span className="flex -space-x-2">
                            {likers.slice(0, 3).map((l) => (
                                <Avatar key={l.url} src={l.photo_url} name={l.name} size="xs" className="ring-2 ring-surface" />
                            ))}
                        </span>
                        <span>
                            Liked by <strong>{likedBy}</strong>
                        </span>
                    </button>
                )}
                <section className="grid gap-3">
                    <h2 className="text-sm font-semibold text-ink-muted">
                        {comments.length} comment{comments.length === 1 ? '' : 's'}
                    </h2>
                    <ul className="grid gap-3">
                        {comments.map((c) => (
                            <li key={c.id} className="fade-up flex gap-3 text-sm">
                                <Link href={c.profile_url}>
                                    <Avatar src={c.photo_url} name={c.user} size="xs" />
                                </Link>
                                <p>
                                    <Link href={c.profile_url} className="font-semibold hover:underline">
                                        {c.user}
                                    </Link>{' '}
                                    {c.body}
                                    <span className="block text-xs text-ink-muted">{c.at}</span>
                                </p>
                                {post.can_moderate && (
                                    <button type="button" className="ml-auto self-start text-xs text-ink-muted hover:text-danger" aria-label={`Delete comment by ${c.user}`} onClick={() => removeComment(c.id)}>
                                        Delete
                                    </button>
                                )}
                            </li>
                        ))}
                    </ul>
                </section>
            </div>
            <Modal open={showLikers} onClose={() => setShowLikers(false)} title="Likes">
                <ul className="grid max-h-[60vh] gap-3 overflow-y-auto">
                    {likers.map((l) => (
                        <li key={l.url}>
                            <Link href={l.url} className="flex items-center gap-3 text-sm font-medium hover:underline">
                                <Avatar src={l.photo_url} name={l.name} size="sm" />
                                {l.name}
                            </Link>
                        </li>
                    ))}
                </ul>
            </Modal>
        </AppLayout>
    );
}
