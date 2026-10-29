<script setup lang="ts">
import Button from '@/Components/ui/Button.vue';
import FileUpload from '@/Components/ui/FileUpload.vue';
import Modal from '@/Components/ui/Modal.vue';
import Select from '@/Components/ui/Select.vue';
import Textarea from '@/Components/ui/Textarea.vue';
import { useForm } from '@inertiajs/vue3';
import { ref } from 'vue';

const props = defineProps<{ clubs: { slug: string; name: string }[]; events?: { id: number; name: string }[] }>();
const open = defineModel<boolean>('open', { default: false });
const mode = ref<'post' | 'story'>('post');
const club = ref(props.clubs[0]?.slug ?? '');

const post = useForm({ type: 'general', caption: '', event_id: null as number | null, photos: [] as File[] });
const story = useForm({ photo: [] as File[] });

function submit(): void {
    const done = { preserveScroll: true, onSuccess: () => { open.value = false; post.reset(); story.reset(); } };
    if (mode.value === 'story') {
        story.transform((d) => ({ photo: d.photo[0] ?? null })).post(`/clubs/${club.value}/stories`, done);
    } else {
        post.post(`/clubs/${club.value}/posts`, done);
    }
}
</script>

<template>
    <Modal v-model:open="open" title="Create">
        <form class="grid gap-4" @submit.prevent="submit">
            <div class="grid grid-cols-2 gap-1 rounded-control bg-surface-muted p-1 text-sm">
                <button v-for="m in ['post', 'story'] as const" :key="m" type="button" class="rounded-control py-1.5 capitalize" :class="mode === m ? 'bg-surface font-semibold shadow-card' : ''" @click="mode = m">{{ m }}</button>
            </div>
            <Select v-if="clubs.length > 1" v-model="club" label="Post as" :options="clubs.map((c) => ({ value: c.slug, label: c.name }))" />
            <template v-if="mode === 'post'">
                <Select v-if="events?.length" v-model="post.type" label="Type" :options="[{ value: 'general', label: 'Post' }, { value: 'recap', label: 'Event recap' }]" />
                <Select v-if="post.type === 'recap' && events?.length" v-model="post.event_id" label="Event" :options="events.map((e) => ({ value: e.id, label: e.name }))" placeholder="Choose event" :error="post.errors.event_id" />
                <FileUpload v-model="post.photos" label="Photos (up to 10)" accept="image/jpeg,image/png,image/webp" multiple :error="post.errors.photos ?? post.errors['photos.0']" />
                <Textarea v-model="post.caption" label="Caption" :rows="3" :error="post.errors.caption" />
            </template>
            <FileUpload v-else v-model="story.photo" label="Story photo (visible 24 hours)" accept="image/jpeg,image/png,image/webp" :error="story.errors.photo" />
            <p class="text-xs text-ink-muted">Real QIU photos only — no AI-generated people.</p>
            <Button type="submit" :loading="post.processing || story.processing">Share</Button>
        </form>
    </Modal>
</template>
