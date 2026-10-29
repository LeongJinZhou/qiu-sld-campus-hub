<script setup lang="ts" generic="Row extends object">
import { computed, ref, shallowRef } from 'vue';

const props = defineProps<{
    columns: { key: keyof Row & string; label: string; sortable?: boolean }[];
    rows: Row[];
    rowKey: keyof Row & string;
}>();

const sortKey = shallowRef<(keyof Row & string) | null>(null);
const direction = ref<1 | -1>(1);

function sortBy(key: keyof Row & string): void {
    direction.value = sortKey.value === key ? (direction.value === 1 ? -1 : 1) : 1;
    sortKey.value = key;
}

const sorted = computed(() => {
    const key: (keyof Row & string) | null = sortKey.value;
    if (!key) {
        return props.rows;
    }
    const text = (row: Row): string => String(row[key] ?? '');
    return [...(props.rows as Row[])].sort((a, b) => text(a).localeCompare(text(b), undefined, { numeric: true }) * direction.value);
});
</script>

<template>
    <div class="overflow-x-auto rounded-card border border-line bg-surface">
        <table class="w-full text-left text-sm">
            <thead class="bg-surface-muted text-xs uppercase tracking-wide text-ink-muted">
                <tr>
                    <th v-for="column in columns" :key="column.key" class="px-4 py-3 font-medium" :aria-sort="sortKey === column.key ? (direction === 1 ? 'ascending' : 'descending') : undefined">
                        <!-- Buttons reset text-transform, so repeat the header style; SVG arrows (not ↕, which macOS draws as an emoji). -->
                        <button v-if="column.sortable" type="button" class="inline-flex items-center gap-1 font-medium uppercase tracking-wide hover:text-ink" :class="sortKey === column.key ? 'text-ink' : ''" @click="sortBy(column.key)">
                            {{ column.label }}
                            <svg width="10" height="12" viewBox="0 0 10 12" aria-hidden="true" class="shrink-0">
                                <path d="M5 1 9 5H1z" fill="currentColor" :opacity="sortKey !== column.key ? 0.45 : direction === 1 ? 1 : 0.25" />
                                <path d="M5 11 1 7h8z" fill="currentColor" :opacity="sortKey !== column.key ? 0.45 : direction === 1 ? 0.25 : 1" />
                            </svg>
                        </button>
                        <template v-else>{{ column.label }}</template>
                    </th>
                </tr>
            </thead>
            <tbody>
                <tr v-for="row in sorted" :key="String(row[rowKey])" class="border-t border-line">
                    <td v-for="column in columns" :key="column.key" class="px-4 py-3">
                        <slot :name="`cell-${column.key}`" :row="row">{{ row[column.key] }}</slot>
                    </td>
                </tr>
                <tr v-if="sorted.length === 0">
                    <td :colspan="columns.length" class="px-4 py-8 text-center text-ink-muted"><slot name="empty">Nothing here yet.</slot></td>
                </tr>
            </tbody>
        </table>
    </div>
</template>
