<script setup lang="ts">
import { Html5Qrcode } from 'html5-qrcode';
import { onBeforeUnmount, onMounted, ref, useId } from 'vue';

const emit = defineEmits<{ scan: [value: string] }>();
const id = `qr-${useId()}`;
const error = ref<string | null>(null);
let scanner: Html5Qrcode | null = null;

onMounted(async () => {
    scanner = new Html5Qrcode(id);
    try {
        await scanner.start({ facingMode: 'environment' }, { fps: 10, qrbox: 240 }, (text) => emit('scan', text), () => undefined);
    } catch {
        error.value = 'Camera unavailable. Allow camera access and try again.';
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
        <div :id="id" class="overflow-hidden rounded-card bg-ink" />
        <p v-if="error" class="text-sm text-danger">{{ error }}</p>
    </div>
</template>
