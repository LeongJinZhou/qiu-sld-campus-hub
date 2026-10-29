<script setup lang="ts">
import ApprovalTracker from '@/Components/forms/ApprovalTracker.vue';
import Avatar from '@/Components/ui/Avatar.vue';
import Badge from '@/Components/ui/Badge.vue';
import Button from '@/Components/ui/Button.vue';
import Card from '@/Components/ui/Card.vue';
import Checkbox from '@/Components/ui/Checkbox.vue';
import EmptyState from '@/Components/ui/EmptyState.vue';
import Icon from '@/Components/ui/Icon.vue';
import Input from '@/Components/ui/Input.vue';
import Select from '@/Components/ui/Select.vue';
import Textarea from '@/Components/ui/Textarea.vue';
import AppLayout from '@/Layouts/AppLayout.vue';
import { dateTime } from '@/lib/format';
import type { ApprovalProgress } from '@/types';
import { Head, Link, router, useForm, usePoll } from '@inertiajs/vue3';
import { computed } from 'vue';

const props = defineProps<{
    club: { slug: string; name: string };
    period: { session: string; starts_on: string; agm_deadline: string; report_deadline: string } | null;
    calendarConnected: boolean;
    agm: {
        id: number;
        starts_at: string;
        ends_at: string;
        venue: string | null;
        meet_link: string | null;
        attendance_open: boolean;
        qr_svg: string | null;
        present: number[];
        report: ApprovalProgress | null;
        election: string | null;
        package_submitted_at: string | null;
        checklist: Record<string, { label: string; ready: boolean }>;
    } | null;
    people: { id: number; name: string; photo_url: string | null }[];
    annual: { events: { id: number; name: string; date: string; details: string }[]; theme_id: number | null; generated_at: string | null; ai: string | null; ai_available: boolean } | null;
    themes: { id: number; name: string }[];
}>();

const daysLeft = (date: string): number => Math.ceil((new Date(`${date}T23:59:59`).getTime() - Date.now()) / 86_400_000);
const schedule = useForm({ starts_at: '', ends_at: '', venue: '', online: false });
const mark = (id: number): void => router.post(`/agms/${props.agm!.id}/attendance`, { user_id: id }, { preserveScroll: true, only: ['agm'] });
const byId = useForm({ student_number: '' });

// The attendance QR rotates every minute, so refresh it while attendance is open.
usePoll(30_000, { only: ['agm'] }, { autoStart: props.agm?.attendance_open === true });

const annualForm = useForm({
    details: Object.fromEntries((props.annual?.events ?? []).map((e) => [e.id, e.details])) as Record<number, string>,
    theme_id: props.annual?.theme_id ?? null,
});
const drafting = computed(() => props.annual?.ai === 'pending');
usePoll(2500, { only: ['annual'], onSuccess: () => drafting.value || (annualForm.details = Object.fromEntries((props.annual?.events ?? []).map((e) => [e.id, e.details]))) }, { autoStart: drafting.value });
const saveAnnual = (then?: () => void): void => annualForm.put(`/clubs/${props.club.slug}/annual-report`, { preserveScroll: true, onSuccess: () => then?.() });
const generate = (): void => saveAnnual(() => router.post(`/clubs/${props.club.slug}/annual-report/generate`, {}, { preserveScroll: true }));
const themeOptions = computed(() => [{ value: '', label: 'Template default' }, ...props.themes.map((t) => ({ value: t.id, label: t.name }))]);
const packageReady = computed(() => Object.values(props.agm?.checklist ?? {}).every((i) => i.ready));
</script>

<template>
    <Head :title="`AGM · ${club.name}`" />
    <AppLayout wide>
        <div class="grid gap-6">
            <header>
                <Link :href="`/clubs/${club.slug}`" class="text-sm text-ink-muted hover:underline">← {{ club.name }}</Link>
                <h1 class="text-2xl font-bold">Annual General Meeting</h1>
            </header>

            <EmptyState v-if="!period" title="The AGM module is locked" description="SLD opens it during the AGM period. You'll see a countdown here when it starts." />

            <template v-else>
                <section class="grid gap-3 rounded-card bg-gradient-to-r from-brand-cyan/15 via-brand-blue/10 to-brand-magenta/15 p-5 sm:grid-cols-3">
                    <div><p class="text-xs text-ink-muted">Session</p><p class="text-lg font-bold">{{ period.session }}</p></div>
                    <div><p class="text-xs text-ink-muted">Hold the AGM by {{ period.agm_deadline }}</p><p class="text-lg font-bold">{{ Math.max(0, daysLeft(period.agm_deadline)) }} days left</p></div>
                    <div><p class="text-xs text-ink-muted">Submit the package by {{ period.report_deadline }}</p><p class="text-lg font-bold">{{ Math.max(0, daysLeft(period.report_deadline)) }} days left</p></div>
                </section>

                <Card v-if="!agm">
                    <template #title>1. Schedule the AGM</template>
                    <form class="grid gap-4" @submit.prevent="schedule.post(`/clubs/${club.slug}/agm`, { preserveScroll: true })">
                        <div class="grid gap-4 sm:grid-cols-2">
                            <Input v-model="schedule.starts_at" type="datetime-local" label="Starts" :error="schedule.errors.starts_at" />
                            <Input v-model="schedule.ends_at" type="datetime-local" label="Ends" :error="schedule.errors.ends_at" />
                        </div>
                        <Checkbox v-model="schedule.online" label="Online — create a Google Meet link from my QIU calendar" />
                        <p v-if="schedule.online && !calendarConnected" class="text-sm text-warning">
                            Connect your QIU Google Calendar first. <a href="/google/calendar/connect" class="underline">Connect</a>
                        </p>
                        <Input v-else-if="!schedule.online" v-model="schedule.venue" label="Venue" :error="schedule.errors.venue" />
                        <div><Button type="submit" :loading="schedule.processing">Schedule and invite members</Button></div>
                    </form>
                </Card>

                <div v-else class="grid gap-6 lg:grid-cols-[1fr_22rem]">
                    <div class="grid content-start gap-6">
                        <Card>
                            <template #title>AGM</template>
                            <p class="flex flex-wrap items-center gap-3 text-sm">
                                <span class="flex items-center gap-1"><Icon name="calendar" :size="16" />{{ dateTime(agm.starts_at) }}</span>
                                <span v-if="agm.venue" class="flex items-center gap-1"><Icon name="pin" :size="16" />{{ agm.venue }}</span>
                                <a v-if="agm.meet_link" :href="agm.meet_link" target="_blank" rel="noopener"><Button size="sm"><Icon name="camera" :size="16" /> Join Google Meet</Button></a>
                            </p>
                            <div class="mt-4 flex flex-wrap gap-2">
                                <Link :href="`/agms/${agm.id}/election`"><Button variant="secondary" size="sm">Election<Badge v-if="agm.election" class="ml-2">{{ agm.election }}</Badge></Button></Link>
                                <Link :href="`/agms/${agm.id}/report`"><Button variant="secondary" size="sm">AGM Report (01)</Button></Link>
                            </div>
                            <div v-if="agm.report && agm.report.total" class="mt-4"><ApprovalTracker :progress="agm.report" compact /></div>
                        </Card>

                        <Card v-if="annual">
                            <template #title>2. Annual Activities Report (12)</template>
                            <p class="mb-3 text-sm text-ink-muted">One details slide and one photos slide per approved event of {{ period.session }}. Photos come from each event's gallery album (up to 6).</p>
                            <div v-if="annual.ai_available && annual.events.length" class="mb-3">
                                <Button variant="secondary" size="sm" :loading="drafting" @click="router.post(`/clubs/${club.slug}/annual-report/draft`, {}, { preserveScroll: true })">
                                    <Icon name="sparkles" :size="16" /> {{ drafting ? 'Writing…' : 'Write empty details with AI' }}
                                </Button>
                                <p v-if="annual.ai === 'failed'" class="mt-1 text-sm text-warning">AI couldn't write the details — please write them yourself.</p>
                            </div>
                            <ul class="grid gap-4">
                                <li v-for="(e, i) in annual.events" :key="e.id">
                                    <Textarea v-model="annualForm.details[e.id]" :rows="3" :label="`${i + 1}. ${e.name} · ${e.date}`" placeholder="Details" />
                                </li>
                                <li v-if="!annual.events.length" class="text-sm text-ink-muted">No approved events this session yet.</li>
                            </ul>
                            <div class="mt-4 grid gap-3 sm:grid-cols-[1fr_auto_auto] sm:items-end">
                                <Select v-model="annualForm.theme_id" label="Theme" :options="themeOptions" />
                                <Button variant="secondary" :loading="annualForm.processing" @click="saveAnnual()">Save</Button>
                                <Button :loading="annualForm.processing" @click="generate">Generate PPTX &amp; PDF</Button>
                            </div>
                            <p v-if="annual.generated_at" class="mt-3 flex flex-wrap items-center gap-2 text-sm">
                                Generated {{ annual.generated_at }} ·
                                <a :href="`/clubs/${club.slug}/annual-report/pptx`" class="text-brand-blue underline">PPTX</a> ·
                                <a :href="`/clubs/${club.slug}/annual-report/pdf`" class="text-brand-blue underline">PDF</a>
                            </p>
                        </Card>

                        <Card>
                            <template #title>3. Submit the AGM package to SLD</template>
                            <ul class="grid gap-2 text-sm">
                                <li v-for="(item, key) in agm.checklist" :key="key" class="flex items-center gap-2">
                                    <Icon :name="item.ready ? 'check' : 'x'" :size="16" :class="item.ready ? 'text-success' : 'text-ink-muted'" />
                                    <span :class="item.ready ? '' : 'text-ink-muted'">{{ item.label }}</span>
                                </li>
                            </ul>
                            <p v-if="agm.package_submitted_at" class="mt-3 text-sm font-medium text-success">Submitted {{ agm.package_submitted_at }}</p>
                            <Button v-else class="mt-3" :disabled="!packageReady" @click="router.post(`/agms/${agm.id}/package`, {}, { preserveScroll: true })">Submit package</Button>
                        </Card>
                    </div>

                    <aside class="grid content-start gap-4">
                        <section class="rounded-card border border-line bg-surface p-5 shadow-card">
                            <h2 class="font-semibold">Attendance</h2>
                            <p class="mb-3 text-xs text-ink-muted">{{ agm.present.length }} present. Opens 1 hour before the AGM.</p>
                            <div v-if="agm.qr_svg" class="mb-3 grid justify-items-center gap-1">
                                <div class="w-56 rounded-card bg-white p-3" v-html="agm.qr_svg" />
                                <p class="text-xs text-ink-muted">Members scan to mark themselves present. Refreshes every minute.</p>
                            </div>
                            <form class="mb-3 flex gap-2" @submit.prevent="byId.post(`/agms/${agm.id}/attendance`, { preserveScroll: true, onSuccess: () => byId.reset() })">
                                <Input v-model="byId.student_number" placeholder="Student ID" class="flex-1" />
                                <Button type="submit" size="sm" variant="secondary" :disabled="!agm.attendance_open">Add</Button>
                            </form>
                            <ul class="grid max-h-96 gap-1 overflow-y-auto">
                                <li v-for="p in people" :key="p.id">
                                    <button type="button" class="flex w-full items-center gap-3 rounded-control p-2 text-left text-sm transition disabled:cursor-default" :class="agm.present.includes(p.id) ? 'bg-success/10' : 'hover:bg-surface-muted'" :disabled="agm.present.includes(p.id) || !agm.attendance_open" @click="mark(p.id)">
                                        <Avatar :src="p.photo_url" :name="p.name" size="xs" />
                                        <span class="flex-1">{{ p.name }}</span>
                                        <Icon v-if="agm.present.includes(p.id)" name="check" :size="16" class="text-success" />
                                    </button>
                                </li>
                            </ul>
                        </section>
                    </aside>
                </div>
            </template>
        </div>
    </AppLayout>
</template>
