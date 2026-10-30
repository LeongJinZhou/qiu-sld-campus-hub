<script setup lang="ts">
import Badge from '@/Components/ui/Badge.vue';
import Button from '@/Components/ui/Button.vue';
import Card from '@/Components/ui/Card.vue';
import EmptyState from '@/Components/ui/EmptyState.vue';
import PortalLayout from '@/Layouts/PortalLayout.vue';
import { Head, router } from '@inertiajs/vue3';

defineProps<{
    reports: { id: number; reason: string; reporter: string; at: string | null; post: { id: number; caption: string | null; club: string | null; hidden: boolean }; comment: { id: number; body: string; hidden: boolean } | null }[];
    recent: { id: number; caption: string | null; club: string | null; type: string; pinned: boolean; hidden: boolean }[];
}>();

const act = (url: string): void => router.post(url, {}, { preserveScroll: true });
</script>

<template>
    <Head title="Moderation" />
    <PortalLayout title="Feed moderation">
        <div class="grid gap-6">
            <Card>
                <template #title>Open reports</template>
                <EmptyState v-if="reports.length === 0" title="No open reports" />
                <ul class="grid gap-3">
                    <li v-for="r in reports" :key="r.id" class="grid gap-2 rounded-control border border-line p-3 text-sm">
                        <p><strong>{{ r.reporter }}</strong> reported {{ r.comment ? 'a comment' : 'a post' }} · {{ r.at }}: “{{ r.reason }}”</p>
                        <p class="text-ink-muted">{{ r.post.club }} · {{ r.comment?.body ?? r.post.caption ?? '(no caption)' }}</p>
                        <div class="flex flex-wrap gap-2">
                            <Button v-if="r.comment" size="sm" variant="secondary" @click="act(`/admin/moderation/comments/${r.comment.id}/hide`)">{{ r.comment.hidden ? 'Restore comment' : 'Hide comment' }}</Button>
                            <Button v-else size="sm" variant="secondary" @click="act(`/admin/moderation/posts/${r.post.id}/hide`)">{{ r.post.hidden ? 'Restore post' : 'Hide post' }}</Button>
                            <Button size="sm" variant="ghost" @click="act(`/admin/moderation/reports/${r.id}/resolve`)">Mark resolved</Button>
                        </div>
                    </li>
                </ul>
            </Card>
            <Card>
                <template #title>Recent posts</template>
                <ul class="grid gap-2">
                    <li v-for="p in recent" :key="p.id" class="flex flex-wrap items-center justify-between gap-2 border-b border-line pb-2 text-sm">
                        <span><strong>{{ p.club }}</strong> · {{ p.type }} · {{ p.caption?.slice(0, 80) ?? '' }}</span>
                        <span class="flex flex-wrap gap-2">
                            <Badge v-if="p.hidden" tone="danger">Hidden</Badge>
                            <Button size="sm" variant="secondary" @click="act(`/admin/moderation/posts/${p.id}/pin`)">{{ p.pinned ? 'Unpin' : 'Pin' }}</Button>
                            <Button size="sm" variant="ghost" @click="act(`/admin/moderation/posts/${p.id}/hide`)">{{ p.hidden ? 'Restore' : 'Hide' }}</Button>
                        </span>
                    </li>
                </ul>
            </Card>
        </div>
    </PortalLayout>
</template>
