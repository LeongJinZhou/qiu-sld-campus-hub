<script setup lang="ts">
import Avatar from '@/Components/ui/Avatar.vue';
import Button from '@/Components/ui/Button.vue';
import CameraCapture from '@/Components/ui/CameraCapture.vue';
import Icon from '@/Components/ui/Icon.vue';
import ImageAdjuster from '@/Components/ui/ImageAdjuster.vue';
import Modal from '@/Components/ui/Modal.vue';
import { router } from '@inertiajs/vue3';
import { computed, ref } from 'vue';

/**
 * Profile photo or cover picture: upload, take one with the camera (photo only) or re-adjust the current one,
 * then drag and zoom it into the frame before saving. "Remove" goes back to the Google photo / default cover.
 */
const props = withDefaults(defineProps<{ kind?: 'photo' | 'cover'; current: string | null; name: string; hasOverride: boolean }>(), { kind: 'photo' });
const open = defineModel<boolean>('open', { default: false });

const isCover = computed(() => props.kind === 'cover');
const mode = ref<'choose' | 'camera' | 'adjust'>('choose');
const source = ref<Blob | null>(null);
const adjuster = ref<InstanceType<typeof ImageAdjuster> | null>(null);
const saving = ref(false);
const error = ref('');

function pick(f: File | Blob | null | undefined): void {
    if (!f) return;
    source.value = f;
    error.value = '';
    mode.value = 'adjust';
}

// Re-adjust the photo already saved (only our own uploads: other sites' images cannot be read back).
async function adjustCurrent(): Promise<void> {
    if (!props.current) return;
    const response = await fetch(props.current).catch(() => null);
    if (response?.ok) pick(await response.blob());
    else error.value = 'Could not load the current picture. Upload it again instead.';
}

function send(data: Record<string, File | number>): void {
    saving.value = true;
    router.post('/me', data, {
        forceFormData: true,
        preserveScroll: true,
        onFinish: () => (saving.value = false),
        onSuccess: () => {
            open.value = false;
            mode.value = 'choose';
            source.value = null;
        },
    });
}

async function save(): Promise<void> {
    const blob = await adjuster.value?.exportBlob();
    if (blob) send({ [props.kind]: new File([blob], `${props.kind}.jpg`, { type: 'image/jpeg' }) });
}
</script>

<template>
    <Modal v-model:open="open" :title="isCover ? 'Cover picture' : 'Profile photo'">
        <div class="grid gap-5">
            <template v-if="mode === 'adjust' && source">
                <ImageAdjuster ref="adjuster" :file="source" :aspect="isCover ? 3 : 1" :round="!isCover" :output-width="isCover ? 1500 : 640" />
                <div class="grid grid-cols-2 gap-2">
                    <Button variant="secondary" @click="mode = 'choose'">Back</Button>
                    <Button :loading="saving" @click="save">Save</Button>
                </div>
            </template>

            <template v-else>
                <div class="grid justify-items-center gap-2">
                    <div v-if="isCover" class="aspect-[3/1] w-full overflow-hidden rounded-card bg-gradient-to-r from-brand-yellow via-brand-orange to-brand-magenta">
                        <img v-if="current" :src="current" alt="Current cover" class="size-full object-cover" />
                    </div>
                    <Avatar v-else :src="current" :name="name" size="xl" ring />
                    <p class="text-xs text-ink-muted">{{ hasOverride ? 'Your current picture' : isCover ? 'Default cover' : 'Your Google photo' }}</p>
                </div>

                <CameraCapture v-if="mode === 'camera'" @capture="pick" />

                <div v-else class="grid gap-2" :class="isCover ? 'grid-cols-1' : 'grid-cols-2'">
                    <label class="flex cursor-pointer items-center justify-center gap-2 rounded-control border border-line py-3 text-sm font-medium hover:bg-surface-muted">
                        <Icon name="grid" :size="18" /> Upload
                        <input type="file" accept="image/jpeg,image/png,image/webp" class="sr-only" @change="pick(($event.target as HTMLInputElement).files?.[0])" />
                    </label>
                    <button v-if="!isCover" type="button" class="flex items-center justify-center gap-2 rounded-control border border-line py-3 text-sm font-medium hover:bg-surface-muted" @click="mode = 'camera'">
                        <Icon name="camera" :size="18" /> Take photo
                    </button>
                </div>
                <Button v-if="hasOverride && mode === 'choose'" variant="secondary" @click="adjustCurrent"><Icon name="edit" :size="16" /> Adjust current picture</Button>
                <p v-if="error" class="text-sm text-danger">{{ error }}</p>
                <Button v-if="hasOverride && mode === 'choose'" variant="ghost" :loading="saving" @click="send({ [`remove_${kind}`]: 1 })">
                    {{ isCover ? 'Use the default cover' : 'Use my Google photo' }}
                </Button>
            </template>
        </div>
    </Modal>
</template>
