<script setup lang="ts">
import { ref, watch } from 'vue';

withDefaults(defineProps<{ title?: string; side?: 'left' | 'right' | 'bottom' }>(), { side: 'right' });
const open = defineModel<boolean>('open', { default: false });
const dialog = ref<HTMLDialogElement | null>(null);

watch(open, (value) => (value ? dialog.value?.showModal() : dialog.value?.close()), { flush: 'post' });

const positions = {
    left: 'mr-auto h-full max-h-none w-[min(22rem,90vw)]',
    right: 'ml-auto h-full max-h-none w-[min(22rem,90vw)]',
    bottom: 'mt-auto w-full max-w-none rounded-t-card',
};
</script>

<template>
    <dialog ref="dialog" class="m-0 bg-surface p-0 shadow-pop backdrop:bg-ink/50" :class="positions[side]" @close="open = false" @click.self="open = false">
        <div class="p-5">
            <header class="mb-4 flex items-center justify-between">
                <h2 class="text-lg font-semibold">{{ title }}</h2>
                <button type="button" class="text-ink-muted hover:text-ink" aria-label="Close" @click="open = false">✕</button>
            </header>
            <slot />
        </div>
    </dialog>
</template>
