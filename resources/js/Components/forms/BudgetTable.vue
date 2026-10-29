<script setup lang="ts">
import { computed } from 'vue';

export interface BudgetItem {
    description: string;
    price_sen: number;
    quantity: number;
}

// Money is stored in sen (integer) and shown as RM with 2 decimals.
defineProps<{ disabled?: boolean; errors?: Record<string, string> }>();
const budget = defineModel<{ revenue: BudgetItem[]; expenses: BudgetItem[] }>({ required: true });

const rm = (sen: number): string => (sen / 100).toLocaleString('en-MY', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
const total = (items: BudgetItem[]): number => items.reduce((sum, i) => sum + i.price_sen * i.quantity, 0);
const revenue = computed(() => total(budget.value.revenue));
const expenses = computed(() => total(budget.value.expenses));
const profit = computed(() => revenue.value - expenses.value);

function setPrice(item: BudgetItem, value: string): void {
    item.price_sen = Math.round(Number(value || 0) * 100);
}

const sections = [
    { key: 'revenue' as const, label: 'Revenue' },
    { key: 'expenses' as const, label: 'Expenses' },
];

function add(key: 'revenue' | 'expenses'): void {
    budget.value[key].push({ description: '', price_sen: 0, quantity: 1 });
}

function remove(key: 'revenue' | 'expenses', index: number): void {
    budget.value[key].splice(index, 1);
}

</script>

<template>
    <div class="grid gap-4">
        <div v-for="section in sections" :key="section.key" class="overflow-x-auto rounded-control border border-line">
            <table class="w-full text-sm">
                <thead class="bg-surface-muted text-left text-xs uppercase text-ink-muted">
                    <tr>
                        <th class="px-3 py-2" colspan="5">{{ section.label }}</th>
                    </tr>
                    <tr>
                        <th class="w-10 px-3 py-2">No</th>
                        <th class="px-3 py-2">Description of items</th>
                        <th class="w-32 px-3 py-2">Price (RM)</th>
                        <th class="w-24 px-3 py-2">Quantity</th>
                        <th class="w-32 px-3 py-2 text-right">Total (RM)</th>
                        <th v-if="!disabled" class="w-8" />
                    </tr>
                </thead>
                <tbody>
                    <tr v-for="(item, index) in budget[section.key]" :key="index" class="border-t border-line">
                        <td class="px-3 py-2 text-ink-muted">{{ index + 1 }}</td>
                        <td class="px-2 py-1.5">
                            <input v-model="item.description" :disabled="disabled" class="h-9 w-full rounded-control border border-line px-2 disabled:bg-surface-muted" :aria-label="`${section.label} item ${index + 1}`" />
                            <p v-if="errors?.[`budget.${section.key}.${index}.description`]" class="text-xs text-danger">{{ errors[`budget.${section.key}.${index}.description`] }}</p>
                        </td>
                        <td class="px-2 py-1.5">
                            <input
                                :value="(item.price_sen / 100).toFixed(2)"
                                type="number"
                                min="0"
                                step="0.01"
                                :disabled="disabled"
                                class="h-9 w-full rounded-control border border-line px-2 disabled:bg-surface-muted"
                                :aria-label="`${section.label} price ${index + 1}`"
                                @change="setPrice(item, ($event.target as HTMLInputElement).value)"
                            />
                        </td>
                        <td class="px-2 py-1.5">
                            <input v-model.number="item.quantity" type="number" min="0" :disabled="disabled" class="h-9 w-full rounded-control border border-line px-2 disabled:bg-surface-muted" :aria-label="`${section.label} quantity ${index + 1}`" />
                        </td>
                        <td class="px-3 py-2 text-right tabular-nums">{{ rm(item.price_sen * item.quantity) }}</td>
                        <td v-if="!disabled" class="px-2"><button type="button" class="text-ink-muted hover:text-danger" aria-label="Remove" @click="remove(section.key, index)">✕</button></td>
                    </tr>
                    <tr class="border-t border-line font-semibold">
                        <td colspan="4" class="px-3 py-2">
                            Total
                            <button v-if="!disabled" type="button" class="ml-3 text-xs font-medium text-brand-blue" @click="add(section.key)">＋ Add item</button>
                        </td>
                        <td class="px-3 py-2 text-right tabular-nums">{{ rm(total(budget[section.key])) }}</td>
                        <td v-if="!disabled" />
                    </tr>
                </tbody>
            </table>
        </div>
        <div class="flex items-center justify-between rounded-control px-4 py-3 text-sm font-semibold" :class="profit < 0 ? 'bg-danger/10 text-danger' : 'bg-success/10 text-success'">
            <span>Profit / Loss</span>
            <span class="tabular-nums">RM {{ rm(profit) }}</span>
        </div>
        <p v-if="profit < 0" class="text-sm text-danger" role="alert">Remark: Revenue MUST be able to cover the expenses of the event.</p>
    </div>
</template>
