<script setup lang="ts">
import Avatar from "@/Components/ui/Avatar.vue";
import Button from "@/Components/ui/Button.vue";
import Icon from "@/Components/ui/Icon.vue";
import Modal from "@/Components/ui/Modal.vue";
import { date, rm } from "@/lib/format";
import { Link, usePage } from "@inertiajs/vue3";
import { onMounted, ref, watch } from "vue";

/** At every sign-in: club committee members who have not paid their own club fee are reminded (7 days to pay). */
const page = usePage();
const dues = ref(page.props.committeeFees ?? []);
const open = ref(false);
const show = (): void => {
    if (dues.value.length) window.setTimeout(() => (open.value = true), 400);
};
onMounted(show);
watch(
    () => page.props.committeeFees,
    (list) => {
        if (list?.length) {
            dues.value = list;
            show();
        }
    },
);
</script>

<template>
    <Modal v-model:open="open">
        <div class="grid gap-4">
            <div class="grid justify-items-center gap-2 text-center">
                <span
                    class="grid size-14 animate-float-soft place-items-center rounded-full bg-gradient-to-br from-brand-yellow to-brand-orange text-white shadow-pop"
                    ><Icon name="award" :size="26"
                /></span>
                <h2 class="text-lg font-bold">
                    Committee members pay the club fee too
                </h2>
                <p class="text-sm text-ink-muted">
                    Lead by example: pay within 7 days of this reminder. It
                    shows each time you sign in until you have paid.
                </p>
            </div>
            <ul class="grid gap-2">
                <li
                    v-for="d in dues"
                    :key="d.club.slug"
                    class="flex items-center gap-3 rounded-card border p-3"
                    :class="
                        d.overdue
                            ? 'border-danger/40 bg-danger/5'
                            : 'border-line'
                    "
                >
                    <Avatar
                        :src="d.club.logo_url"
                        :name="d.club.name"
                        size="sm"
                    />
                    <span class="min-w-0 flex-1 text-sm">
                        <strong class="block truncate">{{
                            d.club.name
                        }}</strong>
                        <span class="text-xs text-ink-muted"
                            >{{ d.position }} · {{ rm(d.fee_sen) }} ·
                        </span>
                        <span
                            class="text-xs font-semibold"
                            :class="d.overdue ? 'text-danger' : 'text-warning'"
                        >
                            {{
                                d.overdue
                                    ? `overdue since ${date(d.due_on)}`
                                    : d.days_left === 0
                                      ? "due today"
                                      : `${d.days_left} day${d.days_left === 1 ? "" : "s"} left`
                            }}
                        </span>
                    </span>
                    <Link :href="d.url" @click="open = false"
                        ><Button size="sm">Pay</Button></Link
                    >
                </li>
            </ul>
            <button
                type="button"
                class="text-sm text-ink-muted underline"
                @click="open = false"
            >
                Remind me next time
            </button>
        </div>
    </Modal>
</template>
