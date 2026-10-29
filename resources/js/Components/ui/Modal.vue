<script setup lang="ts">
import { ref, watch } from 'vue';

defineProps<{ title?: string }>();
const open = defineModel<boolean>('open', { default: false });
const dialog = ref<HTMLDialogElement | null>(null);

watch(open, (value) => (value ? dialog.value?.showModal() : dialog.value?.close()), { flush: 'post' });
</script>

<template>
    <dialog
        ref="dialog"
        class="m-auto w-[min(32rem,calc(100vw-2rem))] rounded-card bg-surface p-0 shadow-pop backdrop:bg-ink/50"
        @close="open = false"
        @click.self="open = false"
    >
        <div class="p-5">
            <header v-if="title" class="mb-4 flex items-center justify-between">
                <h2 class="text-lg font-semibold">{{ title }}</h2>
                <button type="button" class="text-ink-muted hover:text-ink" aria-label="Close" @click="open = false">✕</button>
            </header>
            <slot />
        </div>
    </dialog>
</template>
