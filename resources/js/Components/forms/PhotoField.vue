<script setup lang="ts">
import Icon from '@/Components/ui/Icon.vue';
import { computed, ref } from 'vue';

/**
 * One picture on a form: pick a file, it uploads straight away (cleaned on the server) and the field keeps the
 * stored path. Used for speaker photos (portrait) and pictures in a proposal's added parts (wide).
 */
const props = defineProps<{
    uploadUrl: string;
    imageBase: string;
    label: string;
    required?: boolean;
    readonly?: boolean;
    error?: string;
    shape?: 'portrait' | 'wide';
}>();
const path = defineModel<string>({ required: true });

const busy = ref(false);
const failed = ref<string | null>(null);
const url = computed(() => (path.value ? props.imageBase.replace(/\/?$/, '/') + path.value : null));
const xsrf = (): string => decodeURIComponent(document.cookie.match(/XSRF-TOKEN=([^;]+)/)?.[1] ?? '');

async function pick(e: Event): Promise<void> {
    const input = e.target as HTMLInputElement;
    const file = input.files?.[0];
    input.value = '';
    if (!file) return;
    busy.value = true;
    failed.value = null;
    const body = new FormData();
    body.append('image', file);
    try {
        const res = await fetch(props.uploadUrl, {
            method: 'POST',
            credentials: 'same-origin',
            headers: { Accept: 'application/json', 'X-XSRF-TOKEN': xsrf(), 'X-Requested-With': 'XMLHttpRequest' },
            body,
        });
        const json = (await res.json().catch(() => ({}))) as { path?: string; message?: string; errors?: Record<string, string[]> };
        if (!res.ok || !json.path) {
            failed.value = (json.errors && Object.values(json.errors)[0]?.[0]) ?? json.message ?? 'Upload failed. Try another picture (JPG or PNG, up to 8 MB).';
            return;
        }
        path.value = json.path;
    } catch {
        failed.value = 'Upload failed. Check your connection and try again.';
    } finally {
        busy.value = false;
    }
}
</script>

<template>
    <div class="grid gap-1.5">
        <span class="text-sm font-medium">{{ label }}<span v-if="required" class="text-danger"> *</span></span>
        <div class="flex flex-wrap items-center gap-3">
            <div
                class="grid shrink-0 place-items-center overflow-hidden rounded-control border bg-surface-muted"
                :class="[shape === 'wide' ? 'h-40 w-full max-w-md' : 'h-28 w-22', error && !path ? 'border-danger' : 'border-line']"
            >
                <img v-if="url" :src="url" :alt="label" :class="shape === 'wide' ? 'max-h-full max-w-full object-contain' : 'size-full object-cover'" />
                <Icon v-else :name="shape === 'wide' ? 'camera' : 'user'" :size="28" class="text-ink-muted" />
            </div>
            <label v-if="!readonly" class="inline-flex cursor-pointer items-center gap-1.5 rounded-control border border-line bg-surface px-3 py-2 text-sm font-medium hover:bg-surface-muted">
                <Icon name="camera" :size="16" />{{ busy ? 'Uploading…' : path ? 'Change picture' : 'Upload picture' }}
                <input type="file" accept="image/jpeg,image/png,image/webp" class="sr-only" :disabled="busy" @change="pick" />
            </label>
        </div>
        <p v-if="failed || error" class="text-xs text-danger">{{ failed ?? error }}</p>
    </div>
</template>
