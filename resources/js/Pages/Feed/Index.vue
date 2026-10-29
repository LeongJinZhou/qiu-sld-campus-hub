<script setup lang="ts">
import CreatePost from '@/Components/feed/CreatePost.vue';
import PostCard from '@/Components/feed/PostCard.vue';
import StoryBar from '@/Components/feed/StoryBar.vue';
import Button from '@/Components/ui/Button.vue';
import EmptyState from '@/Components/ui/EmptyState.vue';
import AppLayout from '@/Layouts/AppLayout.vue';
import type { PostCardData, StoryGroup } from '@/types';
import { Head, InfiniteScroll, Link } from '@inertiajs/vue3';
import { ref } from 'vue';

defineProps<{ stories: StoryGroup[]; posts: { data: PostCardData[] }; publishable: { slug: string; name: string }[] }>();
const creating = ref(false);
</script>

<template>
    <Head title="Feed" />
    <AppLayout>
        <div class="mx-auto grid max-w-xl gap-5">
            <StoryBar :groups="stories" />
            <Button v-if="publishable.length" variant="secondary" block @click="creating = true">＋ Share a post or story</Button>
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
