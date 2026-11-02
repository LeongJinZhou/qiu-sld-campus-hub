<script setup lang="ts">
import { Html5Qrcode } from "html5-qrcode";
import { onBeforeUnmount, onMounted, ref, useId } from "vue";
import Button from "./Button.vue";

/**
 * Live camera that captures a photo by itself once it sees the expected person's profile QR code (…/p/{token})
 * steadily in view. Someone else's QR is ignored with a warning. "Capture now" is a manual fallback.
 */
const props = defineProps<{ expectToken: string; personName: string }>();
const emit = defineEmits<{ capture: [photo: Blob] }>();

const id = `live-qr-${useId()}`;
const state = ref<
    "starting" | "looking" | "steady" | "wrong" | "captured" | "error"
>("starting");
let scanner: Html5Qrcode | null = null;
let disposed = false;
let capturing = false;
let hits = 0;
let lastHit = 0;

const tokenOf = (text: string): string | null =>
    text.match(/\/p\/([A-Za-z0-9_-]+)/)?.[1] ?? null;

function grabFrame(): void {
    if (disposed || capturing || state.value === "captured") return;
    const video = document.getElementById(id)?.querySelector("video");
    if (!video || !video.videoWidth || !video.videoHeight) return;
    const canvas = document.createElement("canvas");
    canvas.width = video.videoWidth;
    canvas.height = video.videoHeight;
    const context = canvas.getContext("2d");
    if (!context) return;
    context.drawImage(video, 0, 0);
    capturing = true;
    canvas.toBlob(
        (blob) => {
            capturing = false;
            if (disposed) return;
            if (!blob) {
                state.value = "error";
                return;
            }
            state.value = "captured";
            void stop();
            emit("capture", blob);
        },
        "image/jpeg",
        0.92,
    );
}

function onScan(text: string): void {
    if (disposed || capturing || state.value === "captured") return;
    const token = tokenOf(text);
    if (token !== props.expectToken) {
        state.value = "wrong";
        hits = 0;
        return;
    }
    // Two matching reads close together = the QR is held steady, so the photo will be sharp.
    const now = Date.now();
    hits = now - lastHit < 1500 ? hits + 1 : 1;
    lastHit = now;
    state.value = "steady";
    if (hits >= 2) grabFrame();
}

async function stop(): Promise<void> {
    if (scanner?.isScanning) await scanner.stop().catch(() => undefined);
}

async function start(): Promise<void> {
    state.value = "starting";
    hits = 0;
    lastHit = 0;
    await stop();
    if (disposed) return;
    try {
        scanner = new Html5Qrcode(id, { verbose: false });
        await scanner.start(
            { facingMode: "environment" },
            {
                fps: 8,
                // Scan the middle of the picture, but photograph the whole frame (QR and cash together).
                qrbox: (w: number, h: number) => ({
                    width: Math.max(50, Math.round(Math.min(w, h) * 0.6)),
                    height: Math.max(50, Math.round(Math.min(w, h) * 0.6)),
                }),
                videoConstraints: {
                    facingMode: "environment",
                    width: { ideal: 1920 },
                    height: { ideal: 1080 },
                },
            },
            onScan,
            () => undefined,
        );
        if (disposed) await stop();
        else state.value = "looking";
    } catch {
        if (!disposed) state.value = "error";
    }
}
onMounted(() => void start());
onBeforeUnmount(() => {
    disposed = true;
    void stop();
});

const messages = {
    starting: "Starting the camera…",
    looking: `Point the camera at ${props.personName}'s phone, with the cash in the same picture.`,
    steady: "QR found — hold still…",
    wrong: `That QR code belongs to someone else. Ask ${props.personName} to show their own QR.`,
    captured: "Photo captured — checking…",
    error: "Camera unavailable. Allow camera access in your browser, then try again.",
};
</script>

<template>
    <div class="grid gap-3">
        <!--
            Fixed-shape frame: the video fills it (cropped, never stretched) and the library's own shaded box is
            hidden, so our guide square always sits exactly over the centre area the scanner reads.
        -->
        <div
            class="camera-frame relative mx-auto aspect-[3/4] w-full max-w-md overflow-hidden rounded-card bg-ink sm:aspect-[4/3]"
        >
            <div :id="id" class="absolute inset-0" />
            <div
                class="pointer-events-none absolute inset-0 grid place-items-center"
                aria-hidden="true"
            >
                <div
                    class="relative aspect-square w-[60%] max-w-[60%] rounded-2xl shadow-[0_0_0_9999px_rgb(0_0_0/0.35)] transition-colors duration-300 sm:w-auto sm:h-[60%]"
                    :class="
                        state === 'steady'
                            ? 'outline-4 outline-success'
                            : state === 'wrong'
                              ? 'outline-4 outline-danger'
                              : 'outline-2 outline-white/80'
                    "
                    style="outline-style: solid; outline-offset: 0"
                >
                    <span
                        v-if="state === 'looking'"
                        class="absolute inset-x-3 top-1/2 h-0.5 -translate-y-1/2 animate-pulse rounded bg-brand-cyan/80"
                    />
                </div>
            </div>
            <span
                class="pointer-events-none absolute inset-x-3 bottom-3 rounded-control px-3 py-2 text-center text-sm font-medium text-white backdrop-blur"
                :class="
                    state === 'wrong' || state === 'error'
                        ? 'bg-danger/90'
                        : state === 'steady'
                          ? 'bg-success/90'
                          : 'bg-black/60'
                "
            >
                {{ messages[state] }}
            </span>
        </div>
        <Button v-if="state === 'error'" variant="secondary" @click="start">Try camera again</Button>
        <Button
            v-if="
                state === 'looking' || state === 'steady' || state === 'wrong'
            "
            variant="secondary"
            @click="grabFrame"
            >Capture now</Button
        >
    </div>
</template>
