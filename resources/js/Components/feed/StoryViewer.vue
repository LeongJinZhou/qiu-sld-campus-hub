<script setup lang="ts">
import Avatar from '@/Components/ui/Avatar.vue';
import { onBeforeUnmount, ref, watch } from 'vue';

const props = defineProps<{ group: { name: string; logo_url: string | null; stories: { id: number; url: string; at: string | null }[] } | null }>();
const emit = defineEmits<{ close: []; highlight: [storyId: number] }>();
const index = ref(0);
let timer: ReturnType<typeof setTimeout> | undefined;

function next(): void {
    if (!props.group) return;
    if (index.value < props.group.stories.length - 1) index.value++;
    else emit('close');
}
function prev(): void {
    index.value = Math.max(0, index.value - 1);
}

watch(
    () => [props.group, index.value] as const,
    ([group]) => {
        clearTimeout(timer);
        if (group) timer = setTimeout(next, 5000);
    },
    { immediate: true },
);
watch(() => props.group, () => (index.value = 0));
onBeforeUnmount(() => clearTimeout(timer));
</script>

<template>
    <div v-if="group" class="fixed inset-0 z-50 grid place-items-center bg-ink/95" role="dialog" aria-modal="true" :aria-label="`${group.name} story`">
        <div class="relative aspect-[9/16] h-[min(90dvh,48rem)] max-w-full overflow-hidden rounded-card bg-ink">
            <div class="absolute inset-x-3 top-3 z-10 flex gap-1">
                <span v-for="(s, i) in group.stories" :key="s.id" class="h-0.5 flex-1 rounded bg-white/40"><span class="block h-full rounded bg-white" :class="i <= index ? 'w-full' : 'w-0'" /></span>
            </div>
            <div class="absolute inset-x-3 top-6 z-10 flex items-center gap-2 text-sm font-semibold text-white">
                <Avatar :src="group.logo_url" :name="group.name" size="xs" />{{ group.name }}<span class="font-normal text-white/70">{{ group.stories[index]?.at }}</span>
                <button type="button" class="ml-auto text-xl" aria-label="Close" @click="emit('close')">✕</button>
            </div>
            <img :src="group.stories[index]?.url" alt="" class="size-full object-contain" />
            <button type="button" class="absolute inset-y-0 left-0 w-1/3" aria-label="Previous" @click="prev" />
            <button type="button" class="absolute inset-y-0 right-0 w-2/3" aria-label="Next" @click="next" />
            <slot :story-id="group.stories[index]?.id" />
        </div>
    </div>
</template>
