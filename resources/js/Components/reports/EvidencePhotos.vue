<script setup lang="ts">
import Icon from '@/Components/ui/Icon.vue';
import { router } from '@inertiajs/vue3';
import { ref } from 'vue';

/** Evidence photos for a weekly report, an AGM or an event: shows the count against SLD's minimum, add and remove. */
const props = defineProps<{ evidence: { type: string; id: number; minimum: number; photos: { id: number | null; url: string }[] }; canEdit?: boolean; title?: string }>();
const uploading = ref(false);
function add(e: Event): void {
    const files = [...((e.target as HTMLInputElement).files ?? [])];
    if (!files.length) return;
    uploading.value = true;
    router.post(
        `/evidence/${props.evidence.type}/${props.evidence.id}`,
        { photos: files },
        { forceFormData: true, preserveScroll: true, onFinish: () => (uploading.value = false) },
    );
    (e.target as HTMLInputElement).value = '';
}
const remove = (id: number): void => {
    if (confirm('Remove this photo?')) router.delete(`/evidence-photos/${id}`, { preserveScroll: true });
};
</script>

<template>
    <section class="grid gap-3 rounded-card border bg-surface p-4 shadow-card" :class="evidence.photos.length >= evidence.minimum ? 'border-line' : 'border-warning/50'">
        <header class="flex flex-wrap items-center justify-between gap-2">
            <div>
                <h2 class="font-semibold">{{ title ?? 'Photos' }}<span class="text-danger"> *</span></h2>
                <p class="text-xs" :class="evidence.photos.length >= evidence.minimum ? 'text-success' : 'text-warning'">
                    {{ evidence.photos.length }} of at least {{ evidence.minimum }} required{{ evidence.photos.length >= evidence.minimum ? ' ✓' : '' }}
                </p>
            </div>
            <label v-if="canEdit" class="inline-flex h-9 cursor-pointer items-center gap-1.5 rounded-control bg-ink px-3 text-sm font-medium text-white">
                <Icon name="camera" :size="16" /> {{ uploading ? 'Uploading…' : 'Add photos' }}
                <input type="file" accept="image/jpeg,image/png,image/webp" multiple class="sr-only" @change="add" />
            </label>
        </header>
        <ul v-if="evidence.photos.length" class="grid grid-cols-3 gap-2 sm:grid-cols-5">
            <li v-for="(p, i) in evidence.photos" :key="i" class="relative aspect-square overflow-hidden rounded-control bg-surface-muted">
                <img :src="p.url" alt="" class="size-full object-cover" loading="lazy" />
                <button
                    v-if="canEdit && p.id"
                    type="button"
                    class="absolute right-1 top-1 grid size-6 place-items-center rounded-full bg-black/60 text-white"
                    aria-label="Remove photo"
                    @click="remove(p.id)"
                >
                    <Icon name="x" :size="12" />
                </button>
            </li>
        </ul>
    </section>
</template>
