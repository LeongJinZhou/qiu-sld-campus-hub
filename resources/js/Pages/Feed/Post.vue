<script setup lang="ts">
import PostCard from "@/Components/feed/PostCard.vue";
import Avatar from "@/Components/ui/Avatar.vue";
import Modal from "@/Components/ui/Modal.vue";
import AppLayout from "@/Layouts/AppLayout.vue";
import type { CommentData, PostCardData } from "@/types";
import { Head, Link } from "@inertiajs/vue3";
import { computed, ref } from "vue";

interface Liker {
    name: string;
    photo_url: string | null;
    url: string;
}
const props = defineProps<{
    post: PostCardData;
    comments: CommentData[];
    likers: Liker[];
}>();

const showLikers = ref(false);
const likedBy = computed(() => {
    const [first, second] = props.likers;
    if (!first) return "";
    const rest = props.post.likes_count - (second ? 2 : 1);
    return `${first.name}${second ? `${rest > 0 ? ", " : " and "}${second.name}` : ""}${rest > 0 ? ` and ${rest} other${rest === 1 ? "" : "s"}` : ""}`;
});
</script>

<template>
    <Head :title="post.event?.name ?? post.club?.name ?? 'Post'" />
    <AppLayout>
        <div class="mx-auto grid max-w-xl gap-4">
            <PostCard :post="post" full />

            <button
                v-if="likers.length"
                type="button"
                class="reveal flex items-center gap-2 text-left text-sm"
                @click="showLikers = true"
            >
                <span class="flex -space-x-2">
                    <Avatar
                        v-for="l in likers.slice(0, 3)"
                        :key="l.url"
                        :src="l.photo_url"
                        :name="l.name"
                        size="xs"
                        class="ring-2 ring-surface"
                    />
                </span>
                <span
                    >Liked by <strong>{{ likedBy }}</strong></span
                >
            </button>

            <section class="grid gap-3">
                <h2 class="text-sm font-semibold text-ink-muted">
                    {{ comments.length }} comment{{
                        comments.length === 1 ? "" : "s"
                    }}
                </h2>
                <TransitionGroup tag="ul" name="list" class="grid gap-3">
                    <li
                        v-for="c in comments"
                        :key="c.id"
                        class="flex gap-3 text-sm"
                    >
                        <Link :href="c.profile_url"
                            ><Avatar
                                :src="c.photo_url"
                                :name="c.user"
                                size="xs"
                        /></Link>
                        <p>
                            <Link
                                :href="c.profile_url"
                                class="font-semibold hover:underline"
                                >{{ c.user }}</Link
                            >
                            {{ c.body }}
                            <span class="block text-xs text-ink-muted">{{
                                c.at
                            }}</span>
                        </p>
                    </li>
                </TransitionGroup>
            </section>
        </div>

        <Modal v-model:open="showLikers" title="Likes">
            <ul class="grid max-h-[60vh] gap-3 overflow-y-auto">
                <li v-for="l in likers" :key="l.url">
                    <Link
                        :href="l.url"
                        class="flex items-center gap-3 text-sm font-medium hover:underline"
                        ><Avatar
                            :src="l.photo_url"
                            :name="l.name"
                            size="sm"
                        />{{ l.name }}</Link
                    >
                </li>
            </ul>
        </Modal>
    </AppLayout>
</template>
