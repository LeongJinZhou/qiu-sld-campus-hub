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
                        <button v-if="column.sortable" type="button" class="inline-flex items-center gap-1 hover:text-ink" @click="sortBy(column.key)">
                            {{ column.label }}
                            <span aria-hidden="true">{{ sortKey === column.key ? (direction === 1 ? '▲' : '▼') : '↕' }}</span>
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
