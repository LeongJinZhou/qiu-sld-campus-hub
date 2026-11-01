<script setup lang="ts">
import { Html5Qrcode } from "html5-qrcode";
import { onBeforeUnmount, onMounted, ref, useId } from "vue";

const emit = defineEmits<{ scan: [value: string] }>();
const id = `qr-${useId()}`;
const error = ref<string | null>(null);
let scanner: Html5Qrcode | null = null;

onMounted(async () => {
    scanner = new Html5Qrcode(id);
    try {
        await scanner.start(
            { facingMode: "environment" },
            {
                fps: 10,
                qrbox: (w: number, h: number) => ({
                    width: Math.round(Math.min(w, h) * 0.65),
                    height: Math.round(Math.min(w, h) * 0.65),
                }),
            },
            (text) => emit("scan", text),
            () => undefined,
        );
    } catch {
        error.value = "Camera unavailable. Allow camera access and try again.";
    }
});

onBeforeUnmount(async () => {
    if (scanner?.isScanning) {
        await scanner.stop();
    }
});
</script>

<template>
    <div class="grid gap-2">
        <div
            class="camera-frame relative mx-auto aspect-square w-full max-w-sm overflow-hidden rounded-card bg-ink"
        >
            <div :id="id" class="absolute inset-0" />
            <div
                class="pointer-events-none absolute inset-0 grid place-items-center"
                aria-hidden="true"
            >
                <div
                    class="aspect-square w-[65%] rounded-2xl shadow-[0_0_0_9999px_rgb(0_0_0/0.35)] outline-2 outline-white/80"
                    style="outline-style: solid"
                />
            </div>
        </div>
        <p v-if="error" class="text-sm text-danger">{{ error }}</p>
    </div>
</template>
