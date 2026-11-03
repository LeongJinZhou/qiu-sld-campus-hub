<script setup lang="ts" generic="Row extends Record<string, string>">
// Repeating rows (activities, itinerary, objectives) with add/remove, matching the template tables.
const props = defineProps<{
    columns: { key: keyof Row & string; label: string; type?: 'text' | 'time' | 'textarea' }[];
    blank: Row;
    disabled?: boolean;
    errors?: Record<string, string>;
    errorPrefix: string;
    addLabel?: string;
    /** Rows that always stay (e.g. 2 objectives): their remove button is hidden. */
    min?: number;
    /** Columns that may be left empty, shown as "(optional)". */
    optional?: string[];
}>();
const rows = defineModel<Row[]>({ required: true });

const add = (): void => {
    rows.value = [...rows.value, { ...props.blank }];
};
const remove = (index: number): void => {
    rows.value = rows.value.filter((_, i) => i !== index);
};
</script>

<template>
    <div class="grid gap-2">
        <div class="overflow-x-auto rounded-control border border-line">
            <table class="w-full text-sm">
                <thead class="bg-surface-muted text-left text-xs uppercase text-ink-muted">
                    <tr>
                        <th class="w-10 px-3 py-2">No</th>
                        <th v-for="column in columns" :key="column.key" class="px-3 py-2">
                            {{ column.label }}<span v-if="optional?.includes(column.key)" class="font-normal normal-case"> (optional)</span>
                        </th>
                        <th v-if="!disabled" class="w-10" />
                    </tr>
                </thead>
                <tbody>
                    <tr v-for="(row, index) in rows" :key="index" class="border-t border-line align-top">
                        <td class="px-3 py-2 text-ink-muted">{{ index + 1 }}</td>
                        <td v-for="column in columns" :key="column.key" class="px-2 py-1.5">
                            <textarea
                                v-if="column.type === 'textarea'"
                                v-model="row[column.key]"
                                :disabled="disabled"
                                rows="2"
                                :aria-label="`${column.label} ${index + 1}`"
                                class="w-full rounded-control border border-line px-2 py-1.5 disabled:bg-surface-muted"
                            />
                            <input
                                v-else
                                v-model="row[column.key]"
                                :type="column.type ?? 'text'"
                                :disabled="disabled"
                                :aria-label="`${column.label} ${index + 1}`"
                                class="h-9 w-full rounded-control border border-line px-2 disabled:bg-surface-muted"
                            />
                            <p v-if="errors?.[`${errorPrefix}.${index}.${column.key}`]" class="mt-1 text-xs text-danger">
                                {{ errors[`${errorPrefix}.${index}.${column.key}`] }}
                            </p>
                        </td>
                        <td v-if="!disabled" class="px-2 py-2">
                            <button v-if="rows.length > (min ?? 0)" type="button" class="text-ink-muted hover:text-danger" :aria-label="`Remove row ${index + 1}`" @click="remove(index)">✕</button>
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>
        <button v-if="!disabled" type="button" class="justify-self-start text-sm font-medium text-brand-blue" @click="add">＋ {{ addLabel ?? 'Add row' }}</button>
    </div>
</template>
