<script setup lang="ts">
import ApprovalTracker from '@/Components/forms/ApprovalTracker.vue';
import Badge from '@/Components/ui/Badge.vue';
import Button from '@/Components/ui/Button.vue';
import Card from '@/Components/ui/Card.vue';
import Checkbox from '@/Components/ui/Checkbox.vue';
import EmptyState from '@/Components/ui/EmptyState.vue';
import Icon from '@/Components/ui/Icon.vue';
import Input from '@/Components/ui/Input.vue';
import Select from '@/Components/ui/Select.vue';
import AppLayout from '@/Layouts/AppLayout.vue';
import { dateTime } from '@/lib/format';
import type { ApprovalProgress } from '@/types';
import { Head, Link, useForm } from '@inertiajs/vue3';
import { computed } from 'vue';

const props = defineProps<{
    club: { slug: string; name: string };
    calendarConnected: boolean;
    kinds: Record<string, string>;
    audiences: Record<string, string>;
    meetings: {
        id: number;
        title: string;
        kind: string;
        starts_at: string;
        venue: string | null;
        meet_link: string | null;
        upcoming: boolean;
        audience: string | null;
        pax: number | null;
        minutes: ApprovalProgress | null;
    }[];
}>();

// Committee meeting: committee + advisors only. Weekly activity: pick who is invited, and give the pax and location.
const form = useForm({ kind: 'committee', title: '', starts_at: '', ends_at: '', venue: '', online: props.calendarConnected, audience: 'members', pax: '' as number | '' });
const isActivity = computed(() => form.kind === 'activity');
const inviteLabel = computed(() => (isActivity.value && form.audience === 'members' ? 'Create & invite all members' : 'Create & invite committee'));
</script>

<template>
    <Head :title="`Meetings · ${club.name}`" />
    <AppLayout wide>
        <div class="grid gap-6 lg:grid-cols-[1fr_24rem]">
            <div class="grid content-start gap-4">
                <header>
                    <h1 class="text-xl font-bold">Meetings &amp; activities · {{ club.name }}</h1>
                    <span class="flex flex-wrap gap-4 text-sm">
                        <Link :href="`/clubs/${club.slug}`" class="text-brand-blue">Back to club</Link>
                        <Link :href="`/clubs/${club.slug}/meetings/report`" class="text-brand-blue">Meetings report</Link>
                    </span>
                </header>
                <EmptyState v-if="!meetings.length" title="No meetings yet" description="Create one to invite the committee and keep minutes." />
                <ul class="grid gap-3">
                    <li v-for="m in meetings" :key="m.id">
                        <Link :href="`/meetings/${m.id}`" class="grid gap-2 rounded-card border border-line bg-surface p-4 shadow-card transition hover:shadow-pop">
                            <span class="flex flex-wrap items-center justify-between gap-2">
                                <strong>{{ m.title }}</strong>
                                <span class="flex flex-wrap gap-2"><Badge tone="info">{{ kinds[m.kind] }}</Badge><Badge v-if="m.audience">{{ m.audience }}<template v-if="m.pax"> · {{ m.pax }} pax</template></Badge><Badge v-if="m.upcoming" tone="success">Upcoming</Badge></span>
                            </span>
                            <span class="flex items-center gap-2 text-sm text-ink-muted"><Icon name="calendar" :size="16" />{{ dateTime(m.starts_at) }} · {{ m.meet_link ? 'Google Meet' : m.venue }}</span>
                            <ApprovalTracker v-if="m.minutes && m.minutes.total" :progress="m.minutes" compact />
                            <span v-else class="text-xs text-ink-muted">Minutes not submitted yet</span>
                        </Link>
                    </li>
                </ul>
            </div>

            <Card class="self-start">
                <template #title>New meeting</template>
                <form class="grid gap-3" @submit.prevent="form.post(`/clubs/${club.slug}/meetings`)">
                    <Select v-model="form.kind" label="Type" :options="Object.entries(kinds).map(([value, label]) => ({ value, label }))" />
                    <Select
                        v-if="isActivity"
                        v-model="form.audience"
                        label="Invite"
                        :options="Object.entries(audiences).map(([value, label]) => ({ value, label }))"
                        :error="form.errors.audience"
                    />
                    <p v-else class="rounded-control bg-surface-muted px-3 py-2 text-xs text-ink-muted">Committee meetings are for the committee and advisors only.</p>
                    <Input v-model="form.title" label="Title" placeholder="Weekly committee meeting" :error="form.errors.title" />
                    <Input v-model="form.starts_at" type="datetime-local" label="Starts" :error="form.errors.starts_at" />
                    <Input v-model="form.ends_at" type="datetime-local" label="Ends" :error="form.errors.ends_at" />
                    <Checkbox v-model="form.online" label="Online — create a Google Meet link" />
                    <p v-if="form.online && !calendarConnected" class="rounded-control bg-brand-cyan/10 p-3 text-sm">
                        Meet links are created from your own QIU Google Calendar.
                        <a href="/google/calendar/connect" class="font-semibold text-brand-blue">Connect Google Calendar</a>
                    </p>
                    <Input v-if="!form.online || isActivity" v-model="form.venue" :label="isActivity ? 'Location' : 'Venue'" :required="isActivity" :error="form.errors.venue" />
                    <Input v-if="isActivity" v-model="form.pax" type="number" label="Expected pax" required :min="1" :error="form.errors.pax" />
                    <p v-if="form.kind === 'activity'" class="text-xs text-ink-muted">Weekly activity: attendees earn CCA (1 hour = 1 CCA) once the minutes and attendance are approved.</p>
                    <Button type="submit" :loading="form.processing" :disabled="form.online && !calendarConnected">{{ inviteLabel }}</Button>
                </form>
            </Card>
        </div>
    </AppLayout>
</template>
