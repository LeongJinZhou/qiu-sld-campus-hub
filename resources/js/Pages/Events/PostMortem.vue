<script setup lang="ts">
import ApprovalTracker from '@/Components/forms/ApprovalTracker.vue';
import DynamicTable from '@/Components/forms/DynamicTable.vue';
import FormSection from '@/Components/forms/FormSection.vue';
import LockedField from '@/Components/forms/LockedField.vue';
import Button from '@/Components/ui/Button.vue';
import Icon from '@/Components/ui/Icon.vue';
import Modal from '@/Components/ui/Modal.vue';
import SignaturePad from '@/Components/ui/SignaturePad.vue';
import AppLayout from '@/Layouts/AppLayout.vue';
import type { ApprovalProgress, ApprovalStep } from '@/types';
import { copy } from '@/lib/clone';
import { Head, Link, useForm, usePoll } from '@inertiajs/vue3';
import { computed, ref } from 'vue';

type Row = { well: string; challenges: string; improvement: string };
interface Data {
    rows: Record<string, Row>;
    recommendations: string[];
}

const props = defineProps<{
    event: { slug: string; name: string };
    header: { event: string; club: string; date: string; time: string; venue: string; participants: number; committee: string[] };
    areas: Record<string, string>;
    document: { id: number; status: string; editable: boolean; data: Data; approval: { progress: ApprovalProgress; steps: ApprovalStep[] } | null; downloads: string[]; ai: string | null; ai_available: boolean };
}>();

const form = useForm({ rows: copy(props.document.data.rows), recommendations: [...props.document.data.recommendations], signature: null as string | null, signature_strokes: null as string | null });
const readOnly = computed(() => !props.document.editable);
const url = `/events/${props.event.slug}/post-mortem`;
const body = () => ({ rows: form.rows, recommendations: form.recommendations });
const save = (): void => form.transform(body).put(url, { preserveScroll: true });
const suggest = (): void => form.transform(body).post(`${url}/suggest`, { preserveScroll: true });
const suggesting = computed(() => props.document.ai === 'pending');
usePoll(2500, { only: ['document'], onSuccess: () => { if (!suggesting.value) { form.rows = copy(props.document.data.rows); form.recommendations = [...props.document.data.recommendations]; } } }, { autoStart: suggesting.value });

const recRows = computed({
    get: () => form.recommendations.map((text) => ({ text })),
    set: (rows: { text: string }[]) => (form.recommendations = rows.map((r) => r.text)),
});
const signing = ref(false);
const submit = (): void => form.post(`${url}/submit`, { preserveScroll: true, onFinish: () => (signing.value = false) });
</script>

<template>
    <Head :title="`Post mortem · ${event.name}`" />
    <AppLayout wide>
        <div class="grid gap-6">
            <header>
                <Link :href="`/events/${event.slug}`" class="text-sm text-brand-blue">{{ event.name }}</Link>
                <h1 class="text-xl font-bold">Event Post Mortem</h1>
                <p class="text-sm text-ink-muted">09 template · Prepared by → Club Advisor → SLD</p>
            </header>

            <section v-if="document.approval" class="rounded-card border border-line bg-surface p-5 shadow-card">
                <ApprovalTracker :progress="document.approval.progress" :steps="document.approval.steps" />
            </section>
            <div v-if="document.downloads.length" class="flex flex-wrap gap-2">
                <a v-for="f in document.downloads" :key="f" :href="`/documents/${document.id}/download/${f}`"><Button variant="secondary" size="sm">Official 09 Post Mortem ({{ f.toUpperCase() }})</Button></a>
            </div>

            <FormSection title="Event">
                <div class="grid gap-3 sm:grid-cols-3">
                    <LockedField label="Event Name" :value="header.event" />
                    <LockedField label="Organizing Club" :value="header.club" />
                    <LockedField label="Date" :value="header.date" />
                    <LockedField label="Time" :value="header.time" />
                    <LockedField label="Venue" :value="header.venue" />
                    <LockedField label="Number of Actual Participants" :value="String(header.participants)" />
                </div>
                <LockedField label="Committees (with position)" :value="header.committee.join(' · ')" />
            </FormSection>

            <FormSection title="Review" hint='Fill the relevant one. If there is nothing, leave it empty — the form prints "N/A".'>
                <div class="overflow-x-auto">
                    <table class="w-full min-w-[56rem] text-sm">
                        <thead class="text-left text-xs uppercase text-ink-muted"><tr><th class="w-72 py-2"></th><th class="px-2 py-2">What went well</th><th class="px-2 py-2">Challenges faced</th><th class="px-2 py-2">Improvement suggestion</th></tr></thead>
                        <tbody>
                            <tr v-for="(label, key) in areas" :key="key" class="border-t border-line align-top">
                                <th class="py-2 pr-3 text-left font-medium">{{ key }}. {{ label }}</th>
                                <td v-for="field in ['well', 'challenges', 'improvement'] as const" :key="field" class="px-1 py-1.5">
                                    <textarea v-model="form.rows[key]![field]" rows="3" :readonly="readOnly" :aria-label="`${key} ${field}`" class="w-full rounded-control border border-line px-2 py-1.5 read-only:bg-surface-muted" />
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </FormSection>

            <FormSection title="Recommendations for future committees">
                <DynamicTable v-model="recRows" :columns="[{ key: 'text', label: 'Recommendation', type: 'textarea' }]" :blank="{ text: '' }" :disabled="readOnly" error-prefix="recommendations" add-label="Add recommendation" />
                <Button v-if="document.editable && document.ai_available" variant="secondary" class="justify-self-start" :loading="suggesting" @click="suggest">
                    <Icon name="sparkles" :size="16" /> {{ suggesting ? 'Thinking…' : 'Suggest improvements with AI' }}
                </Button>
                <p class="text-xs text-ink-muted">AI only fills empty improvement and recommendation boxes; edit anything it writes.</p>
            </FormSection>

            <div v-if="document.editable" class="flex justify-end gap-2">
                <Button variant="secondary" :loading="form.processing" @click="save">Save draft</Button>
                <Button @click="signing = true">Sign &amp; submit</Button>
            </div>
        </div>

        <Modal v-model:open="signing" title="Prepared by">
            <div class="grid gap-4">
                <SignaturePad v-model="form.signature" v-model:strokes="form.signature_strokes" label="Signature" />
                <p v-if="Object.keys(form.errors).length" class="text-sm text-danger">{{ Object.values(form.errors)[0] }}</p>
                <Button :disabled="!form.signature" :loading="form.processing" @click="submit">Submit post mortem</Button>
            </div>
        </Modal>
    </AppLayout>
</template>
