<script setup lang="ts">
import { Html5Qrcode } from 'html5-qrcode';
import { onBeforeUnmount, onMounted, ref, useId } from 'vue';
import Button from './Button.vue';

/**
 * Live camera that captures a photo by itself once it sees the expected person's profile QR code (…/p/{token})
 * steadily in view. Someone else's QR is ignored with a warning. "Capture now" is a manual fallback.
 */
const props = defineProps<{ expectToken: string; personName: string }>();
const emit = defineEmits<{ capture: [photo: Blob] }>();

const id = `live-qr-${useId()}`;
const state = ref<'starting' | 'looking' | 'steady' | 'wrong' | 'captured' | 'error'>('starting');
let scanner: Html5Qrcode | null = null;
let hits = 0;
let lastHit = 0;

const tokenOf = (text: string): string | null => text.match(/\/p\/([A-Za-z0-9_-]+)/)?.[1] ?? null;

function grabFrame(): void {
    const video = document.querySelector<HTMLVideoElement>(`#${id} video`);
    if (!video || !video.videoWidth) return;
    const canvas = document.createElement('canvas');
    canvas.width = video.videoWidth;
    canvas.height = video.videoHeight;
    canvas.getContext('2d')?.drawImage(video, 0, 0);
    canvas.toBlob((blob) => {
        if (!blob) return;
        state.value = 'captured';
        void stop();
        emit('capture', blob);
    }, 'image/jpeg', 0.92);
}

function onScan(text: string): void {
    if (state.value === 'captured') return;
    const token = tokenOf(text);
    if (token !== props.expectToken) {
        state.value = 'wrong';
        hits = 0;
        return;
    }
    // Two matching reads close together = the QR is held steady, so the photo will be sharp.
    const now = Date.now();
    hits = now - lastHit < 1500 ? hits + 1 : 1;
    lastHit = now;
    state.value = 'steady';
    if (hits >= 2) grabFrame();
}

async function stop(): Promise<void> {
    if (scanner?.isScanning) await scanner.stop().catch(() => undefined);
}

onMounted(async () => {
    scanner = new Html5Qrcode(id, { verbose: false });
    try {
        await scanner.start(
            { facingMode: 'environment' },
            {
                fps: 8,
                // Scan the middle of the picture, but photograph the whole frame (QR and cash together).
                qrbox: (w: number, h: number) => ({ width: Math.round(Math.min(w, h) * 0.6), height: Math.round(Math.min(w, h) * 0.6) }),
                videoConstraints: { facingMode: 'environment', width: { ideal: 1920 }, height: { ideal: 1080 } },
            },
            onScan,
            () => undefined,
        );
        state.value = 'looking';
    } catch {
        state.value = 'error';
    }
});
onBeforeUnmount(() => void stop());

const messages = {
    starting: 'Starting the camera…',
    looking: `Point the camera at ${props.personName}'s phone, with the cash in the same picture.`,
    steady: 'QR found — hold still…',
    wrong: `That QR code belongs to someone else. Ask ${props.personName} to show their own QR.`,
    captured: 'Photo captured — checking…',
    error: 'Camera unavailable. Allow camera access in your browser and reload this page.',
};
</script>

<template>
    <div class="grid gap-3">
        <div class="relative overflow-hidden rounded-card bg-ink">
            <div :id="id" class="min-h-64 [&_video]:!w-full [&_video]:object-cover" />
            <span class="pointer-events-none absolute inset-x-3 bottom-3 rounded-control px-3 py-2 text-center text-sm font-medium text-white backdrop-blur" :class="state === 'wrong' || state === 'error' ? 'bg-danger/90' : state === 'steady' ? 'bg-success/90' : 'bg-black/60'">
                {{ messages[state] }}
            </span>
        </div>
        <Button v-if="state === 'looking' || state === 'steady' || state === 'wrong'" variant="secondary" @click="grabFrame">Capture now</Button>
    </div>
</template>
