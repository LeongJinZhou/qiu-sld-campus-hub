<script setup lang="ts">
import Badge from '@/Components/ui/Badge.vue';
import Button from '@/Components/ui/Button.vue';
import Card from '@/Components/ui/Card.vue';
import Checkbox from '@/Components/ui/Checkbox.vue';
import Input from '@/Components/ui/Input.vue';
import Select from '@/Components/ui/Select.vue';
import Textarea from '@/Components/ui/Textarea.vue';
import AppLayout from '@/Layouts/AppLayout.vue';
import { Head, Link, useForm } from '@inertiajs/vue3';
import { computed } from 'vue';

type VenueType = 'la_place' | 'semester' | 'big_room';

const props = defineProps<{
    club: { slug: string; name: string };
    types: Record<VenueType, string>;
    rules: string[];
    earliestBigRoom: string;
    bookings: { id: number; type: VenueType; summary: string; status: string; sld_note: string | null }[];
}>();

const weekdays = ['Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday', 'Sunday'].map((d) => ({ value: d, label: d }));
const form = useForm({
    type: 'la_place' as VenueType,
    venue: '',
    date: '',
    semester: '',
    weekday: '',
    starts_at: '',
    ends_at: '',
    purpose: '',
    items: '',
    activity: '',
    rules_accepted: false,
});
const typeOptions = computed(() => Object.entries(props.types).map(([value, label]) => ({ value, label })));
const tone = (s: string): 'success' | 'danger' | 'warning' => (s === 'confirmed' ? 'success' : s === 'declined' ? 'danger' : 'warning');
const submit = (): void => form.post(`/clubs/${props.club.slug}/venues`, { preserveScroll: true, onSuccess: () => form.reset() });
</script>

<template>
    <Head :title="`Venues · ${club.name}`" />
    <AppLayout>
        <div class="grid gap-6">
            <div>
                <Link :href="`/clubs/${club.slug}`" class="text-sm text-ink-muted hover:underline">← {{ club.name }}</Link>
                <h1 class="text-2xl font-bold">Venue booking</h1>
                <p class="text-sm text-ink-muted">SLD is emailed and confirms in the app. You'll get a notification.</p>
            </div>

            <Card>
                <template #title>New request</template>
                <form class="grid gap-4" @submit.prevent="submit">
                    <Select v-model="form.type" label="What do you need?" :options="typeOptions" :error="form.errors.type" />
                    <Input v-if="form.type !== 'la_place'" v-model="form.venue" :label="form.type === 'semester' ? 'Venue (for the whole semester)' : 'Room'" placeholder="e.g. Lecture Hall 2" :error="form.errors.venue" />
                    <div class="grid gap-4 sm:grid-cols-3">
                        <template v-if="form.type === 'semester'">
                            <Input v-model="form.semester" label="Semester" placeholder="2026/2027 Sem 1" :error="form.errors.semester" />
                            <Select v-model="form.weekday" label="Every" placeholder="Choose a day" :options="weekdays" :error="form.errors.weekday" />
                        </template>
                        <Input v-else v-model="form.date" type="date" label="Date" :error="form.errors.date" />
                        <Input v-model="form.starts_at" type="time" label="From" :error="form.errors.starts_at" />
                        <Input v-model="form.ends_at" type="time" label="To" :error="form.errors.ends_at" />
                    </div>
                    <p v-if="form.type === 'big_room'" class="text-xs text-ink-muted">Big rooms need at least 1 week's notice (earliest {{ earliestBigRoom }}).</p>
                    <Input v-model="form.purpose" label="Purpose" placeholder="Membership drive booth" :error="form.errors.purpose" />
                    <Input v-if="form.type === 'la_place'" v-model="form.items" label="Items sold or promoted" placeholder="Club T-shirts, flyers" :error="form.errors.items" />
                    <Textarea v-model="form.activity" label="Activity" :rows="3" placeholder="What will happen at the venue?" :error="form.errors.activity" />

                    <div v-if="form.type === 'la_place'" class="grid gap-2 rounded-card bg-surface-muted p-4 text-sm">
                        <strong>La Place table rules (SLD SOP)</strong>
                        <ol class="list-decimal pl-5">
                            <li v-for="r in rules" :key="r">{{ r }}</li>
                        </ol>
                        <Checkbox v-model="form.rules_accepted" label="We will follow these rules" />
                        <p v-if="form.errors.rules_accepted" class="text-xs text-danger">{{ form.errors.rules_accepted }}</p>
                    </div>
                    <div><Button type="submit" :loading="form.processing">Send to SLD</Button></div>
                </form>
            </Card>

            <Card>
                <template #title>Requests</template>
                <ul class="divide-y divide-line">
                    <li v-for="b in bookings" :key="b.id" class="flex flex-wrap items-start justify-between gap-3 py-3 text-sm">
                        <span class="min-w-0 flex-1">{{ b.summary }}<span v-if="b.sld_note" class="block text-xs text-ink-muted">SLD: {{ b.sld_note }}</span></span>
                        <Badge :tone="tone(b.status)">{{ b.status }}</Badge>
                    </li>
                    <li v-if="!bookings.length" class="py-3 text-sm text-ink-muted">No requests yet.</li>
                </ul>
            </Card>
        </div>
    </AppLayout>
</template>
