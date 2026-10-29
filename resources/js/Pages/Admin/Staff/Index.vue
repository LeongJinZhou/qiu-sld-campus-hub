<script setup lang="ts">
import Badge from '@/Components/ui/Badge.vue';
import Button from '@/Components/ui/Button.vue';
import Card from '@/Components/ui/Card.vue';
import Input from '@/Components/ui/Input.vue';
import Select from '@/Components/ui/Select.vue';
import Table from '@/Components/ui/Table.vue';
import PortalLayout from '@/Layouts/PortalLayout.vue';
import type { Option } from '@/types';
import { Head, router, useForm } from '@inertiajs/vue3';

interface StaffRow {
    id: number;
    email: string;
    name: string;
    title: string | null;
    approver_role: string | null;
    active: boolean;
    signed_in: boolean;
}

const props = defineProps<{ staff: StaffRow[]; approverRoles: Option[] }>();

const form = useForm({ email: '', name: '', title: '', approver_role: null as string | null });
const create = (): void => form.post('/admin/staff', { preserveScroll: true, onSuccess: () => form.reset() });

const roleLabel = (value: string | null) => props.approverRoles.find((r) => r.value === value)?.label ?? '—';

function toggle(row: StaffRow): void {
    router.put(`/admin/staff/${row.id}`, { name: row.name, title: row.title, approver_role: row.approver_role, active: !row.active }, { preserveScroll: true });
}

const columns = [
    { key: 'name' as const, label: 'Name', sortable: true },
    { key: 'email' as const, label: 'Email', sortable: true },
    { key: 'title' as const, label: 'Title' },
    { key: 'approver_role' as const, label: 'Approver role', sortable: true },
    { key: 'active' as const, label: 'Status' },
];
</script>

<template>
    <Head title="Staff" />
    <PortalLayout title="Staff accounts">
        <div class="grid gap-6">
            <Card>
                <template #title>Add staff</template>
                <form class="grid gap-3 md:grid-cols-[1fr_1fr_1fr_12rem_auto] md:items-end" @submit.prevent="create">
                    <Input v-model="form.email" type="email" label="QIU email" required :error="form.errors.email" />
                    <Input v-model="form.name" label="Name" required :error="form.errors.name" />
                    <Input v-model="form.title" label="Title" :error="form.errors.title" />
                    <Select v-model="form.approver_role" label="Approver role" :options="[{ value: '', label: 'None' }, ...approverRoles]" :error="form.errors.approver_role" />
                    <Button type="submit" :loading="form.processing">Add</Button>
                </form>
            </Card>

            <Table :columns="columns" :rows="staff" row-key="id">
                <template #cell-name="{ row }">
                    {{ row.name }} <Badge v-if="!row.signed_in">not signed in yet</Badge>
                </template>
                <template #cell-approver_role="{ row }">{{ roleLabel(row.approver_role) }}</template>
                <template #cell-active="{ row }">
                    <button type="button" @click="toggle(row)">
                        <Badge :tone="row.active ? 'success' : 'neutral'">{{ row.active ? 'Active' : 'Inactive' }}</Badge>
                    </button>
                </template>
            </Table>
        </div>
    </PortalLayout>
</template>
