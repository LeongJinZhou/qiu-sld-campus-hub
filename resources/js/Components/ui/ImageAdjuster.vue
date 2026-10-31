<script setup lang="ts">
import { useResizeObserver } from '@vueuse/core';
import { computed, onBeforeUnmount, ref, watch } from 'vue';

/**
 * Drag to move and slide to zoom a photo inside a fixed frame (round for profile photos, wide for covers),
 * then export exactly what is inside the frame as a JPEG. Pure canvas, no library.
 */
const props = withDefaults(defineProps<{ file: Blob; aspect: number; round?: boolean; outputWidth: number }>(), { round: false });

const frame = ref<HTMLDivElement | null>(null);
const img = ref<HTMLImageElement | null>(null);
const url = ref('');
const zoom = ref(1);
const x = ref(0);
const y = ref(0);
const natural = ref({ w: 1, h: 1 });
const box = ref({ w: 1, h: 1 });
useResizeObserver(frame, ([entry]) => {
    box.value = { w: entry?.contentRect.width || 1, h: entry?.contentRect.height || 1 };
    clamp();
});
let drag: { id: number; sx: number; sy: number; ox: number; oy: number } | null = null;

watch(
    () => props.file,
    (file) => {
        if (url.value) URL.revokeObjectURL(url.value);
        url.value = URL.createObjectURL(file);
        zoom.value = 1;
        x.value = 0;
        y.value = 0;
    },
    { immediate: true },
);
onBeforeUnmount(() => URL.revokeObjectURL(url.value));

// Size of the photo on screen at the current zoom: always covers the frame.
const size = computed(() => {
    const { w: fw, h: fh } = box.value;
    const cover = Math.max(fw / natural.value.w, fh / natural.value.h) * zoom.value;
    return { w: natural.value.w * cover, h: natural.value.h * cover, fw, fh };
});

// Keep the photo covering the frame: no empty edges.
function clamp(): void {
    const d = size.value;
    const mx = Math.max(0, (d.w - d.fw) / 2);
    const my = Math.max(0, (d.h - d.fh) / 2);
    x.value = Math.min(mx, Math.max(-mx, x.value));
    y.value = Math.min(my, Math.max(-my, y.value));
}

function onLoad(): void {
    natural.value = { w: img.value?.naturalWidth || 1, h: img.value?.naturalHeight || 1 };
    clamp();
}
watch(zoom, clamp);

function down(e: PointerEvent): void {
    drag = { id: e.pointerId, sx: e.clientX, sy: e.clientY, ox: x.value, oy: y.value };
    (e.currentTarget as HTMLElement).setPointerCapture(e.pointerId);
}
function move(e: PointerEvent): void {
    if (!drag || drag.id !== e.pointerId) return;
    x.value = drag.ox + e.clientX - drag.sx;
    y.value = drag.oy + e.clientY - drag.sy;
    clamp();
}
function up(): void {
    drag = null;
}
function wheel(e: WheelEvent): void {
    zoom.value = Math.min(4, Math.max(1, zoom.value - e.deltaY * 0.002));
}

/** The framed area as a JPEG at outputWidth × outputWidth / aspect. */
function exportBlob(): Promise<Blob> {
    const d = size.value;
    const scale = natural.value.w / d.w; // screen px → source px
    const sx = (d.w - d.fw) / 2 - x.value;
    const sy = (d.h - d.fh) / 2 - y.value;
    const canvas = document.createElement('canvas');
    canvas.width = props.outputWidth;
    canvas.height = Math.round(props.outputWidth / props.aspect);
    const ctx = canvas.getContext('2d');
    if (ctx && img.value) {
        ctx.imageSmoothingQuality = 'high';
        ctx.drawImage(img.value, sx * scale, sy * scale, d.fw * scale, d.fh * scale, 0, 0, canvas.width, canvas.height);
    }
    return new Promise((resolve, reject) => canvas.toBlob((b) => (b ? resolve(b) : reject(new Error('export failed'))), 'image/jpeg', 0.9));
}
defineExpose({ exportBlob });
</script>

<template>
    <div class="grid gap-3">
        <div
            ref="frame"
            class="relative w-full touch-none select-none overflow-hidden bg-ink"
            :class="round ? 'mx-auto max-w-72 rounded-full' : 'rounded-card'"
            :style="{ aspectRatio: String(aspect) }"
            @pointerdown="down"
            @pointermove="move"
            @pointerup="up"
            @pointercancel="up"
            @wheel.prevent="wheel"
        >
            <img
                ref="img"
                :src="url"
                alt="Photo being adjusted"
                draggable="false"
                class="pointer-events-none absolute left-1/2 top-1/2 max-w-none cursor-grab"
                :style="{ width: `${size.w}px`, height: `${size.h}px`, transform: `translate(calc(-50% + ${x}px), calc(-50% + ${y}px))` }"
                @load="onLoad"
            />
            <span class="pointer-events-none absolute inset-x-0 bottom-2 text-center text-xs font-medium text-white drop-shadow">Drag to move</span>
        </div>
        <label class="flex items-center gap-3 text-sm">
            <span class="text-ink-muted">Zoom</span>
            <input v-model.number="zoom" type="range" min="1" max="4" step="0.01" class="flex-1 accent-brand-magenta" aria-label="Zoom" />
        </label>
    </div>
</template>
