<script setup lang="ts">
import Badge from "@/Components/ui/Badge.vue";
import Button from "@/Components/ui/Button.vue";
import Icon from "@/Components/ui/Icon.vue";
import Input from "@/Components/ui/Input.vue";
import LiveQrCapture from "@/Components/ui/LiveQrCapture.vue";
import Modal from "@/Components/ui/Modal.vue";
import Select from "@/Components/ui/Select.vue";
import AppLayout from "@/Layouts/AppLayout.vue";
import { rm } from "@/lib/format";
import { Head, Link, router, useForm } from "@inertiajs/vue3";
import { useIntervalFn } from "@vueuse/core";
import { computed, reactive, ref, watch } from "vue";

interface Item {
    name: string;
    quantity: number;
    unit_sen: number;
    total_sen?: number;
}
interface Purchase {
    id: number;
    status:
        | "reading"
        | "draft"
        | "pending"
        | "owed"
        | "paying"
        | "recorded"
        | "rejected";
    paid_with: "club" | "own";
    items: Item[];
    total_sen: number;
    shop: string | null;
    purchased_on: string | null;
    budget_line: string | null;
    ai_confidence: number | null;
    buyer: { id: number; name: string; qr_token: string | null };
    verifier: string | null;
    payer: string | null;
    review_note: string | null;
    settle_when: "now" | "after_event" | null;
    settle_method: "cash" | "online" | null;
    payout_check: string | null;
    payout_payee: string | null;
    mine: boolean;
    to_verify: boolean;
    receipt_url: string;
    payout_url: string | null;
    at: string | null;
}

const props = defineProps<{
    event: {
        slug: string;
        name: string;
        ended: boolean;
        club: { slug: string; name: string };
    };
    purchases: Purchase[];
    budget: { description: string; budget_sen: number; spent_sen: number }[];
    budgetLines: string[];
    verifiers: { id: number; name: string }[];
    can: { organise: boolean };
}>();

// Snap: the photo goes up at once; AI reads it in the background while this page checks back.
const snapping = ref(false);
function snap(e: Event): void {
    const file = (e.target as HTMLInputElement).files?.[0];
    if (!file) return;
    snapping.value = true;
    router.post(
        `/events/${props.event.slug}/purchases`,
        { receipt: file },
        {
            forceFormData: true,
            preserveScroll: true,
            onFinish: () => (snapping.value = false),
        },
    );
    (e.target as HTMLInputElement).value = "";
}
const busy = computed(() =>
    props.purchases.some(
        (p) => p.status === "reading" || p.payout_check === "reading",
    ),
);
const { pause, resume } = useIntervalFn(
    () => router.reload({ only: ["purchases", "budget"] }),
    2500,
    { immediate: false },
);
watch(busy, (b) => (b ? resume() : pause()), { immediate: true });

// Drafts the buyer is checking: items as read by AI, editable, total worked out as they type.
const drafts = reactive<
    Record<
        number,
        {
            items: { name: string; quantity: number; price_rm: string }[];
            shop: string;
            purchased_on: string;
            budget_line: string;
            paid_with: "club" | "own";
            verifier_id: number | null;
        }
    >
>({});
watch(
    () => props.purchases,
    (list) =>
        list.forEach((p) => {
            if (p.status === "draft" && p.mine && !drafts[p.id]) {
                drafts[p.id] = {
                    items: (p.items.length
                        ? p.items
                        : [{ name: "", quantity: 1, unit_sen: 0 }]
                    ).map((i) => ({
                        name: i.name,
                        quantity: i.quantity,
                        price_rm: (i.unit_sen / 100).toFixed(2),
                    })),
                    shop: p.shop ?? "",
                    purchased_on: p.purchased_on ?? "",
                    budget_line: p.budget_line ?? "",
                    paid_with: props.can.organise ? "club" : "own",
                    verifier_id: null,
                };
            }
        }),
    { immediate: true },
);
const draftTotal = (id: number): number =>
    (drafts[id]?.items ?? []).reduce(
        (sum, i) =>
            sum +
            Math.round(Number(i.price_rm || 0) * 100) * Number(i.quantity || 0),
        0,
    );
const submitting = ref<number | null>(null);
function submitDraft(id: number): void {
    const d = drafts[id];
    if (!d) return;
    submitting.value = id;
    router.put(
        `/purchases/${id}`,
        {
            items: d.items
                .filter((i) => i.name.trim())
                .map((i) => ({
                    name: i.name.trim(),
                    quantity: Number(i.quantity),
                    unit_sen: Math.round(Number(i.price_rm || 0) * 100),
                })),
            shop: d.shop || null,
            purchased_on: d.purchased_on || null,
            budget_line: d.budget_line || null,
            paid_with: d.paid_with,
            verifier_id: d.paid_with === "own" ? d.verifier_id : null,
        },
        { preserveScroll: true, onFinish: () => (submitting.value = null) },
    );
}

const verify = (p: Purchase, settle_when: "now" | "after_event"): void =>
    router.post(
        `/purchases/${p.id}/verify`,
        { approve: true, settle_when },
        { preserveScroll: true },
    );
function reject(p: Purchase): void {
    const note = prompt(
        "Why is this purchase not accepted? (sent to the buyer)",
    );
    if (note)
        router.post(
            `/purchases/${p.id}/verify`,
            { approve: false, note },
            { preserveScroll: true },
        );
}

// Pay back: cash (photo of the buyer's own QR with the cash) or transfer (upload the transfer receipt).
const paying = ref<Purchase | null>(null);
const payMethod = ref<"cash" | "online" | null>(null);
const payForm = useForm({
    photo: null as File | null,
    receipt: null as File | null,
});
function openPay(p: Purchase): void {
    paying.value = p;
    payMethod.value = null;
}
function onCashPhoto(blob: Blob): void {
    if (!paying.value) return;
    payForm.photo = new File([blob], "payback.jpg", { type: "image/jpeg" });
    payForm.post(`/purchases/${paying.value.id}/pay-cash`, {
        preserveScroll: true,
        forceFormData: true,
        onSuccess: () => (paying.value = null),
        onFinish: () => payForm.reset(),
    });
}
function onTransfer(e: Event): void {
    const file = (e.target as HTMLInputElement).files?.[0];
    if (!file || !paying.value) return;
    payForm.receipt = file;
    payForm.post(`/purchases/${paying.value.id}/pay-online`, {
        preserveScroll: true,
        forceFormData: true,
        onSuccess: () => (paying.value = null),
        onFinish: () => payForm.reset(),
    });
}
const post = (p: Purchase, action: string): void =>
    router.post(`/purchases/${p.id}/${action}`, {}, { preserveScroll: true });

const totals = computed(() => ({
    budget: props.budget.reduce((s, l) => s + l.budget_sen, 0),
    spent: props.budget.reduce((s, l) => s + l.spent_sen, 0),
    owed: props.purchases
        .filter((p) => ["pending", "owed", "paying"].includes(p.status))
        .reduce((s, p) => s + p.total_sen, 0),
}));
const statusText = (p: Purchase): string =>
    ({
        reading: "Reading the receipt…",
        draft: "Check the items",
        pending: `Waiting for ${p.verifier ?? "verification"}`,
        owed:
            p.settle_when === "after_event"
                ? "To pay back after the event"
                : "To pay back now",
        paying:
            p.settle_method === "cash"
                ? "Cash handed over, waiting for the buyer"
                : p.payout_check === "unread"
                  ? "Transfer to confirm"
                  : "Checking the transfer…",
        recorded:
            p.paid_with === "club"
                ? "Recorded (club money)"
                : "Paid back and recorded",
        rejected: "Not accepted",
    })[p.status];
const tone = (
    p: Purchase,
): "success" | "warning" | "danger" | "info" | "neutral" =>
    p.status === "recorded"
        ? "success"
        : p.status === "rejected"
          ? "danger"
          : p.status === "reading" || p.status === "paying"
            ? "info"
            : "warning";
const barWidth = (l: { budget_sen: number; spent_sen: number }): string =>
    `${Math.min(100, l.budget_sen ? (l.spent_sen / l.budget_sen) * 100 : 100)}%`;
</script>

<template>
    <Head :title="`Purchases · ${event.name}`" />
    <AppLayout wide>
        <div class="grid gap-6">
            <header class="flex flex-wrap items-end justify-between gap-3">
                <div>
                    <Link
                        :href="`/events/${event.slug}`"
                        class="text-sm text-brand-blue"
                        >← {{ event.name }}</Link
                    >
                    <h1 class="text-2xl font-bold">Purchases & claims</h1>
                    <p class="text-sm text-ink-muted">
                        Snap every receipt at the till. Items and prices are
                        read for you; claims are verified and paid back here.
                    </p>
                </div>
                <label
                    class="relative inline-flex cursor-pointer items-center gap-2 overflow-hidden rounded-control bg-ink px-5 py-3 text-sm font-semibold text-white shadow-pop transition hover:-translate-y-0.5 active:scale-[0.98]"
                >
                    <span
                        class="absolute inset-0 animate-shimmer"
                        aria-hidden="true"
                    />
                    <Icon name="camera" :size="18" />
                    {{ snapping ? "Uploading…" : "Snap a receipt" }}
                    <input
                        type="file"
                        accept="image/*,application/pdf"
                        capture="environment"
                        class="sr-only"
                        :disabled="snapping"
                        @change="snap"
                    />
                </label>
            </header>

            <section class="grid gap-3 sm:grid-cols-3">
                <div
                    class="rounded-card border border-line bg-surface p-4 shadow-card"
                >
                    <p class="text-xs text-ink-muted">Approved budget</p>
                    <p class="text-xl font-bold tabular-nums">
                        {{ rm(totals.budget) }}
                    </p>
                </div>
                <div
                    class="rounded-card border border-line bg-surface p-4 shadow-card"
                >
                    <p class="text-xs text-ink-muted">Spent and recorded</p>
                    <p
                        class="text-xl font-bold tabular-nums"
                        :class="
                            totals.spent > totals.budget
                                ? 'text-danger'
                                : 'text-success'
                        "
                    >
                        {{ rm(totals.spent) }}
                    </p>
                </div>
                <div
                    class="rounded-card border border-line bg-surface p-4 shadow-card"
                >
                    <p class="text-xs text-ink-muted">Claims in progress</p>
                    <p class="text-xl font-bold tabular-nums text-warning">
                        {{ rm(totals.owed) }}
                    </p>
                </div>
            </section>

            <section
                v-if="budget.length"
                class="grid gap-3 rounded-card border border-line bg-surface p-4 shadow-card"
            >
                <h2 class="font-semibold">Budget against spending</h2>
                <div
                    v-for="l in budget"
                    :key="l.description"
                    class="grid gap-1 text-sm"
                >
                    <div class="flex justify-between gap-3">
                        <span>{{ l.description }}</span>
                        <span
                            class="tabular-nums"
                            :class="
                                l.spent_sen > l.budget_sen
                                    ? 'font-semibold text-danger'
                                    : 'text-ink-muted'
                            "
                            >{{ rm(l.spent_sen) }} /
                            {{ rm(l.budget_sen) }}</span
                        >
                    </div>
                    <div
                        class="h-2 overflow-hidden rounded-full bg-surface-muted"
                    >
                        <div
                            class="h-full rounded-full transition-[width] duration-700 ease-out"
                            :class="
                                l.spent_sen > l.budget_sen
                                    ? 'bg-danger'
                                    : 'bg-gradient-to-r from-brand-cyan to-brand-blue'
                            "
                            :style="{ width: barWidth(l) }"
                        />
                    </div>
                </div>
            </section>

            <TransitionGroup
                tag="section"
                name="list"
                class="relative grid gap-4"
            >
                <article
                    v-for="p in purchases"
                    :key="p.id"
                    class="grid gap-4 rounded-card border border-line bg-surface p-4 shadow-card md:grid-cols-[10rem_1fr]"
                >
                    <a
                        :href="p.receipt_url"
                        target="_blank"
                        rel="noopener"
                        class="block h-40 overflow-hidden rounded-control border border-line bg-surface-muted md:h-auto"
                    >
                        <img
                            :src="p.receipt_url"
                            alt="Receipt"
                            class="size-full object-cover"
                        />
                    </a>
                    <div class="grid content-start gap-3">
                        <div
                            class="flex flex-wrap items-center justify-between gap-2"
                        >
                            <p class="text-sm">
                                <strong>{{ p.buyer.name }}</strong>
                                <span class="text-ink-muted">
                                    ·
                                    {{
                                        p.paid_with === "club"
                                            ? "club money"
                                            : "own money"
                                    }}<template v-if="p.shop">
                                        · {{ p.shop }}</template
                                    >
                                    · {{ p.at }}</span
                                >
                            </p>
                            <Badge :tone="tone(p)">{{ statusText(p) }}</Badge>
                        </div>

                        <div v-if="p.status === 'reading'" class="grid gap-2">
                            <div
                                v-for="n in 3"
                                :key="n"
                                class="h-4 animate-pulse rounded bg-surface-muted"
                                :style="{ width: `${90 - n * 15}%` }"
                            />
                        </div>

                        <!-- Buyer checks what was read, then sends it on. -->
                        <form
                            v-else-if="
                                p.status === 'draft' && p.mine && drafts[p.id]
                            "
                            class="grid gap-3"
                            @submit.prevent="submitDraft(p.id)"
                        >
                            <p class="text-xs text-ink-muted">
                                {{
                                    p.items.length
                                        ? `Read from the receipt${p.ai_confidence !== null ? ` (${Math.round(p.ai_confidence * 100)}% sure)` : ""}. Fix anything that is wrong.`
                                        : "Could not read the receipt. Type the items in."
                                }}
                            </p>
                            <div class="grid gap-2">
                                <div
                                    v-for="(item, i) in drafts[p.id]!.items"
                                    :key="i"
                                    class="grid grid-cols-[1fr_4.5rem_6rem_auto] items-end gap-2"
                                >
                                    <Input
                                        v-model="item.name"
                                        :label="i === 0 ? 'Item' : undefined"
                                        placeholder="Item"
                                    />
                                    <Input
                                        v-model="item.quantity"
                                        type="number"
                                        :label="i === 0 ? 'Qty' : undefined"
                                    />
                                    <Input
                                        v-model="item.price_rm"
                                        type="number"
                                        :label="
                                            i === 0 ? 'Price (RM)' : undefined
                                        "
                                    />
                                    <button
                                        type="button"
                                        class="h-10 px-1 text-ink-muted hover:text-danger"
                                        aria-label="Remove item"
                                        @click="
                                            drafts[p.id]!.items.splice(i, 1)
                                        "
                                    >
                                        <Icon name="x" :size="16" />
                                    </button>
                                </div>
                                <button
                                    type="button"
                                    class="justify-self-start text-sm font-medium text-brand-blue"
                                    @click="
                                        drafts[p.id]!.items.push({
                                            name: '',
                                            quantity: 1,
                                            price_rm: '0.00',
                                        })
                                    "
                                >
                                    ＋ Add item
                                </button>
                            </div>
                            <div class="grid gap-3 sm:grid-cols-3">
                                <Input
                                    v-model="drafts[p.id]!.shop"
                                    label="Shop"
                                />
                                <Input
                                    v-model="drafts[p.id]!.purchased_on"
                                    type="date"
                                    label="Date"
                                />
                                <Select
                                    v-model="drafts[p.id]!.budget_line"
                                    label="Budget line"
                                    :options="[
                                        ...budgetLines.map((l) => ({
                                            value: l,
                                            label: l,
                                        })),
                                        {
                                            value: '',
                                            label: 'Not in the budget',
                                        },
                                    ]"
                                />
                            </div>
                            <div class="grid gap-3 sm:grid-cols-2">
                                <Select
                                    v-model="drafts[p.id]!.paid_with"
                                    label="Paid with"
                                    :options="
                                        can.organise
                                            ? [
                                                  {
                                                      value: 'club',
                                                      label: 'Club money',
                                                  },
                                                  {
                                                      value: 'own',
                                                      label: 'My own money (claim back)',
                                                  },
                                              ]
                                            : [
                                                  {
                                                      value: 'own',
                                                      label: 'My own money (claim back)',
                                                  },
                                              ]
                                    "
                                />
                                <Select
                                    v-if="drafts[p.id]!.paid_with === 'own'"
                                    v-model="drafts[p.id]!.verifier_id"
                                    label="Verified by (event committee)"
                                    placeholder="Choose who checks it"
                                    :options="
                                        verifiers.map((v) => ({
                                            value: v.id,
                                            label: v.name,
                                        }))
                                    "
                                />
                            </div>
                            <div
                                class="flex flex-wrap items-center justify-between gap-2 border-t border-line pt-3"
                            >
                                <p class="text-lg font-bold tabular-nums">
                                    {{ rm(draftTotal(p.id)) }}
                                </p>
                                <Button
                                    type="submit"
                                    :loading="submitting === p.id"
                                    :disabled="
                                        draftTotal(p.id) <= 0 ||
                                        (drafts[p.id]!.paid_with === 'own' &&
                                            !drafts[p.id]!.verifier_id)
                                    "
                                >
                                    {{
                                        drafts[p.id]!.paid_with === "club"
                                            ? "Record purchase"
                                            : "Send for verification"
                                    }}
                                </Button>
                            </div>
                        </form>

                        <template v-else>
                            <ul class="grid gap-1 text-sm">
                                <li
                                    v-for="(item, i) in p.items"
                                    :key="i"
                                    class="flex justify-between gap-3"
                                >
                                    <span
                                        >{{ item.name
                                        }}<span
                                            v-if="item.quantity > 1"
                                            class="text-ink-muted"
                                        >
                                            × {{ item.quantity }}</span
                                        ></span
                                    >
                                    <span class="tabular-nums">{{
                                        rm(
                                            item.total_sen ??
                                                item.unit_sen * item.quantity,
                                        )
                                    }}</span>
                                </li>
                            </ul>
                            <div
                                class="flex flex-wrap items-center justify-between gap-2 border-t border-line pt-2 text-sm"
                            >
                                <span class="text-ink-muted"
                                    >{{
                                        p.budget_line
                                            ? `Budget: ${p.budget_line}`
                                            : "Not in the budget"
                                    }}<template v-if="p.payer">
                                        · paid back by {{ p.payer }}</template
                                    ></span
                                >
                                <strong class="tabular-nums">{{
                                    rm(p.total_sen)
                                }}</strong>
                            </div>
                            <p
                                v-if="p.review_note"
                                class="rounded-control bg-warning/10 p-2 text-sm text-warning"
                            >
                                {{ p.review_note }}
                            </p>
                            <a
                                v-if="p.payout_url"
                                :href="p.payout_url"
                                target="_blank"
                                rel="noopener"
                                class="text-sm font-medium text-brand-blue"
                            >
                                {{
                                    p.settle_method === "cash"
                                        ? "Cash photo"
                                        : "Transfer receipt"
                                }}<template v-if="p.payout_payee">
                                    (to {{ p.payout_payee }})</template
                                >
                            </a>

                            <div
                                v-if="p.to_verify"
                                class="flex flex-wrap gap-2"
                            >
                                <Button @click="verify(p, 'now')"
                                    >Verify · pay back now</Button
                                >
                                <Button
                                    variant="secondary"
                                    @click="verify(p, 'after_event')"
                                    >Verify · pay after the event</Button
                                >
                                <Button variant="ghost" @click="reject(p)"
                                    >Reject</Button
                                >
                            </div>
                            <Button
                                v-if="
                                    can.organise &&
                                    !p.mine &&
                                    p.status === 'owed'
                                "
                                :disabled="
                                    p.settle_when === 'after_event' &&
                                    !event.ended
                                "
                                class="justify-self-start"
                                @click="openPay(p)"
                            >
                                <Icon name="send" :size="16" />
                                {{
                                    p.settle_when === "after_event" &&
                                    !event.ended
                                        ? "Pay back after the event"
                                        : `Pay back ${rm(p.total_sen)}`
                                }}
                            </Button>
                            <Button
                                v-if="
                                    p.mine &&
                                    p.status === 'paying' &&
                                    p.settle_method === 'cash'
                                "
                                class="justify-self-start"
                                @click="post(p, 'received')"
                                >I got {{ rm(p.total_sen) }} in cash</Button
                            >
                            <div
                                v-if="
                                    can.organise &&
                                    p.status === 'paying' &&
                                    p.payout_check === 'unread'
                                "
                                class="grid gap-2 rounded-control bg-surface-muted p-3 text-sm"
                            >
                                <p>
                                    The transfer receipt could not be read
                                    automatically. Check that it went to
                                    <strong>{{ p.buyer.name }}</strong> for
                                    {{ rm(p.total_sen) }}.
                                </p>
                                <Button
                                    variant="secondary"
                                    class="justify-self-start"
                                    @click="post(p, 'confirm-transfer')"
                                    >Yes, it went to {{ p.buyer.name }}</Button
                                >
                            </div>
                        </template>
                    </div>
                </article>
            </TransitionGroup>
            <p
                v-if="!purchases.length"
                class="rounded-card border border-dashed border-line p-8 text-center text-sm text-ink-muted"
            >
                No purchases yet. Snap the first receipt when you buy something
                for the event.
            </p>
        </div>

        <Modal
            :open="paying !== null"
            :title="
                paying
                    ? `Pay back ${paying.buyer.name} · ${rm(paying.total_sen)}`
                    : ''
            "
            @update:open="(v: boolean) => v || (paying = null)"
        >
            <div v-if="paying" class="grid gap-4">
                <div v-if="!payMethod" class="grid grid-cols-2 gap-3">
                    <button
                        type="button"
                        class="grid justify-items-center gap-2 rounded-card border border-line p-4 text-sm transition hover:-translate-y-0.5 hover:shadow-pop"
                        @click="payMethod = 'cash'"
                    >
                        <Icon name="qr" :size="28" /><strong>Cash</strong
                        ><span class="text-center text-xs text-ink-muted"
                            >Photo of their QR with the cash</span
                        >
                    </button>
                    <button
                        type="button"
                        class="grid justify-items-center gap-2 rounded-card border border-line p-4 text-sm transition hover:-translate-y-0.5 hover:shadow-pop"
                        @click="payMethod = 'online'"
                    >
                        <Icon name="send" :size="28" /><strong>Transfer</strong
                        ><span class="text-center text-xs text-ink-muted"
                            >TNG or bank; upload the receipt</span
                        >
                    </button>
                </div>
                <template v-else-if="payMethod === 'cash'">
                    <p class="text-sm">
                        Ask {{ paying.buyer.name }} to open their profile QR.
                        Hold the cash next to their phone.
                    </p>
                    <LiveQrCapture
                        v-if="paying.buyer.qr_token"
                        :expect-token="paying.buyer.qr_token"
                        :person-name="paying.buyer.name"
                        @capture="onCashPhoto"
                    />
                    <p
                        v-if="payForm.processing"
                        class="text-center text-sm text-ink-muted"
                    >
                        Checking the photo…
                    </p>
                </template>
                <template v-else>
                    <p class="text-sm">
                        Transfer {{ rm(paying.total_sen) }} to
                        <strong>{{ paying.buyer.name }}</strong
                        >, then upload the transfer receipt. The name on it must
                        be {{ paying.buyer.name }}.
                    </p>
                    <label
                        class="grid cursor-pointer justify-items-center gap-2 rounded-card border-2 border-dashed border-line p-6 text-sm text-ink-muted transition hover:border-brand-cyan"
                    >
                        <Icon name="receipt" :size="28" />
                        {{
                            payForm.processing
                                ? "Uploading…"
                                : "Choose the transfer receipt"
                        }}
                        <input
                            type="file"
                            accept="image/*,application/pdf"
                            class="sr-only"
                            @change="onTransfer"
                        />
                    </label>
                </template>
                <p
                    v-if="payForm.errors.photo || payForm.errors.receipt"
                    class="text-sm text-danger"
                >
                    {{ payForm.errors.photo ?? payForm.errors.receipt }}
                </p>
                <button
                    v-if="payMethod"
                    type="button"
                    class="justify-self-start text-sm text-ink-muted underline"
                    @click="payMethod = null"
                >
                    Back
                </button>
            </div>
        </Modal>
    </AppLayout>
</template>
