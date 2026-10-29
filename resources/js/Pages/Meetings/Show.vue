<script setup lang="ts">
import ApprovalTracker from '@/Components/forms/ApprovalTracker.vue';
import DynamicTable from '@/Components/forms/DynamicTable.vue';
import FormSection from '@/Components/forms/FormSection.vue';
import Avatar from '@/Components/ui/Avatar.vue';
import Badge from '@/Components/ui/Badge.vue';
import Button from '@/Components/ui/Button.vue';
import Icon from '@/Components/ui/Icon.vue';
import Input from '@/Components/ui/Input.vue';
import Modal from '@/Components/ui/Modal.vue';
import SignaturePad from '@/Components/ui/SignaturePad.vue';
import Textarea from '@/Components/ui/Textarea.vue';
import AppLayout from '@/Layouts/AppLayout.vue';
import { dateTime } from '@/lib/format';
import type { ApprovalProgress, ApprovalStep } from '@/types';
import { Head, Link, router, useForm, usePoll } from '@inertiajs/vue3';
import { computed, ref } from 'vue';

interface Minutes {
    purpose: string;
    summary: string[];
    actions: { task: string; pic: string; deadline: string }[];
    next_date: string;
    next_time: string;
    next_venue: string;
    notes: string;
}

const props = defineProps<{
    meeting: { id: number; title: string; kind: string; kind_label: string; starts_at: string; ends_at: string; venue: string | null; meet_link: string | null; club: { slug: string; name: string }; hours: number };
    people: { id: number; name: string; photo_url: string | null }[];
    present: number[];
    minutes: { id: number; status: string; editable: boolean; data: Minutes; approval: { progress: ApprovalProgress; steps: ApprovalStep[] } | null; downloads: string[]; ai: string | null; ai_available: boolean };
}>();

const attendance = ref<number[]>([...props.present]);
const toggle = (id: number): void => {
    attendance.value = attendance.value.includes(id) ? attendance.value.filter((x) => x !== id) : [...attendance.value, id];
};
const saveAttendance = (): void => router.post(`/meetings/${props.meeting.id}/attendance`, { present: attendance.value }, { preserveScroll: true });

const form = useForm<Minutes & { signature: string | null; signature_strokes: string | null }>({ ...props.minutes.data, signature: null, signature_strokes: null });
const url = `/meetings/${props.meeting.id}/minutes`;
const plain = (d: typeof form.data extends () => infer T ? T : never) => {
    const { signature: _s, signature_strokes: _t, ...rest } = d as Minutes & { signature: unknown; signature_strokes: unknown };
    return rest;
};
const save = (): void => {
    form.transform((d) => plain(d)).put(url, { preserveScroll: true });
};
const draftWithAi = (): void => {
    form.transform((d) => plain(d)).post(`${url}/draft`, { preserveScroll: true });
};
// While the AI drafts, refresh until the draft lands, then load it into the form.
const drafting = computed(() => props.minutes.ai === 'pending');
usePoll(2500, { only: ['minutes'], onSuccess: () => drafting.value || Object.assign(form, props.minutes.data) }, { autoStart: drafting.value });

const summaryRows = computed({
    get: () => form.summary.map((text) => ({ text })),
    set: (rows: { text: string }[]) => (form.summary = rows.map((r) => r.text)),
});
const signing = ref(false);
const submit = (): void => form.post(`${url}/submit`, { preserveScroll: true, onFinish: () => (signing.value = false) });
const readOnly = computed(() => !props.minutes.editable);
</script>

<template>
    <Head :title="meeting.title" />
    <AppLayout wide>
        <div class="grid gap-6 lg:grid-cols-[1fr_22rem]">
            <div class="grid content-start gap-6">
                <header class="grid gap-2">
                    <Link :href="`/clubs/${meeting.club.slug}/meetings`" class="text-sm text-brand-blue">{{ meeting.club.name }} · meetings</Link>
                    <h1 class="text-2xl font-bold">{{ meeting.title }}</h1>
                    <p class="flex flex-wrap items-center gap-3 text-sm text-ink-muted">
                        <Badge tone="info">{{ meeting.kind_label }}</Badge>
                        <span class="flex items-center gap-1"><Icon name="calendar" :size="16" />{{ dateTime(meeting.starts_at) }}</span>
                        <span v-if="meeting.venue" class="flex items-center gap-1"><Icon name="pin" :size="16" />{{ meeting.venue }}</span>
                    </p>
                    <a v-if="meeting.meet_link" :href="meeting.meet_link" target="_blank" rel="noopener" class="justify-self-start"><Button><Icon name="camera" :size="16" /> Join Google Meet</Button></a>
                </header>

                <section v-if="minutes.approval" class="rounded-card border border-line bg-surface p-5 shadow-card">
                    <ApprovalTracker :progress="minutes.approval.progress" :steps="minutes.approval.steps" />
                </section>

                <div v-if="minutes.downloads.length" class="flex gap-2">
                    <a v-for="f in minutes.downloads" :key="f" :href="`/documents/${minutes.id}/download/${f}`"><Button variant="secondary" size="sm">Official 13 Minutes ({{ f.toUpperCase() }})</Button></a>
                </div>

                <FormSection title="Secretary's notes" hint="Jot down what happened; AI can turn the notes into minutes you then edit.">
                    <Textarea v-model="form.notes" :rows="5" :readonly="readOnly" placeholder="- attendance taken, 9 present&#10;- Aisyah updated on beach cleanup permits&#10;- Wei Zhang to design poster by 10 Nov" />
                    <Button v-if="minutes.editable && minutes.ai_available" variant="secondary" class="justify-self-start" :loading="drafting" :disabled="!form.notes.trim()" @click="draftWithAi">
                        <Icon name="sparkles" :size="16" /> {{ drafting ? 'Drafting…' : 'Draft minutes with AI' }}
                    </Button>
                    <p v-if="minutes.ai === 'failed'" class="text-sm text-warning">AI couldn't draft from these notes — fill the sections below yourself.</p>
                </FormSection>

                <FormSection title="1. Purpose of Meeting / Activity">
                    <Input v-model="form.purpose" :readonly="readOnly" :error="form.errors.purpose" />
                </FormSection>
                <FormSection title="2. Summary of Activities / Discussion">
                    <DynamicTable v-model="summaryRows" :columns="[{ key: 'text', label: 'Point', type: 'textarea' }]" :blank="{ text: '' }" :disabled="readOnly" :errors="form.errors" error-prefix="summary" add-label="Add point" />
                </FormSection>
                <FormSection title="3. Decisions / Action Items" hint='Leave empty if there are none — the form prints "N/A".'>
                    <DynamicTable v-model="form.actions" :columns="[{ key: 'task', label: 'Task/Action' }, { key: 'pic', label: 'Person in Charge' }, { key: 'deadline', label: 'Deadline' }]" :blank="{ task: '', pic: '', deadline: '' }" :disabled="readOnly" :errors="form.errors" error-prefix="actions" add-label="Add action" />
                </FormSection>
                <FormSection title="4. Next Meeting / Activity">
                    <div class="grid gap-3 sm:grid-cols-3">
                        <Input v-model="form.next_date" type="date" label="Date" :readonly="readOnly" />
                        <Input v-model="form.next_time" type="time" label="Time" :readonly="readOnly" />
                        <Input v-model="form.next_venue" label="Venue" :readonly="readOnly" />
                    </div>
                </FormSection>

                <div v-if="minutes.editable" class="flex flex-wrap justify-end gap-2">
                    <Button variant="secondary" :loading="form.processing" @click="save">Save draft</Button>
                    <Button @click="signing = true">Sign &amp; submit</Button>
                </div>
            </div>

            <aside class="grid content-start gap-4">
                <section class="rounded-card border border-line bg-surface p-5 shadow-card">
                    <h2 class="mb-1 font-semibold">Attendance</h2>
                    <p class="mb-3 text-xs text-ink-muted">{{ attendance.length }} present<template v-if="meeting.kind === 'activity'"> · {{ meeting.hours }} CCA each once minutes are approved</template></p>
                    <ul class="grid max-h-96 gap-1 overflow-y-auto">
                        <li v-for="p in people" :key="p.id">
                            <button type="button" class="flex w-full items-center gap-3 rounded-control p-2 text-left text-sm transition" :class="attendance.includes(p.id) ? 'bg-success/10' : 'hover:bg-surface-muted'" @click="toggle(p.id)">
                                <Avatar :src="p.photo_url" :name="p.name" size="xs" />
                                <span class="flex-1">{{ p.name }}</span>
                                <Icon v-if="attendance.includes(p.id)" name="check" :size="16" class="text-success" />
                            </button>
                        </li>
                    </ul>
                    <Button variant="secondary" size="sm" class="mt-3" block @click="saveAttendance">Save attendance</Button>
                </section>
            </aside>
        </div>

        <Modal v-model:open="signing" title="Prepared by">
            <div class="grid gap-4">
                <p class="text-sm text-ink-muted">Then: President → Club Advisor → SLD. Date and time come from the server.</p>
                <SignaturePad v-model="form.signature" v-model:strokes="form.signature_strokes" label="Signature" />
                <p v-if="Object.keys(form.errors).length" class="text-sm text-danger">{{ Object.values(form.errors)[0] }}</p>
                <Button :disabled="!form.signature" :loading="form.processing" @click="submit">Submit minutes</Button>
            </div>
        </Modal>
    </AppLayout>
</template>
