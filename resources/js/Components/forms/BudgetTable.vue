<script setup lang="ts">
import { computed, reactive } from "vue";

export interface BudgetItem {
    description: string;
    price_sen: number;
    quantity: number;
}

/**
 * Revenue and expenses, money kept in sen. Prices are typed like on a calculator ("12.5" → RM 12.50 when you
 * leave the box), never negative; quantities are whole numbers from 1. Revenue must at least cover expenses.
 */
defineProps<{ disabled?: boolean; errors?: Record<string, string> }>();
const budget = defineModel<{ revenue: BudgetItem[]; expenses: BudgetItem[] }>({
    required: true,
});

const rm = (sen: number): string =>
    (sen / 100).toLocaleString("en-MY", {
        minimumFractionDigits: 2,
        maximumFractionDigits: 2,
    });
const total = (items: BudgetItem[]): number =>
    items.reduce((sum, i) => sum + i.price_sen * i.quantity, 0);
const revenue = computed(() => total(budget.value.revenue));
const expenses = computed(() => total(budget.value.expenses));
const profit = computed(() => revenue.value - expenses.value);

// What the person is typing in a price box, kept as text until they leave it.
const typing = reactive<Record<string, string>>({});
const priceText = (key: string, item: BudgetItem): string =>
    typing[key] ?? (item.price_sen ? rm(item.price_sen).replace(/,/g, "") : "");
function typePrice(key: string, item: BudgetItem, raw: string): void {
    // Digits and one dot, at most 2 decimals; no minus sign.
    const clean = raw
        .replace(/[^\d.]/g, "")
        .replace(/(\..*)\./g, "$1")
        .replace(/^(\d*\.\d{0,2}).*$/, "$1");
    typing[key] = clean;
    item.price_sen = Math.round(Number(clean || 0) * 100);
}
const leavePrice = (key: string): void => void delete typing[key];
function typeQuantity(item: BudgetItem, raw: string): void {
    item.quantity = Math.max(
        1,
        Math.min(100000, Number.parseInt(raw.replace(/\D/g, "") || "1", 10)),
    );
}
const blockKeys = (e: KeyboardEvent): void => {
    if (["-", "+", "e", "E", ".", ","].includes(e.key)) e.preventDefault();
};

const sections = [
    {
        key: "revenue" as const,
        label: "Revenue",
        hint: "Ticket sales, sponsorship, club fund…",
    },
    {
        key: "expenses" as const,
        label: "Expenses",
        hint: "Everything you plan to buy or pay for",
    },
];
const add = (key: "revenue" | "expenses"): void =>
    void budget.value[key].push({ description: "", price_sen: 0, quantity: 1 });
const remove = (key: "revenue" | "expenses", index: number): void =>
    void budget.value[key].splice(index, 1);
</script>

<template>
    <div class="grid gap-4">
        <div v-for="section in sections" :key="section.key" class="grid gap-2">
            <div class="flex items-baseline justify-between gap-2">
                <h3 class="font-semibold">
                    {{ section.label }}
                    <span class="text-xs font-normal text-ink-muted">{{
                        section.hint
                    }}</span>
                </h3>
                <span class="text-sm font-semibold tabular-nums"
                    >RM {{ rm(total(budget[section.key])) }}</span
                >
            </div>
            <TransitionGroup tag="ul" name="list" class="relative grid gap-2">
                <li
                    v-for="(item, index) in budget[section.key]"
                    :key="index"
                    class="grid grid-cols-[1.5rem_1fr] gap-x-2 gap-y-2 rounded-control border border-line p-2 sm:grid-cols-[1.5rem_1fr_8rem_6rem_7rem_1.5rem] sm:items-center"
                >
                    <span class="pt-2 text-xs text-ink-muted sm:pt-0">{{
                        index + 1
                    }}</span>
                    <div>
                        <input
                            v-model="item.description"
                            :disabled="disabled"
                            placeholder="Item description"
                            class="h-10 w-full rounded-control border border-line px-3 text-sm outline-none transition focus:border-brand-cyan focus:ring-2 focus:ring-brand-cyan/30 disabled:bg-surface-muted"
                            :aria-label="`${section.label} item ${index + 1}`"
                        />
                        <p
                            v-if="
                                errors?.[
                                    `budget.${section.key}.${index}.description`
                                ]
                            "
                            class="text-xs text-danger"
                        >
                            {{
                                errors[
                                    `budget.${section.key}.${index}.description`
                                ]
                            }}
                        </p>
                    </div>
                    <div
                        class="col-start-2 grid grid-cols-[1fr_5.5rem] gap-2 sm:col-start-auto sm:contents"
                    >
                        <label class="relative block">
                            <span
                                class="pointer-events-none absolute left-3 top-1/2 -translate-y-1/2 text-xs text-ink-muted"
                                >RM</span
                            >
                            <input
                                :value="
                                    priceText(`${section.key}${index}`, item)
                                "
                                inputmode="decimal"
                                placeholder="0.00"
                                :disabled="disabled"
                                class="h-10 w-full rounded-control border border-line pl-9 pr-2 text-right text-sm tabular-nums outline-none transition focus:border-brand-cyan focus:ring-2 focus:ring-brand-cyan/30 disabled:bg-surface-muted"
                                :aria-label="`${section.label} price ${index + 1} in RM`"
                                @input="
                                    typePrice(
                                        `${section.key}${index}`,
                                        item,
                                        ($event.target as HTMLInputElement)
                                            .value,
                                    )
                                "
                                @blur="leavePrice(`${section.key}${index}`)"
                                @focus="
                                    ($event.target as HTMLInputElement).select()
                                "
                            />
                        </label>
                        <label class="relative block">
                            <span
                                class="pointer-events-none absolute left-2.5 top-1/2 -translate-y-1/2 text-xs text-ink-muted"
                                >×</span
                            >
                            <input
                                :value="item.quantity"
                                type="number"
                                inputmode="numeric"
                                min="1"
                                step="1"
                                :disabled="disabled"
                                class="h-10 w-full rounded-control border border-line pl-6 pr-2 text-right text-sm tabular-nums outline-none transition focus:border-brand-cyan focus:ring-2 focus:ring-brand-cyan/30 disabled:bg-surface-muted"
                                :aria-label="`${section.label} quantity ${index + 1}`"
                                @keydown="blockKeys"
                                @input="
                                    typeQuantity(
                                        item,
                                        ($event.target as HTMLInputElement)
                                            .value,
                                    )
                                "
                            />
                        </label>
                    </div>
                    <span
                        class="col-start-2 text-right text-sm font-semibold tabular-nums sm:col-start-auto"
                        >RM {{ rm(item.price_sen * item.quantity) }}</span
                    >
                    <button
                        v-if="!disabled"
                        type="button"
                        class="col-start-2 justify-self-end text-ink-muted hover:text-danger sm:col-start-auto"
                        :aria-label="`Remove ${section.label} item ${index + 1}`"
                        @click="remove(section.key, index)"
                    >
                        ✕
                    </button>
                    <p
                        v-if="
                            errors?.[
                                `budget.${section.key}.${index}.quantity`
                            ] ||
                            errors?.[`budget.${section.key}.${index}.price_sen`]
                        "
                        class="col-span-full text-xs text-danger"
                    >
                        {{
                            errors[`budget.${section.key}.${index}.quantity`] ??
                            errors[`budget.${section.key}.${index}.price_sen`]
                        }}
                    </p>
                </li>
            </TransitionGroup>
            <button
                v-if="!disabled"
                type="button"
                class="justify-self-start rounded-control border border-dashed border-line px-3 py-2 text-sm font-medium text-brand-blue transition hover:border-brand-cyan"
                @click="add(section.key)"
            >
                ＋ Add {{ section.key === "revenue" ? "revenue" : "expense" }}
            </button>
        </div>
        <div
            class="flex items-center justify-between rounded-control px-4 py-3 text-sm font-semibold transition-colors"
            :class="
                profit < 0
                    ? 'bg-danger/10 text-danger'
                    : 'bg-success/10 text-success'
            "
        >
            <span>{{
                profit < 0 ? "Loss" : profit === 0 ? "Break even" : "Profit"
            }}</span>
            <span class="tabular-nums">RM {{ rm(profit) }}</span>
        </div>
        <p v-if="profit < 0" class="text-sm text-danger" role="alert">
            Revenue must at least cover expenses (break even). You cannot submit
            until it does: add RM {{ rm(-profit) }} of revenue or cut costs.
        </p>
        <p v-if="errors?.budget" class="text-sm text-danger">
            {{ errors.budget }}
        </p>
    </div>
</template>
