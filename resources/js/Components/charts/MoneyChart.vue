<script setup lang="ts">
import { rm } from '@/lib/format';
import { computed, ref } from 'vue';

/**
 * Income and expenses side by side per period (month or year): two series, one axis (RM), a legend, a hover
 * tooltip on every bar and a table view. Colours are the validated blue/orange pair, re-stepped for dark mode.
 */
const props = defineProps<{ title: string; rows: { key: string; label?: string; income_sen: number; expense_sen: number }[] }>();
const max = computed(() => Math.max(1, ...props.rows.flatMap((r) => [r.income_sen, r.expense_sen])));
const tip = ref<{ x: number; y: number; lines: string[] } | null>(null);
const show = (e: PointerEvent, r: (typeof props.rows)[number]): void => {
    tip.value = { x: e.clientX, y: e.clientY, lines: [r.label ?? r.key, `Income ${rm(r.income_sen)}`, `Expenses ${rm(r.expense_sen)}`, `Net ${rm(r.income_sen - r.expense_sen)}`] };
};
const table = ref(false);
const h = (sen: number): string => `${Math.max(sen ? 2 : 0, (sen / max.value) * 100)}%`;
</script>

<template>
    <section class="money-chart grid gap-3 rounded-card border border-line bg-surface p-4 shadow-card" @pointerleave="tip = null">
        <header class="flex flex-wrap items-center justify-between gap-2">
            <h3 class="font-semibold">{{ title }}</h3>
            <div class="flex items-center gap-3 text-xs text-ink-muted">
                <span class="flex items-center gap-1"><span class="size-2.5 rounded-sm bg-[var(--income)]" /> Income</span>
                <span class="flex items-center gap-1"><span class="size-2.5 rounded-sm bg-[var(--expense)]" /> Expenses</span>
                <button type="button" class="rounded-control border border-line px-2 py-1" :aria-pressed="table" @click="table = !table">{{ table ? 'Chart' : 'Table' }}</button>
            </div>
        </header>
        <table v-if="table" class="w-full text-sm">
            <thead class="text-left text-xs text-ink-muted">
                <tr>
                    <th class="py-1">Period</th>
                    <th class="text-right">Income</th>
                    <th class="text-right">Expenses</th>
                    <th class="text-right">Net</th>
                </tr>
            </thead>
            <tbody>
                <tr v-for="r in rows" :key="r.key" class="border-t border-line tabular-nums">
                    <td class="py-1.5">{{ r.label ?? r.key }}</td>
                    <td class="text-right">{{ rm(r.income_sen) }}</td>
                    <td class="text-right">{{ rm(r.expense_sen) }}</td>
                    <td class="text-right" :class="r.income_sen - r.expense_sen < 0 ? 'text-danger' : ''">{{ rm(r.income_sen - r.expense_sen) }}</td>
                </tr>
            </tbody>
        </table>
        <template v-else>
            <div class="flex h-44 items-end gap-1.5 border-b border-line" role="img" :aria-label="`${title}: income and expenses per period`">
                <div v-for="r in rows" :key="r.key" class="flex h-full flex-1 items-end justify-center gap-[2px]" @pointermove="show($event, r)">
                    <span class="w-[42%] rounded-t-[4px] bg-[var(--income)] transition-[height] duration-700" :style="{ height: h(r.income_sen) }" />
                    <span class="w-[42%] rounded-t-[4px] bg-[var(--expense)] transition-[height] duration-700" :style="{ height: h(r.expense_sen) }" />
                </div>
            </div>
            <div class="flex gap-1.5 text-center text-[10px] text-ink-muted">
                <span v-for="r in rows" :key="r.key" class="flex-1 truncate">{{ r.label ?? r.key }}</span>
            </div>
        </template>
        <div
            v-if="tip"
            class="pointer-events-none fixed z-50 grid gap-0.5 rounded-control border border-line bg-surface px-3 py-2 text-xs shadow-pop"
            :style="{ left: `${tip.x + 12}px`, top: `${tip.y + 12}px` }"
            role="tooltip"
        >
            <strong>{{ tip.lines[0] }}</strong>
            <span v-for="l in tip.lines.slice(1)" :key="l" class="text-ink-muted">{{ l }}</span>
        </div>
    </section>
</template>

<style scoped>
.money-chart {
    --income: #2a78d6;
    --expense: #eb6834;
}
:root[data-theme='dark'] .money-chart {
    --income: #3987e5;
    --expense: #d95926;
}
@media (prefers-color-scheme: dark) {
    :root[data-theme='system'] .money-chart {
        --income: #3987e5;
        --expense: #d95926;
    }
}
</style>
