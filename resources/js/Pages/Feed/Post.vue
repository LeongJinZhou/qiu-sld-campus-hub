<script setup lang="ts">
import PostCard from '@/Components/feed/PostCard.vue';
import Avatar from '@/Components/ui/Avatar.vue';
import AppLayout from '@/Layouts/AppLayout.vue';
import type { CommentData, PostCardData } from '@/types';
import { Head } from '@inertiajs/vue3';

defineProps<{ post: PostCardData; comments: CommentData[] }>();
</script>

<template>
    <Head :title="post.event?.name ?? post.club?.name ?? 'Post'" />
    <AppLayout>
        <div class="mx-auto grid max-w-xl gap-4">
            <PostCard :post="post" full />
            <ul class="grid gap-3">
                <li v-for="c in comments" :key="c.id" class="flex gap-3 text-sm">
                    <Avatar :src="c.photo_url" :name="c.user" size="xs" />
                    <p><strong>{{ c.user }}</strong> {{ c.body }} <span class="text-xs text-ink-muted">{{ c.at }}</span></p>
                </li>
            </ul>
        </div>
    </AppLayout>
</template>
