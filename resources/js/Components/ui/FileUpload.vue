<script setup lang="ts">
import { ref } from 'vue';

withDefaults(defineProps<{ label?: string; accept?: string; multiple?: boolean; error?: string }>(), { multiple: false });
const model = defineModel<File[]>({ default: () => [] });
const dragging = ref(false);

function add(files: FileList | null | undefined): void {
    if (files) {
        model.value = [...model.value, ...Array.from(files)];
    }
}

function remove(index: number): void {
    model.value = model.value.filter((_, i) => i !== index);
}
</script>

<template>
    <div class="grid gap-2">
        <span v-if="label" class="text-sm font-medium">{{ label }}</span>
        <label
            class="grid cursor-pointer place-items-center rounded-card border-2 border-dashed px-4 py-8 text-center text-sm transition"
            :class="dragging ? 'border-brand-cyan bg-brand-cyan/5' : 'border-line hover:bg-surface-muted'"
            @dragover.prevent="dragging = true"
            @dragleave="dragging = false"
            @drop.prevent="dragging = false; add($event.dataTransfer?.files)"
        >
            <span><strong>Choose file</strong> or drag it here</span>
            <input type="file" class="sr-only" :accept="accept" :multiple="multiple" @change="add(($event.target as HTMLInputElement).files)" />
        </label>
        <ul v-if="model.length" class="grid gap-1 text-sm">
            <li v-for="(file, index) in model" :key="`${file.name}-${index}`" class="flex items-center justify-between rounded-control bg-surface-muted px-3 py-2">
                <span class="truncate">{{ file.name }}</span>
                <button type="button" class="text-ink-muted hover:text-danger" :aria-label="`Remove ${file.name}`" @click="remove(index)">✕</button>
            </li>
        </ul>
        <p v-if="error" class="text-xs text-danger">{{ error }}</p>
    </div>
</template>
