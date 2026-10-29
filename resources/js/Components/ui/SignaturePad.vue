<script setup lang="ts">
import { onBeforeUnmount, onMounted, ref } from 'vue';
import Icon from './Icon.vue';

/**
 * E-signature pad. Records every stroke from mouse, finger or stylus (x, y, time, pressure) and exports a
 * tightly cropped transparent PNG for the printed forms. The recording is sent with the PNG as evidence.
 */
withDefaults(defineProps<{ label?: string; disabled?: boolean; height?: number; signer?: string }>(), { height: 180 });
/** PNG data URL of the signature, or null when empty. */
const model = defineModel<string | null>({ default: null });
/** JSON recording: {w, h, device, strokes: [[[x, y, t, pressure], ...], ...]}. */
const strokesModel = defineModel<string | null>('strokes', { default: null });

type Point = [number, number, number, number];
const INK = '#14213d';

const wrapper = ref<HTMLDivElement | null>(null);
const canvas = ref<HTMLCanvasElement | null>(null);
const strokes = ref<Point[][]>([]);
const device = ref<'mouse' | 'touch' | 'pen'>('mouse');
let current: Point[] | null = null;
let started = 0;
let ctx: CanvasRenderingContext2D | null = null;
let observer: ResizeObserver | null = null;

// Sizes the canvas to its box (and device pixel ratio) every time it changes, including when a hidden
// dialog opens, then redraws what was already signed.
function resize(): void {
    const el = canvas.value;
    if (!el || el.clientWidth === 0) return;
    const ratio = window.devicePixelRatio || 1;
    el.width = Math.round(el.clientWidth * ratio);
    el.height = Math.round(el.clientHeight * ratio);
    ctx = el.getContext('2d');
    ctx?.setTransform(ratio, 0, 0, ratio, 0, 0);
    redraw();
}

function width(p: Point): number {
    return 1.2 + 2.6 * (p[3] || 0.5);
}

function drawStroke(target: CanvasRenderingContext2D, stroke: Point[], dx = 0, dy = 0): void {
    target.strokeStyle = INK;
    target.fillStyle = INK;
    target.lineCap = 'round';
    target.lineJoin = 'round';
    const [first] = stroke;
    if (!first) return;
    if (stroke.length === 1) {
        target.beginPath();
        target.arc(first[0] - dx, first[1] - dy, width(first) / 2, 0, Math.PI * 2);
        target.fill();
        return;
    }
    // Quadratic curves through midpoints give smooth ink instead of jagged segments.
    for (let i = 1; i < stroke.length; i++) {
        const a = stroke[i - 1]!;
        const b = stroke[i]!;
        const before = stroke[i - 2] ?? a;
        target.beginPath();
        target.lineWidth = width(b);
        target.moveTo((before[0] + a[0]) / 2 - dx, (before[1] + a[1]) / 2 - dy);
        target.quadraticCurveTo(a[0] - dx, a[1] - dy, (a[0] + b[0]) / 2 - dx, (a[1] + b[1]) / 2 - dy);
        target.stroke();
    }
}

function redraw(): void {
    const el = canvas.value;
    if (!ctx || !el) return;
    ctx.clearRect(0, 0, el.width, el.height);
    for (const stroke of strokes.value) drawStroke(ctx, stroke);
}

function point(event: PointerEvent): Point {
    const rect = canvas.value!.getBoundingClientRect();
    const pressure = event.pointerType === 'mouse' ? 0.5 : event.pressure || 0.5;
    return [
        Math.round((event.clientX - rect.left) * 10) / 10,
        Math.round((event.clientY - rect.top) * 10) / 10,
        Math.round(performance.now() - started),
        Math.round(pressure * 100) / 100,
    ];
}

function down(event: PointerEvent): void {
    event.preventDefault();
    if (!started) started = performance.now();
    device.value = event.pointerType === 'touch' ? 'touch' : event.pointerType === 'pen' ? 'pen' : 'mouse';
    canvas.value?.setPointerCapture(event.pointerId);
    current = [point(event)];
    strokes.value.push(current);
    redraw();
}

function move(event: PointerEvent): void {
    if (!current) return;
    event.preventDefault();
    // Coalesced events keep fast finger/stylus strokes smooth.
    const events = typeof event.getCoalescedEvents === 'function' ? event.getCoalescedEvents() : [event];
    for (const e of events.length ? events : [event]) current.push(point(e));
    redraw();
}

function up(): void {
    if (!current) return;
    current = null;
    emit();
}

function emit(): void {
    const el = canvas.value;
    const all = strokes.value.flat();
    if (!el || all.length === 0) {
        model.value = null;
        strokesModel.value = null;
        return;
    }
    // Crop to the ink with a small margin, on a transparent background, at 2x for crisp printing.
    const pad = 8;
    const xs = all.map((p) => p[0]);
    const ys = all.map((p) => p[1]);
    const minX = Math.max(0, Math.min(...xs) - pad);
    const minY = Math.max(0, Math.min(...ys) - pad);
    const w = Math.max(...xs) + pad - minX;
    const h = Math.max(...ys) + pad - minY;
    const out = document.createElement('canvas');
    out.width = Math.ceil(w * 2);
    out.height = Math.ceil(h * 2);
    const octx = out.getContext('2d');
    if (!octx) return;
    octx.scale(2, 2);
    for (const stroke of strokes.value) drawStroke(octx, stroke, minX, minY);

    model.value = out.toDataURL('image/png');
    strokesModel.value = JSON.stringify({ w: el.clientWidth, h: el.clientHeight, device: device.value, strokes: strokes.value });
}

function undo(): void {
    strokes.value.pop();
    redraw();
    emit();
}

function clear(): void {
    strokes.value = [];
    started = 0;
    redraw();
    emit();
}

onMounted(() => {
    observer = new ResizeObserver(resize);
    if (wrapper.value) observer.observe(wrapper.value);
    resize();
});
onBeforeUnmount(() => observer?.disconnect());
</script>

<template>
    <div class="grid gap-2">
        <div class="flex items-center justify-between">
            <span v-if="label" class="text-sm font-medium">{{ label }}</span>
            <div v-if="!disabled" class="ml-auto flex gap-1">
                <button type="button" class="inline-flex items-center gap-1 rounded-control px-2 py-1 text-xs text-ink-muted hover:bg-surface-muted hover:text-ink disabled:opacity-40" :disabled="!strokes.length" @click="undo">↶ Undo</button>
                <button type="button" class="inline-flex items-center gap-1 rounded-control px-2 py-1 text-xs text-ink-muted hover:bg-surface-muted hover:text-ink disabled:opacity-40" :disabled="!strokes.length" @click="clear">
                    <Icon name="x" :size="14" /> Clear
                </button>
            </div>
        </div>
        <div ref="wrapper" class="relative overflow-hidden rounded-card border-2 border-dashed bg-white transition" :class="model ? 'border-brand-cyan' : 'border-line'" :style="{ height: `${height}px` }">
            <!-- Signing guide: baseline with an X, like a paper form. -->
            <div class="pointer-events-none absolute inset-x-6 bottom-10 flex items-end gap-2 text-ink-muted/60" aria-hidden="true">
                <span class="text-lg leading-none">✕</span>
                <span class="mb-1 h-px flex-1 bg-ink-muted/40" />
            </div>
            <p v-if="!strokes.length" class="pointer-events-none absolute inset-0 grid place-items-center px-6 text-center text-sm text-ink-muted/70">
                Sign here with your mouse, finger or stylus
            </p>
            <p v-if="signer" class="pointer-events-none absolute bottom-2 left-6 text-xs text-ink-muted">{{ signer }}</p>
            <canvas
                ref="canvas"
                class="absolute inset-0 size-full touch-none select-none"
                :class="disabled ? 'pointer-events-none opacity-60' : 'cursor-crosshair'"
                aria-label="Signature pad"
                @pointerdown="down"
                @pointermove="move"
                @pointerup="up"
                @pointercancel="up"
                @pointerleave="up"
            />
        </div>
        <p class="text-xs text-ink-muted">
            <template v-if="model">✓ Signature captured ({{ device }}). The server adds the date, time and a verification code.</template>
            <template v-else>Your drawn signature is recorded and printed on the official form.</template>
        </p>
    </div>
</template>
