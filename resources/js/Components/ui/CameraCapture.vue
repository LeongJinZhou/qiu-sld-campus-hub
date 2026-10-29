<script setup lang="ts">
import { onBeforeUnmount, onMounted, ref } from 'vue';
import Button from './Button.vue';

const emit = defineEmits<{ capture: [photo: Blob] }>();
const video = ref<HTMLVideoElement | null>(null);
const preview = ref<string | null>(null);
const error = ref<string | null>(null);
let stream: MediaStream | null = null;
let photo: Blob | null = null;

onMounted(async () => {
    try {
        stream = await navigator.mediaDevices.getUserMedia({ video: { facingMode: 'environment' }, audio: false });
        if (video.value) {
            video.value.srcObject = stream;
        }
    } catch {
        error.value = 'Camera unavailable. Allow camera access and try again.';
    }
});

onBeforeUnmount(() => {
    stream?.getTracks().forEach((track) => track.stop());
    if (preview.value) {
        URL.revokeObjectURL(preview.value);
    }
});

function take(): void {
    const source = video.value;
    if (!source) {
        return;
    }
    const canvas = document.createElement('canvas');
    canvas.width = source.videoWidth;
    canvas.height = source.videoHeight;
    canvas.getContext('2d')?.drawImage(source, 0, 0);
    canvas.toBlob((blob) => {
        if (blob) {
            photo = blob;
            preview.value = URL.createObjectURL(blob);
        }
    }, 'image/jpeg', 0.9);
}

function retake(): void {
    if (preview.value) {
        URL.revokeObjectURL(preview.value);
    }
    preview.value = null;
    photo = null;
}

function confirm(): void {
    if (photo) {
        emit('capture', photo);
    }
}
</script>

<template>
    <div class="grid gap-3">
        <p v-if="error" class="text-sm text-danger">{{ error }}</p>
        <img v-if="preview" :src="preview" alt="Captured photo" class="rounded-card" />
        <video v-show="!preview" ref="video" autoplay playsinline muted class="rounded-card bg-ink" />
        <div class="flex gap-2">
            <template v-if="preview">
                <Button variant="secondary" @click="retake">Retake</Button>
                <Button @click="confirm">Use photo</Button>
            </template>
            <Button v-else :disabled="!!error" @click="take">Take photo</Button>
        </div>
    </div>
</template>
