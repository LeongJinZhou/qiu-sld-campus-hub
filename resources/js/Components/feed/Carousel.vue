<script setup lang="ts">
import { ref } from 'vue';

defineProps<{ images: string[]; alt: string }>();
const index = ref(0);
const track = ref<HTMLElement | null>(null);
// Like Instagram: the first photo sets the frame, kept between 4:5 portrait and 1.91:1 landscape, so posters are
// not cropped to a square. Square until the first photo has loaded.
const ratio = ref(1);

function onScroll(): void {
    const el = track.value;
    if (el) index.value = Math.round(el.scrollLeft / el.clientWidth);
}
function onLoad(event: Event, i: number): void {
    const img = event.target as HTMLImageElement;
    if (i === 0 && img.naturalWidth && img.naturalHeight) {
        ratio.value = Math.min(1.91, Math.max(0.8, img.naturalWidth / img.naturalHeight));
    }
}
const go = (step: number): void => track.value?.scrollBy({ left: step * track.value.clientWidth, behavior: 'smooth' });
</script>

<template>
    <div class="group relative bg-surface-muted">
        <div ref="track" class="flex snap-x snap-mandatory overflow-x-auto [scrollbar-width:none] [&::-webkit-scrollbar]:hidden" :style="{ aspectRatio: ratio }" @scroll.passive="onScroll">
            <img
                v-for="(src, i) in images"
                :key="`${i}-${src}`"
                :src="src"
                :alt="`${alt} ${i + 1}`"
                class="h-full w-full shrink-0 snap-center object-cover"
                :loading="i === 0 ? 'eager' : 'lazy'"
                decoding="async"
                @load="onLoad($event, i)"
            />
        </div>
        <template v-if="images.length > 1">
            <button v-if="index > 0" type="button" class="absolute left-2 top-1/2 hidden size-8 -translate-y-1/2 place-items-center rounded-full bg-white/90 text-ink shadow-card group-hover:grid" aria-label="Previous photo" @click="go(-1)">‹</button>
            <button v-if="index < images.length - 1" type="button" class="absolute right-2 top-1/2 hidden size-8 -translate-y-1/2 place-items-center rounded-full bg-white/90 text-ink shadow-card group-hover:grid" aria-label="Next photo" @click="go(1)">›</button>
            <span class="absolute right-3 top-3 rounded-full bg-black/60 px-2 py-0.5 text-xs font-medium text-white">{{ index + 1 }}/{{ images.length }}</span>
            <div class="absolute inset-x-0 bottom-2 flex justify-center gap-1" aria-hidden="true">
                <span v-for="(_, i) in images" :key="i" class="size-1.5 rounded-full transition" :class="i === index ? 'bg-white' : 'bg-white/50'" />
            </div>
        </template>
    </div>
</template>
