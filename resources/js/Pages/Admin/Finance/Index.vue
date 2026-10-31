<script setup lang="ts">
import Badge from "@/Components/ui/Badge.vue";
import Icon from "@/Components/ui/Icon.vue";
import Table from "@/Components/ui/Table.vue";
import PortalLayout from "@/Layouts/PortalLayout.vue";
import { dateTime, rm } from "@/lib/format";
import { searchTokens } from "@/lib/search";
import { Head, Link, router } from "@inertiajs/vue3";
import { watchDebounced } from "@vueuse/core";
import { computed, ref } from "vue";

interface Transaction {
    key: string;
    order_id: string | null;
    kind: string;
    club: string;
    club_slug: string;
    purpose: string;
    name: string | null;
    student_number: string | null;
    amount_sen: number;
    direction: "in" | "out";
    method: string | null;
    status: string;
    reference: string | null;
    at: string;
    receipt_url: string | null;
}

const props = defineProps<{
    q: string;
    transactions: Transaction[];
    clubs: {
        slug: string;
        name: string;
        ready: boolean;
        balance_sen: number;
        last_entry: string | null;
    }[];
}>();

const columns = [
    { key: "name" as const, label: "Club", sortable: true },
    { key: "ready" as const, label: "Ledger" },
    { key: "balance_sen" as const, label: "Balance", sortable: true },
    { key: "last_entry" as const, label: "Last entry", sortable: true },
];

// Searches as you type: no Enter, no button.
const q = ref(props.q);
const searching = ref(false);
watchDebounced(
    q,
    (value) =>
        router.get("/admin/finance", value.trim() ? { q: value.trim() } : {}, {
            only: ["q", "transactions"],
            preserveState: true,
            preserveScroll: true,
            replace: true,
            onStart: () => (searching.value = true),
            onFinish: () => (searching.value = false),
        }),
    { debounce: 300 },
);

const tokens = computed(() => searchTokens(props.q));
/** Splits an ID into its parts and marks the ones the search matched. */
const parts = (id: string): { text: string; hit: boolean }[] =>
    id
        .split("_")
        .map((text) => ({
            text,
            hit: tokens.value.some((t) => text.replace(/-/g, "").includes(t)),
        }));
const tone = (
    s: string,
): "success" | "warning" | "danger" | "info" | "neutral" =>
    ["confirmed", "auto_confirmed", "recorded"].includes(s)
        ? "success"
        : s === "rejected"
          ? "danger"
          : s === "pending_review" || s === "reading"
            ? "info"
            : "warning";
</script>

<template>
    <Head title="Club finance" />
    <PortalLayout title="Club finance">
        <section class="grid gap-3">
            <div
                class="flex items-center gap-3 rounded-card border border-line bg-surface px-4 shadow-card focus-within:border-brand-cyan focus-within:ring-2 focus-within:ring-brand-cyan/30"
            >
                <Icon name="search" :size="20" class="text-ink-muted" />
                <input
                    v-model="q"
                    type="search"
                    class="h-12 flex-1 bg-transparent text-[15px] outline-none"
                    placeholder="Find a transaction: any part of the ID, e.g. 2026-11-01 14:30 jinzhou"
                    aria-label="Find a transaction"
                />
                <span
                    v-if="searching"
                    class="size-4 animate-spin rounded-full border-2 border-brand-cyan border-t-transparent"
                    aria-hidden="true"
                />
            </div>
            <p class="text-xs text-ink-muted">
                IDs read
                <span class="font-mono"
                    >CLUB_EVENT or MEMBERSHIPDRIVE /
                    NORMALREGISTER_STUDENTID_NAME_YYYYMMDD_HHMM_TRANSACTION</span
                >. Type any parts in any order: club, event, student ID, name,
                date (2026-11-01 or 1/11/2026), time (14:30) or bank reference.
            </p>

            <template v-if="props.q">
                <p class="text-sm text-ink-muted">
                    {{ transactions.length }}
                    {{ transactions.length === 1 ? "match" : "matches"
                    }}{{
                        transactions.length >= 50
                            ? " (first 50, add more words to narrow)"
                            : ""
                    }}
                </p>
                <TransitionGroup tag="ul" name="list" class="grid gap-2">
                    <li
                        v-for="t in transactions"
                        :key="t.key"
                        class="grid gap-2 rounded-card border border-line bg-surface p-3 shadow-card sm:grid-cols-[1fr_auto] sm:items-center"
                    >
                        <div class="grid min-w-0 gap-1">
                            <p
                                v-if="t.order_id"
                                class="flex flex-wrap gap-x-0.5 font-mono text-xs leading-5"
                            >
                                <template
                                    v-for="(part, i) in parts(t.order_id)"
                                    :key="i"
                                >
                                    <span v-if="i" class="text-ink-muted"
                                        >_</span
                                    >
                                    <mark
                                        v-if="part.hit"
                                        class="rounded bg-brand-cyan/20 px-0.5 text-ink"
                                        >{{ part.text }}</mark
                                    >
                                    <span v-else>{{ part.text }}</span>
                                </template>
                            </p>
                            <p class="text-sm">
                                <strong>{{ t.name ?? t.purpose }}</strong>
                                <span class="text-ink-muted">
                                    · {{ t.club
                                    }}<template v-if="t.name">
                                        · {{ t.purpose }}</template
                                    >
                                    ·
                                    {{
                                        t.at.length > 10 ? dateTime(t.at) : t.at
                                    }}</span
                                >
                            </p>
                        </div>
                        <div
                            class="flex flex-wrap items-center gap-2 sm:justify-end"
                        >
                            <span
                                class="font-semibold tabular-nums"
                                :class="
                                    t.direction === 'out' ? 'text-danger' : ''
                                "
                                >{{ t.direction === "out" ? "−" : ""
                                }}{{ rm(t.amount_sen) }}</span
                            >
                            <Badge v-if="t.method" tone="neutral">{{
                                t.method
                            }}</Badge>
                            <Badge :tone="tone(t.status)">{{
                                t.status.replace("_", " ")
                            }}</Badge>
                            <a
                                v-if="t.receipt_url"
                                :href="t.receipt_url"
                                target="_blank"
                                rel="noopener"
                                class="text-sm font-medium text-brand-blue"
                                >Receipt</a
                            >
                            <Link
                                :href="`/clubs/${t.club_slug}/finance`"
                                class="text-sm font-medium text-brand-blue"
                                >Ledger</Link
                            >
                        </div>
                    </li>
                </TransitionGroup>
                <p
                    v-if="!transactions.length"
                    class="rounded-card border border-dashed border-line p-6 text-center text-sm text-ink-muted"
                >
                    No transaction matches every word.
                </p>
            </template>
        </section>

        <section class="mt-8 grid gap-3">
            <h2 class="text-lg font-semibold">All clubs · balances</h2>
            <Table :columns="columns" :rows="clubs" row-key="slug">
                <template #cell-name="{ row }"
                    ><Link
                        :href="`/clubs/${row.slug}/finance`"
                        class="font-medium hover:underline"
                        >{{ row.name }}</Link
                    ></template
                >
                <template #cell-ready="{ row }"
                    ><Badge :tone="row.ready ? 'success' : 'warning'">{{
                        row.ready ? "Open" : "Needs opening balance"
                    }}</Badge></template
                >
                <template #cell-balance_sen="{ row }"
                    ><span
                        class="tabular-nums"
                        :class="row.balance_sen < 0 ? 'text-danger' : ''"
                        >{{ rm(row.balance_sen) }}</span
                    ></template
                >
            </Table>
        </section>
    </PortalLayout>
</template>
