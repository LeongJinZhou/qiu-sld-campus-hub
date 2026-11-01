<script setup lang="ts">
import CreatePost from '@/Components/feed/CreatePost.vue';
import PostCard from '@/Components/feed/PostCard.vue';
import StoryBar from '@/Components/feed/StoryBar.vue';
import Button from '@/Components/ui/Button.vue';
import Icon from '@/Components/ui/Icon.vue';
import EmptyState from '@/Components/ui/EmptyState.vue';
import AppLayout from '@/Layouts/AppLayout.vue';
import type { PostCardData, StoryGroup } from '@/types';
import { Head, InfiniteScroll, Link, router } from '@inertiajs/vue3';
import { ref } from 'vue';

interface ArchiveGroup {
    key: string;
    label: string;
    count: number;
    thumbs: string[];
}

const props = defineProps<{
    stories: StoryGroup[];
    posts: { data: PostCardData[] };
    archive: ArchiveGroup[];
    group?: { key: string; posts: PostCardData[] };
    publishable: { slug: string; name: string }[];
    openCreate: boolean;
}>();
const creating = ref(props.openCreate && props.publishable.length > 0);

// Posts older than 7 days stay folded until opened; one group open at a time.
const open = ref<string | null>(null);
const loading = ref<string | null>(null);
function toggle(key: string): void {
    if (open.value === key) {
        open.value = null;
        return;
    }
    open.value = key;
    if (props.group?.key === key) return;
    loading.value = key;
    router.reload({
        only: ['group'],
        data: { group: key },
        preserveUrl: true,
        onFinish: () => (loading.value = null),
    });
}
</script>

<template>
    <Head title="Feed" />
    <AppLayout>
        <div class="mx-auto grid max-w-xl gap-5">
            <div v-if="stories.length" class="reveal rounded-card border border-line bg-surface p-4 shadow-card">
                <StoryBar :groups="stories" />
            </div>
            <button
                v-if="publishable.length"
                data-tour="feed-create"
                type="button"
                class="reveal flex items-center gap-3 rounded-card border border-line bg-surface p-4 text-left text-sm text-ink-muted shadow-card transition hover:-translate-y-0.5 hover:shadow-pop"
                @click="creating = true"
            >
                <span class="grid size-10 place-items-center rounded-full bg-gradient-to-br from-brand-orange to-brand-magenta text-white"><Icon name="plus" :size="20" /></span>
                Share a post, recap or story with your club…
            </button>
            <EmptyState v-if="posts.data.length === 0 && archive.length === 0" title="No posts yet" description="Follow clubs to see their events and recaps here.">
                <Link href="/clubs"><Button>Discover clubs</Button></Link>
            </EmptyState>
            <p v-else-if="posts.data.length === 0" class="rounded-card border border-dashed border-line p-5 text-center text-sm text-ink-muted">
                Nothing new this week. Earlier posts are below.
            </p>
            <InfiniteScroll data="posts" class="grid gap-5">
                <PostCard v-for="post in posts.data" :key="post.id" :post="post" class="reveal" />
            </InfiniteScroll>

            <section v-if="archive.length" class="grid gap-3" aria-label="Earlier posts">
                <h2 class="flex items-center gap-3 text-xs font-semibold uppercase tracking-wide text-ink-muted">
                    <span class="h-px flex-1 bg-line" />Earlier<span class="h-px flex-1 bg-line" />
                </h2>
                <div v-for="g in archive" :key="g.key" class="reveal overflow-hidden rounded-card border border-line bg-surface shadow-card">
                    <button
                        type="button"
                        class="flex w-full items-center gap-3 p-3 text-left transition hover:bg-surface-muted"
                        :aria-expanded="open === g.key"
                        @click="toggle(g.key)"
                    >
                        <span class="flex -space-x-3">
                            <img v-for="(t, i) in g.thumbs" :key="i" :src="t" alt="" class="size-10 rounded-lg border-2 border-surface object-cover" loading="lazy" />
                            <span v-if="!g.thumbs.length" class="grid size-10 place-items-center rounded-lg bg-surface-muted text-ink-muted"
                                ><Icon name="calendar" :size="18"
                            /></span>
                        </span>
                        <span class="flex-1">
                            <span class="block font-semibold">{{ g.label }}</span>
                            <span class="text-xs text-ink-muted">{{ g.count }} post{{ g.count === 1 ? '' : 's' }}</span>
                        </span>
                        <span v-if="loading === g.key" class="size-4 animate-spin rounded-full border-2 border-brand-cyan border-t-transparent" />
                        <Icon v-else name="chevron-down" :size="18" class="text-ink-muted transition-transform duration-300" :class="open === g.key ? 'rotate-180' : ''" />
                    </button>
                    <div class="grid transition-[grid-template-rows] duration-300 ease-out" :class="open === g.key && group?.key === g.key ? 'grid-rows-[1fr]' : 'grid-rows-[0fr]'">
                        <ul class="min-h-0 divide-y divide-line overflow-hidden">
                            <li v-for="p in group?.key === g.key ? group.posts : []" :key="p.id">
                                <Link :href="p.url" class="flex items-center gap-3 px-3 py-2.5 text-sm transition hover:bg-surface-muted">
                                    <img v-if="p.media[0]" :src="p.media[0]" alt="" class="size-12 shrink-0 rounded-lg object-cover" loading="lazy" />
                                    <span v-else class="grid size-12 shrink-0 place-items-center rounded-lg bg-gradient-to-br from-brand-yellow to-brand-magenta text-white"
                                        ><Icon name="calendar" :size="18"
                                    /></span>
                                    <span class="min-w-0 flex-1">
                                        <span class="block truncate font-medium">{{ p.event?.name ?? p.club?.name }}</span>
                                        <span class="block truncate text-xs text-ink-muted"
                                            >{{ p.club?.name }} · {{ p.published_at }}<template v-if="p.caption"> · {{ p.caption }}</template></span
                                        >
                                    </span>
                                    <span class="flex shrink-0 items-center gap-2 text-xs text-ink-muted">
                                        <span class="flex items-center gap-0.5"><Icon name="heart" :size="14" />{{ p.likes_count }}</span>
                                        <span class="flex items-center gap-0.5"><Icon name="message" :size="14" />{{ p.comments_count }}</span>
                                    </span>
                                </Link>
                            </li>
                        </ul>
                    </div>
                </div>
            </section>
        </div>
        <CreatePost v-if="publishable.length" v-model:open="creating" :clubs="publishable" />
    </AppLayout>
</template>
