<script setup lang="ts">
import { ref } from 'vue';

defineProps<{ images: string[]; alt: string }>();
const index = ref(0);
const track = ref<HTMLElement | null>(null);

function onScroll(): void {
    const el = track.value;
    if (el) index.value = Math.round(el.scrollLeft / el.clientWidth);
}
</script>

<template>
    <div class="relative">
        <div ref="track" class="flex snap-x snap-mandatory overflow-x-auto [scrollbar-width:none]" @scroll.passive="onScroll">
            <img v-for="(src, i) in images" :key="src" :src="src" :alt="`${alt} ${i + 1}`" class="aspect-square w-full shrink-0 snap-center bg-surface-muted object-cover" loading="lazy" />
        </div>
        <div v-if="images.length > 1" class="absolute inset-x-0 bottom-2 flex justify-center gap-1" aria-hidden="true">
            <span v-for="(_, i) in images" :key="i" class="size-1.5 rounded-full" :class="i === index ? 'bg-white' : 'bg-white/50'" />
        </div>
    </div>
</template>
