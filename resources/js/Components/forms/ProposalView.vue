<script setup lang="ts">
import { hoursBetween, rm } from '@/lib/format';
import type { PresentedDocument } from '@/types';
import { computed } from 'vue';

// Read-only rendering of the proposal in the order of the official 06 template.
const props = defineProps<{ document: PresentedDocument }>();
const d = computed(() => props.document.data);
const opts = computed(() => props.document.options);
const ticked = (selected: (string | number)[] | undefined, map: Record<string | number, string> | undefined) =>
    (selected ?? []).map((key) => map?.[key] ?? String(key)).join(', ') || '—';
const when = computed(() => `${d.value.starts_at.replace('T', ' ')} – ${d.value.ends_at.replace('T', ' ')}`);
</script>

<template>
    <article class="grid gap-6 text-sm">
        <section class="overflow-x-auto rounded-card border border-line bg-surface">
            <h2 class="border-b border-line bg-ink px-4 py-2 text-center text-sm font-semibold text-white">Student Organization Concept Paper</h2>
            <table class="w-full">
                <tbody class="[&_th]:w-48 [&_th]:bg-surface-muted [&_th]:px-4 [&_th]:py-2 [&_th]:text-left [&_th]:align-top [&_th]:font-medium [&_td]:px-4 [&_td]:py-2 [&_tr]:border-t [&_tr]:border-line">
                    <tr><th>To</th><td>{{ document.locked.to }}</td></tr>
                    <tr><th>From</th><td>{{ document.locked.from }}</td></tr>
                    <tr><th>Date of Submission</th><td>{{ document.locked.submitted_on ?? 'On submission' }}</td></tr>
                    <tr><th>Event Date &amp; Time</th><td>{{ when }}</td></tr>
                    <tr><th>Event Name</th><td>{{ d.name }}</td></tr>
                    <tr><th>Event Summary</th><td class="whitespace-pre-line">{{ d.summary }}</td></tr>
                    <tr><th>Nature of event</th><td>{{ ticked(d.nature, opts.natures) }}<template v-if="d.nature_other"> ({{ d.nature_other }})</template></td></tr>
                    <tr><th>CCA Hours</th><td>Participants: {{ d.cca_participant }} · Event Committee: {{ d.cca_committee }}</td></tr>
                    <tr><th>Project Duration</th><td>{{ hoursBetween(d.starts_at, d.ends_at) }} hours</td></tr>
                    <tr><th>Event Venue</th><td>{{ d.venue }}</td></tr>
                    <tr><th>Mode</th><td class="capitalize">{{ d.mode }}</td></tr>
                    <tr><th>SDG Goals</th><td>{{ ticked(d.sdgs, opts.sdgs) }}</td></tr>
                    <tr><th>Collaboration</th><td>{{ d.collaboration || '—' }}</td></tr>
                    <tr><th>Total Budget</th><td>{{ document.totals ? rm(document.totals.expenses_sen) : '—' }}</td></tr>
                    <tr><th>Source of fund</th><td>{{ ticked(d.source_of_fund, opts.fundSources) }}</td></tr>
                </tbody>
            </table>
        </section>

        <section class="grid gap-4">
            <h2 class="text-lg font-bold uppercase">{{ d.name }} Proposal</h2>
            <div><h3 class="font-semibold">Introduction</h3><p class="whitespace-pre-line">{{ d.introduction }}</p></div>
            <div><h3 class="font-semibold">Objectives</h3><ol class="list-decimal pl-5"><li v-for="(o, i) in d.objectives" :key="i">{{ o }}</li></ol></div>
            <div><h3 class="font-semibold">Target Participants</h3><p>Expected number of participants: {{ d.expected_participants }}</p><p>Eligibility: {{ d.eligibility }}</p></div>
            <div>
                <h3 class="font-semibold">Proposed Activities</h3>
                <table class="mt-1 w-full border border-line"><tbody><tr v-for="(a, i) in d.activities" :key="i" class="border-t border-line"><td class="w-48 px-3 py-2 font-medium">{{ a.name }}</td><td class="px-3 py-2">{{ a.description }}</td></tr></tbody></table>
            </div>
            <div>
                <h3 class="font-semibold">Tentative / Event Itinerary</h3>
                <table class="mt-1 w-full border border-line"><tbody><tr v-for="(r, i) in d.itinerary" :key="i" class="border-t border-line"><td class="w-24 px-3 py-2">{{ r.start }}</td><td class="w-24 px-3 py-2">{{ r.end }}</td><td class="px-3 py-2">{{ r.agenda }}</td></tr></tbody></table>
            </div>
            <div><h3 class="font-semibold">ICT &amp; Maintenance Requirement</h3><p class="whitespace-pre-line">{{ d.ict_requirements || '—' }}</p></div>
            <div>
                <h3 class="font-semibold">Logistic Arrangements</h3>
                <p>Transportation {{ d.transport_required ? 'required' : 'not required' }}<template v-if="d.transport_arrangement">: {{ d.transport_arrangement }}</template></p>
                <p v-if="d.transport_required">Departure {{ d.departure_time }} from {{ d.departure_pickup }} · Return {{ d.return_time }} at {{ d.return_pickup }}</p>
            </div>
            <div>
                <h3 class="font-semibold">Event Committee &amp; Responsibilities</h3>
                <table class="mt-1 w-full border border-line"><tbody><tr v-for="(c, i) in document.committee" :key="i" class="border-t border-line"><td class="px-3 py-2">{{ c.name }}</td><td class="px-3 py-2">{{ c.position }}</td><td class="px-3 py-2">{{ c.responsibility }}</td></tr></tbody></table>
            </div>
            <div v-if="document.totals">
                <h3 class="font-semibold">Estimated Budget</h3>
                <p>Revenue {{ rm(document.totals.revenue_sen) }} · Expenses {{ rm(document.totals.expenses_sen) }} · Profit / Loss <strong :class="document.totals.covers_expenses ? '' : 'text-danger'">{{ rm(document.totals.profit_sen) }}</strong></p>
                <p v-if="!document.totals.covers_expenses" class="text-danger">Revenue does not cover expenses.</p>
            </div>
            <div><h3 class="font-semibold">Conclusion</h3><p class="whitespace-pre-line">{{ d.conclusion }}</p></div>
        </section>
    </article>
</template>
