<script setup lang="ts">
import Badge from '@/Components/ui/Badge.vue';
import Button from '@/Components/ui/Button.vue';
import Card from '@/Components/ui/Card.vue';
import Checkbox from '@/Components/ui/Checkbox.vue';
import Input from '@/Components/ui/Input.vue';
import Textarea from '@/Components/ui/Textarea.vue';
import PortalLayout from '@/Layouts/PortalLayout.vue';
import type { Option } from '@/types';
import { Head, useForm } from '@inertiajs/vue3';
import { computed } from 'vue';

const props = defineProps<{
    clubs: Option[];
    requests: { id: number; club: string; subject: string; body: string; due_on: string; overdue: boolean; reply: string | null; replied_by: string | null; replied_at: string | null }[];
}>();

const form = useForm({ club_ids: [] as number[], subject: '', body: '' });
const allClubs = computed({
    get: () => form.club_ids.length === props.clubs.length,
    set: (on: boolean) => (form.club_ids = on ? props.clubs.map((c) => Number(c.value)) : []),
});
const toggle = (id: number): void => {
    form.club_ids = form.club_ids.includes(id) ? form.club_ids.filter((x) => x !== id) : [...form.club_ids, id];
};
</script>

<template>
    <Head title="Requests to clubs" />
    <PortalLayout title="Requests to clubs">
        <div class="grid gap-6">
            <Card>
                <template #title>New request</template>
                <form class="grid gap-4" @submit.prevent="form.post('/admin/requests', { preserveScroll: true, onSuccess: () => form.reset() })">
                    <Input v-model="form.subject" label="Subject" :error="form.errors.subject" />
                    <Textarea v-model="form.body" label="What do you need from the club?" :rows="4" :error="form.errors.body" />
                    <div class="grid gap-2">
                        <Checkbox v-model="allClubs" label="All clubs" />
                        <div class="flex flex-wrap gap-2">
                            <button v-for="c in clubs" :key="c.value" type="button" class="rounded-full border px-3 py-1 text-sm" :class="form.club_ids.includes(Number(c.value)) ? 'border-brand-blue bg-brand-cyan/15' : 'border-line'" @click="toggle(Number(c.value))">{{ c.label }}</button>
                        </div>
                        <p v-if="form.errors.club_ids" class="text-xs text-danger">{{ form.errors.club_ids }}</p>
                    </div>
                    <p class="text-xs text-ink-muted">Clubs must reply within 7 working days. They are reminded the day before and daily while overdue.</p>
                    <div><Button type="submit" :loading="form.processing">Send</Button></div>
                </form>
            </Card>
            <Card>
                <template #title>Sent</template>
                <ul class="divide-y divide-line">
                    <li v-for="r in requests" :key="r.id" class="grid gap-1 py-3 text-sm">
                        <p class="flex flex-wrap items-center justify-between gap-2">
                            <span><strong>{{ r.club }}</strong> · {{ r.subject }}</span>
                            <Badge v-if="r.replied_at" tone="success">Replied {{ r.replied_at }}</Badge>
                            <Badge v-else :tone="r.overdue ? 'danger' : 'warning'">{{ r.overdue ? 'Overdue' : 'Due' }} {{ r.due_on }}</Badge>
                        </p>
                        <p class="text-ink-muted">{{ r.body }}</p>
                        <p v-if="r.reply" class="whitespace-pre-line rounded-control bg-surface-muted p-2">{{ r.replied_by }}: {{ r.reply }}</p>
                    </li>
                    <li v-if="!requests.length" class="py-3 text-sm text-ink-muted">No requests sent yet.</li>
                </ul>
            </Card>
        </div>
    </PortalLayout>
</template>
