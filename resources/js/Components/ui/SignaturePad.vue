<script setup lang="ts">
import { onMounted, ref } from 'vue';

defineProps<{ label?: string; disabled?: boolean }>();
/** PNG data URL of the drawn signature, or null when empty. */
const model = defineModel<string | null>({ default: null });
const canvas = ref<HTMLCanvasElement | null>(null);
let context: CanvasRenderingContext2D | null = null;
let drawing = false;

onMounted(() => {
    const element = canvas.value;
    if (!element) {
        return;
    }
    const ratio = window.devicePixelRatio || 1;
    element.width = element.offsetWidth * ratio;
    element.height = element.offsetHeight * ratio;
    context = element.getContext('2d');
    context?.scale(ratio, ratio);
    if (context) {
        context.lineWidth = 2;
        context.lineCap = 'round';
        context.strokeStyle = '#1a1a1f';
    }
});

function point(event: PointerEvent): [number, number] {
    const rect = canvas.value?.getBoundingClientRect();
    return [event.clientX - (rect?.left ?? 0), event.clientY - (rect?.top ?? 0)];
}

function start(event: PointerEvent): void {
    drawing = true;
    canvas.value?.setPointerCapture(event.pointerId);
    context?.beginPath();
    context?.moveTo(...point(event));
}

function move(event: PointerEvent): void {
    if (!drawing) {
        return;
    }
    context?.lineTo(...point(event));
    context?.stroke();
}

function end(): void {
    if (drawing) {
        drawing = false;
        model.value = canvas.value?.toDataURL('image/png') ?? null;
    }
}

function clear(): void {
    const element = canvas.value;
    if (element) {
        context?.clearRect(0, 0, element.width, element.height);
    }
    model.value = null;
}
</script>

<template>
    <div class="grid gap-1.5">
        <span v-if="label" class="text-sm font-medium">{{ label }}</span>
        <canvas
            ref="canvas"
            class="h-40 w-full touch-none rounded-control border border-dashed border-line bg-surface"
            :class="disabled ? 'pointer-events-none opacity-60' : 'cursor-crosshair'"
            aria-label="Signature pad"
            @pointerdown="start"
            @pointermove="move"
            @pointerup="end"
            @pointerleave="end"
        />
        <button v-if="!disabled" type="button" class="justify-self-end text-sm text-ink-muted hover:text-ink" @click="clear">Clear</button>
    </div>
</template>
