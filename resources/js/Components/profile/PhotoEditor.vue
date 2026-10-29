<script setup lang="ts">
import Avatar from '@/Components/ui/Avatar.vue';
import Button from '@/Components/ui/Button.vue';
import CameraCapture from '@/Components/ui/CameraCapture.vue';
import Icon from '@/Components/ui/Icon.vue';
import Modal from '@/Components/ui/Modal.vue';
import { router } from '@inertiajs/vue3';
import { onBeforeUnmount, ref } from 'vue';

// Change profile photo: upload, take one with the camera, or go back to the Google photo.
defineProps<{ current: string | null; name: string; hasOverride: boolean }>();
const open = defineModel<boolean>('open', { default: false });

const mode = ref<'choose' | 'camera'>('choose');
const file = ref<File | null>(null);
const preview = ref<string | null>(null);
const saving = ref(false);

function setFile(f: File | Blob | null | undefined): void {
    if (!f) return;
    file.value = f instanceof File ? f : new File([f], 'camera.jpg', { type: 'image/jpeg' });
    if (preview.value) URL.revokeObjectURL(preview.value);
    preview.value = URL.createObjectURL(file.value);
    mode.value = 'choose';
}

function submit(data: { photo: File } | { remove_photo: 1 }): void {
    saving.value = true;
    router.post('/me', data, {
        forceFormData: true,
        preserveScroll: true,
        onFinish: () => (saving.value = false),
        onSuccess: () => {
            open.value = false;
            file.value = null;
            preview.value = null;
        },
    });
}

onBeforeUnmount(() => preview.value && URL.revokeObjectURL(preview.value));
</script>

<template>
    <Modal v-model:open="open" title="Profile photo">
        <div class="grid gap-5">
            <div class="grid justify-items-center gap-2">
                <Avatar :src="preview ?? current" :name="name" size="xl" ring />
                <p class="text-xs text-ink-muted">{{ preview ? 'Preview' : hasOverride ? 'Your uploaded photo' : 'Your Google photo' }}</p>
            </div>

            <CameraCapture v-if="mode === 'camera'" @capture="setFile" />

            <template v-else>
                <div class="grid grid-cols-2 gap-2">
                    <label class="flex cursor-pointer items-center justify-center gap-2 rounded-control border border-line py-3 text-sm font-medium hover:bg-surface-muted">
                        <Icon name="grid" :size="18" /> Upload
                        <input type="file" accept="image/jpeg,image/png,image/webp" class="sr-only" @change="setFile(($event.target as HTMLInputElement).files?.[0])" />
                    </label>
                    <button type="button" class="flex items-center justify-center gap-2 rounded-control border border-line py-3 text-sm font-medium hover:bg-surface-muted" @click="mode = 'camera'">
                        <Icon name="camera" :size="18" /> Take photo
                    </button>
                </div>
                <Button v-if="file" :loading="saving" @click="submit({ photo: file })">Save new photo</Button>
                <Button v-if="hasOverride && !file" variant="ghost" :loading="saving" @click="submit({ remove_photo: 1 })">Use my Google photo</Button>
            </template>
        </div>
    </Modal>
</template>
