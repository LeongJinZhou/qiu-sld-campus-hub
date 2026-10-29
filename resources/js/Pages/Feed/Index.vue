<script setup lang="ts">
import CreatePost from '@/Components/feed/CreatePost.vue';
import PostCard from '@/Components/feed/PostCard.vue';
import StoryBar from '@/Components/feed/StoryBar.vue';
import Button from '@/Components/ui/Button.vue';
import Icon from '@/Components/ui/Icon.vue';
import EmptyState from '@/Components/ui/EmptyState.vue';
import AppLayout from '@/Layouts/AppLayout.vue';
import type { PostCardData, StoryGroup } from '@/types';
import { Head, InfiniteScroll, Link } from '@inertiajs/vue3';
import { ref } from 'vue';

const props = defineProps<{ stories: StoryGroup[]; posts: { data: PostCardData[] }; publishable: { slug: string; name: string }[]; openCreate: boolean }>();
const creating = ref(props.openCreate && props.publishable.length > 0);
</script>

<template>
    <Head title="Feed" />
    <AppLayout>
        <div class="mx-auto grid max-w-xl gap-5">
            <div v-if="stories.length" class="rounded-card border border-line bg-surface p-4 shadow-card"><StoryBar :groups="stories" /></div>
            <button v-if="publishable.length" type="button" class="flex items-center gap-3 rounded-card border border-line bg-surface p-4 text-left text-sm text-ink-muted shadow-card transition hover:shadow-pop" @click="creating = true">
                <span class="grid size-10 place-items-center rounded-full bg-gradient-to-br from-brand-orange to-brand-magenta text-white"><Icon name="plus" :size="20" /></span>
                Share a post, recap or story with your club…
            </button>
            <EmptyState v-if="posts.data.length === 0" title="No posts yet" description="Follow clubs to see their events and recaps here.">
                <Link href="/clubs"><Button>Discover clubs</Button></Link>
            </EmptyState>
            <InfiniteScroll data="posts" class="grid gap-5">
                <PostCard v-for="post in posts.data" :key="post.id" :post="post" />
            </InfiniteScroll>
        </div>
        <CreatePost v-if="publishable.length" v-model:open="creating" :clubs="publishable" />
    </AppLayout>
</template>
