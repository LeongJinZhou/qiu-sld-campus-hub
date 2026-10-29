<script setup lang="ts">
import ApprovalTracker from '@/Components/forms/ApprovalTracker.vue';
import Badge from '@/Components/ui/Badge.vue';
import Button from '@/Components/ui/Button.vue';
import Card from '@/Components/ui/Card.vue';
import Checkbox from '@/Components/ui/Checkbox.vue';
import FileUpload from '@/Components/ui/FileUpload.vue';
import Icon from '@/Components/ui/Icon.vue';
import Input from '@/Components/ui/Input.vue';
import PortalLayout from '@/Layouts/PortalLayout.vue';
import type { ApprovalProgress } from '@/types';
import { Head, Link, router, useForm } from '@inertiajs/vue3';

interface ClubRow {
    slug: string;
    name: string;
    agm: {
        starts_at: string;
        attended: number;
        report: ApprovalProgress | null;
        report_id: number | null;
        election: string | null;
        flags: { position: string; issue: string }[];
        checklist: Record<string, { label: string; ready: boolean }>;
        package_submitted_at: string | null;
    } | null;
    annual: boolean;
}

const props = defineProps<{
    period: { id: number; session: string; session_id: number; starts_on: string; agm_deadline: string; report_deadline: string } | null;
    history: { id: number; session: string; starts_on: string; report_deadline: string; closed: boolean }[];
    clubs: ClubRow[];
    themes: { id: number; name: string; background_color: string; title_color: string; body_color: string; font: string; is_default: boolean; background_url: string | null }[];
    defaultSession: string;
}>();

const open = useForm({ session: props.defaultSession, starts_on: '', agm_deadline: '', report_deadline: '' });
const close = (): void => {
    if (confirm('Close the AGM period? Clubs can no longer schedule or edit AGM work.')) router.post(`/admin/agm/periods/${props.period!.id}/close`);
};
const theme = useForm({ name: '', background_color: '#FFFFFF', title_color: '#14213D', body_color: '#333333', font: 'Poppins', background: [] as File[], is_default: false });
const saveTheme = (): void =>
    theme.transform((d) => ({ ...d, background: d.background[0] ?? null })).post('/admin/agm/themes', { forceFormData: true, preserveScroll: true, onSuccess: () => theme.reset() });
const flagText: Record<string, string> = { no_candidate: 'no candidate', uncontested: 'uncontested', tie: 'tie' };
</script>

<template>
    <Head title="AGM" />
    <PortalLayout title="AGM, elections and annual reports">
        <div class="grid gap-6">
            <Card v-if="period">
                <template #title>AGM period {{ period.session }} is open</template>
                <p class="text-sm">From {{ period.starts_on }} · AGMs by {{ period.agm_deadline }} · packages due {{ period.report_deadline }}</p>
                <Button variant="secondary" size="sm" class="mt-3" @click="close">Close period</Button>
            </Card>
            <Card v-else>
                <template #title>Open an AGM period</template>
                <form class="grid gap-3 md:grid-cols-[10rem_1fr_1fr_1fr_auto] md:items-end" @submit.prevent="open.post('/admin/agm/periods')">
                    <Input v-model="open.session" label="Session" placeholder="2026/2027" :error="open.errors.session" />
                    <Input v-model="open.starts_on" type="date" label="Opens" :error="open.errors.starts_on" />
                    <Input v-model="open.agm_deadline" type="date" label="AGM deadline" :error="open.errors.agm_deadline" />
                    <Input v-model="open.report_deadline" type="date" label="Report deadline" :error="open.errors.report_deadline" />
                    <Button type="submit" :loading="open.processing">Open</Button>
                </form>
            </Card>

            <Card v-if="period">
                <template #title>Clubs</template>
                <ul class="divide-y divide-line">
                    <li v-for="c in clubs" :key="c.slug" class="grid gap-3 py-4 text-sm lg:grid-cols-[14rem_1fr_16rem]">
                        <div>
                            <p class="font-semibold">{{ c.name }}</p>
                            <p v-if="c.agm" class="text-xs text-ink-muted">AGM {{ c.agm.starts_at }} · {{ c.agm.attended }} attended</p>
                            <Badge v-else tone="warning">AGM not scheduled</Badge>
                        </div>
                        <div v-if="c.agm" class="grid content-start gap-2">
                            <ApprovalTracker v-if="c.agm.report && c.agm.report.total" :progress="c.agm.report" compact />
                            <p v-else class="text-ink-muted">AGM Report not submitted</p>
                            <p>
                                Election: <Badge>{{ c.agm.election ?? 'none' }}</Badge>
                                <Badge v-for="f in c.agm.flags" :key="f.position + f.issue" tone="warning" class="ml-1">{{ f.position }}: {{ flagText[f.issue] }}</Badge>
                            </p>
                        </div>
                        <div v-if="c.agm" class="grid content-start gap-1">
                            <p class="font-medium">Package <Badge v-if="c.agm.package_submitted_at" tone="success">submitted</Badge></p>
                            <p v-for="(item, key) in c.agm.checklist" :key="key" class="flex items-center gap-1 text-xs">
                                <Icon :name="item.ready ? 'check' : 'x'" :size="14" :class="item.ready ? 'text-success' : 'text-ink-muted'" />
                                <template v-if="item.ready && key === 'report' && c.agm.report_id"><Link :href="`/documents/${c.agm.report_id}`" class="underline">{{ item.label }}</Link></template>
                                <template v-else-if="item.ready && key === 'finance'"><a :href="`/clubs/${c.slug}/finance/export/pdf`" class="underline">{{ item.label }}</a></template>
                                <template v-else-if="key === 'members'"><a :href="`/clubs/${c.slug}/members/export/xlsx`" class="underline">{{ item.label }}</a></template>
                                <template v-else-if="item.ready && key === 'annual'"><a :href="`/clubs/${c.slug}/annual-report/pdf?session=${period.session_id}`" class="underline">{{ item.label }}</a></template>
                                <template v-else>{{ item.label }}</template>
                            </p>
                        </div>
                    </li>
                </ul>
            </Card>

            <Card>
                <template #title>Annual report themes</template>
                <ul class="mb-4 flex flex-wrap gap-3">
                    <li v-for="t in themes" :key="t.id" class="w-40 overflow-hidden rounded-card border border-line text-xs">
                        <div class="grid h-20 place-items-center bg-cover bg-center" :style="{ backgroundColor: t.background_color, backgroundImage: t.background_url ? `url(${t.background_url})` : undefined, fontFamily: t.font }">
                            <span class="font-bold" :style="{ color: t.title_color }">Aa</span>
                        </div>
                        <p class="p-2">{{ t.name }} <Badge v-if="t.is_default" tone="info">default</Badge></p>
                    </li>
                    <li v-if="!themes.length" class="text-sm text-ink-muted">No themes yet — reports use the official template's look.</li>
                </ul>
                <form class="grid gap-3 md:grid-cols-3" @submit.prevent="saveTheme">
                    <Input v-model="theme.name" label="Name" :error="theme.errors.name" />
                    <Input v-model="theme.font" label="Font" :error="theme.errors.font" />
                    <div class="grid grid-cols-3 gap-2">
                        <Input v-model="theme.background_color" type="color" label="Background" />
                        <Input v-model="theme.title_color" type="color" label="Titles" />
                        <Input v-model="theme.body_color" type="color" label="Text" />
                    </div>
                    <FileUpload v-model="theme.background" label="Background image (optional)" accept="image/png,image/jpeg" :error="theme.errors.background" />
                    <Checkbox v-model="theme.is_default" label="Use as default" />
                    <div><Button type="submit" :loading="theme.processing">Add theme</Button></div>
                </form>
            </Card>

            <Card v-if="history.length">
                <template #title>Past periods</template>
                <ul class="grid gap-1 text-sm">
                    <li v-for="h in history" :key="h.id">{{ h.session }} · {{ h.starts_on }} → {{ h.report_deadline }} <Badge v-if="h.closed">closed</Badge></li>
                </ul>
            </Card>
        </div>
    </PortalLayout>
</template>
