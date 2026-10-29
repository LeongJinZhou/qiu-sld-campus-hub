<script setup lang="ts">
import Badge from '@/Components/ui/Badge.vue';
import Button from '@/Components/ui/Button.vue';
import Card from '@/Components/ui/Card.vue';
import Input from '@/Components/ui/Input.vue';
import Select from '@/Components/ui/Select.vue';
import PortalLayout from '@/Layouts/PortalLayout.vue';
import { rm } from '@/lib/format';
import type { Option } from '@/types';
import { Head, router, useForm } from '@inertiajs/vue3';

defineProps<{
    fees: { id: number; club: string; session: string; amount_sen: number; account_name: string | null; payment_url: string | null; approved: boolean }[];
    drives: { id: number; title: string; club: string; starts_at: string; ends_at: string }[];
    clubs: Option[];
}>();

const drive = useForm({ title: '', club_id: null as number | null, starts_at: '', ends_at: '' });
const approve = (id: number): void => router.post(`/admin/fees/${id}/approve`, {}, { preserveScroll: true });
</script>

<template>
    <Head title="Fees & drives" />
    <PortalLayout title="Membership fees & drives">
        <div class="grid gap-6">
            <Card>
                <template #title>Club fees (set by Treasurers)</template>
                <ul class="divide-y divide-line">
                    <li v-for="f in fees" :key="f.id" class="flex flex-wrap items-center justify-between gap-3 py-3 text-sm">
                        <span><strong>{{ f.club }}</strong> · {{ f.session }} · {{ f.amount_sen ? rm(f.amount_sen) : 'Free' }}<template v-if="f.account_name"> · to {{ f.account_name }}</template></span>
                        <Badge v-if="f.approved" tone="success">Approved</Badge>
                        <Button v-else size="sm" @click="approve(f.id)">Approve</Button>
                    </li>
                    <li v-if="!fees.length" class="py-3 text-sm text-ink-muted">No fees submitted yet.</li>
                </ul>
            </Card>
            <Card>
                <template #title>Membership drives</template>
                <form class="mb-4 grid gap-3 md:grid-cols-[1fr_14rem_12rem_12rem_auto] md:items-end" @submit.prevent="drive.post('/admin/drives', { onSuccess: () => drive.reset() })">
                    <Input v-model="drive.title" label="Title" placeholder="Orientation Week Drive" :error="drive.errors.title" />
                    <Select v-model="drive.club_id" label="Club" :options="[{ value: '', label: 'All clubs' }, ...clubs]" />
                    <Input v-model="drive.starts_at" type="datetime-local" label="Starts" :error="drive.errors.starts_at" />
                    <Input v-model="drive.ends_at" type="datetime-local" label="Ends" :error="drive.errors.ends_at" />
                    <Button type="submit" :loading="drive.processing">Schedule</Button>
                </form>
                <ul class="grid gap-1 text-sm">
                    <li v-for="d in drives" :key="d.id"><strong>{{ d.title }}</strong> · {{ d.club }} · {{ d.starts_at }} → {{ d.ends_at }}</li>
                </ul>
            </Card>
        </div>
    </PortalLayout>
</template>
