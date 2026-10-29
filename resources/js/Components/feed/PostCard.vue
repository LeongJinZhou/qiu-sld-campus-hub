<script setup lang="ts">
import Avatar from '@/Components/ui/Avatar.vue';
import Badge from '@/Components/ui/Badge.vue';
import Icon from '@/Components/ui/Icon.vue';
import { dateTime } from '@/lib/format';
import type { PostCardData } from '@/types';
import { Link, router, useForm } from '@inertiajs/vue3';
import { ref } from 'vue';
import Carousel from './Carousel.vue';

const props = defineProps<{ post: PostCardData; full?: boolean }>();

// Optimistic like/save so the heart responds instantly; the server is the source of truth on reload.
const liked = ref(props.post.liked);
const likes = ref(props.post.likes_count);
const saved = ref(props.post.saved);
const options = { preserveScroll: true, preserveState: true, only: [] as string[] };

function like(): void {
    liked.value = !liked.value;
    likes.value += liked.value ? 1 : -1;
    router.post(`/posts/${props.post.id}/like`, {}, options);
}
function save(): void {
    saved.value = !saved.value;
    router.post(`/posts/${props.post.id}/save`, {}, options);
}
async function share(): Promise<void> {
    const data = { title: props.post.event?.name ?? props.post.club?.name ?? 'QIU Campus Hub', url: props.post.url };
    if (navigator.share) await navigator.share(data).catch(() => undefined);
    else await navigator.clipboard.writeText(data.url);
}
function report(): void {
    const reason = prompt('Why are you reporting this post?');
    if (reason) router.post(`/posts/${props.post.id}/report`, { reason }, options);
}
const comment = useForm({ body: '' });
const sendComment = (): void => comment.post(`/posts/${props.post.id}/comments`, { preserveScroll: true, onSuccess: () => comment.reset() });
const register = (): void => router.post(`/events/${props.post.event?.slug}/register`, {}, { preserveScroll: true });
</script>

<template>
    <article class="overflow-hidden rounded-card border border-line bg-surface shadow-card">
        <header class="flex items-center gap-3 px-4 py-3">
            <Link v-if="post.club" :href="`/clubs/${post.club.slug}`" class="flex items-center gap-3">
                <Avatar :src="post.club.logo_url" :name="post.club.name" size="sm" ring />
                <span class="text-sm font-semibold">{{ post.club.name }}</span>
            </Link>
            <Badge v-if="post.pinned" tone="info">Pinned by SLD</Badge>
            <span class="ml-auto text-xs text-ink-muted">{{ post.published_at }}</span>
            <button type="button" class="text-ink-muted hover:text-ink" aria-label="Report post" @click="report"><Icon name="more" :size="20" :stroke-width="3" /></button>
        </header>

        <Carousel v-if="post.media.length" :images="post.media" :alt="post.event?.name ?? 'Post photo'" />
        <div v-else-if="post.event" class="grid aspect-[4/3] place-items-center bg-gradient-to-br from-brand-yellow via-brand-orange to-brand-magenta p-8 text-center text-2xl font-bold text-white">
            {{ post.event.name }}
        </div>

        <div class="grid gap-3 px-4 py-3">
            <div class="flex items-center gap-4">
                <button type="button" :aria-pressed="liked" aria-label="Like" class="transition active:scale-125" :class="liked ? 'text-danger' : 'hover:text-ink-muted'" @click="like">
                    <Icon name="heart" :filled="liked" :size="26" />
                </button>
                <Link :href="post.url" aria-label="Comments" class="hover:text-ink-muted"><Icon name="message" :size="26" /></Link>
                <button type="button" aria-label="Share" class="hover:text-ink-muted" @click="share"><Icon name="send" :size="24" /></button>
                <button type="button" class="ml-auto hover:text-ink-muted" :aria-pressed="saved" aria-label="Save" @click="save"><Icon name="bookmark" :filled="saved" :size="26" /></button>
            </div>
            <p class="text-sm font-semibold">{{ likes }} like{{ likes === 1 ? '' : 's' }}</p>

            <div v-if="post.event" class="grid gap-2 rounded-card border border-line bg-gradient-to-br from-brand-yellow/10 to-brand-magenta/10 p-4 text-sm">
                <Link :href="`/events/${post.event.slug}`" class="text-base font-bold">{{ post.event.name }}</Link>
                <p class="flex items-center gap-2 text-ink-muted"><Icon name="calendar" :size="16" />{{ dateTime(post.event.starts_at) }}</p>
                <p v-if="post.event.venue" class="flex items-center gap-2 text-ink-muted"><Icon name="pin" :size="16" />{{ post.event.venue }}</p>
                <button v-if="post.type === 'event' && post.event.registration_open" type="button" class="mt-1 justify-self-start rounded-control bg-ink px-5 py-2 text-xs font-semibold text-white transition hover:bg-ink/85" @click="register">Register</button>
            </div>

            <p v-if="post.caption" class="whitespace-pre-line text-sm"><strong v-if="post.club">{{ post.club.name }}</strong> {{ post.caption }}</p>

            <template v-if="!full">
                <Link v-if="post.comments_count > post.comments.length" :href="post.url" class="text-sm text-ink-muted">View all {{ post.comments_count }} comments</Link>
                <p v-for="c in post.comments" :key="c.id" class="text-sm"><strong>{{ c.user }}</strong> {{ c.body }}</p>
            </template>

            <form class="flex gap-2 border-t border-line pt-3" @submit.prevent="sendComment">
                <input v-model="comment.body" maxlength="1000" placeholder="Add a comment…" aria-label="Add a comment" class="flex-1 bg-transparent text-sm outline-none" />
                <button type="submit" :disabled="!comment.body.trim() || comment.processing" class="text-sm font-semibold text-brand-blue disabled:opacity-40">Post</button>
            </form>
        </div>
    </article>
</template>
