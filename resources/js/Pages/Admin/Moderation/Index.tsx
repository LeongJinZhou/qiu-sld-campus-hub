import Badge from '@/Components/ui/Badge';
import Button from '@/Components/ui/Button';
import Card from '@/Components/ui/Card';
import EmptyState from '@/Components/ui/EmptyState';
import PortalLayout from '@/Layouts/PortalLayout';
import { Head, router } from '@inertiajs/react';

interface Report {
    id: number;
    reason: string;
    reporter: string;
    at: string | null;
    post: { id: number; caption: string | null; club: string | null; hidden: boolean };
    comment: { id: number; body: string; hidden: boolean } | null;
}
interface RecentPost {
    id: number;
    caption: string | null;
    club: string | null;
    type: string;
    pinned: boolean;
    hidden: boolean;
}
const act = (url: string): void => router.post(url, {}, { preserveScroll: true });

export default function Index({ reports, recent }: { reports: Report[]; recent: RecentPost[] }) {
    return (
        <PortalLayout title="Feed moderation">
            <Head title="Moderation" />
            <div className="grid gap-6">
                <Card title="Open reports">
                    {reports.length === 0 && <EmptyState title="No open reports" />}
                    <ul className="grid gap-3">
                        {reports.map((r) => (
                            <li key={r.id} className="grid gap-2 rounded-control border border-line p-3 text-sm">
                                <p>
                                    <strong>{r.reporter}</strong> reported {r.comment ? 'a comment' : 'a post'} · {r.at}: “{r.reason}”
                                </p>
                                <p className="text-ink-muted">
                                    {r.post.club} · {r.comment?.body ?? r.post.caption ?? '(no caption)'}
                                </p>
                                <div className="flex flex-wrap gap-2">
                                    {r.comment ? (
                                        <Button size="sm" variant="secondary" onClick={() => act(`/admin/moderation/comments/${r.comment!.id}/hide`)}>
                                            {r.comment.hidden ? 'Restore comment' : 'Hide comment'}
                                        </Button>
                                    ) : (
                                        <Button size="sm" variant="secondary" onClick={() => act(`/admin/moderation/posts/${r.post.id}/hide`)}>
                                            {r.post.hidden ? 'Restore post' : 'Hide post'}
                                        </Button>
                                    )}
                                    <Button size="sm" variant="ghost" onClick={() => act(`/admin/moderation/reports/${r.id}/resolve`)}>
                                        Mark resolved
                                    </Button>
                                </div>
                            </li>
                        ))}
                    </ul>
                </Card>
                <Card title="Recent posts">
                    <ul className="grid gap-2">
                        {recent.map((p) => (
                            <li key={p.id} className="flex flex-wrap items-center justify-between gap-2 border-b border-line pb-2 text-sm">
                                <span>
                                    <strong>{p.club}</strong> · {p.type} · {p.caption?.slice(0, 80) ?? ''}
                                </span>
                                <span className="flex flex-wrap gap-2">
                                    {p.hidden && <Badge tone="danger">Hidden</Badge>}
                                    <Button size="sm" variant="secondary" onClick={() => act(`/admin/moderation/posts/${p.id}/pin`)}>
                                        {p.pinned ? 'Unpin' : 'Pin'}
                                    </Button>
                                    <Button size="sm" variant="ghost" onClick={() => act(`/admin/moderation/posts/${p.id}/hide`)}>
                                        {p.hidden ? 'Restore' : 'Hide'}
                                    </Button>
                                </span>
                            </li>
                        ))}
                    </ul>
                </Card>
            </div>
        </PortalLayout>
    );
}
