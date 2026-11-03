import Badge from '@/Components/ui/Badge';
import Button from '@/Components/ui/Button';
import Card from '@/Components/ui/Card';
import Input from '@/Components/ui/Input';
import Select from '@/Components/ui/Select';
import Table from '@/Components/ui/Table';
import PortalLayout from '@/Layouts/PortalLayout';
import type { Option } from '@/types';
import { Head, router, useForm } from '@inertiajs/react';
import type { FormEvent } from 'react';

interface StaffRow {
    id: number;
    email: string;
    name: string;
    title: string | null;
    staff_number: string | null;
    approver_role: string | null;
    active: boolean;
    signed_in: boolean;
}
const COLUMNS = [
    { key: 'name' as const, label: 'Name', sortable: true },
    { key: 'email' as const, label: 'Email', sortable: true },
    { key: 'title' as const, label: 'Title' },
    { key: 'staff_number' as const, label: 'Employee ID' },
    { key: 'approver_role' as const, label: 'Approver role', sortable: true },
    { key: 'active' as const, label: 'Status' },
];

export default function Index({ staff, approverRoles }: { staff: StaffRow[]; approverRoles: Option[] }) {
    const form = useForm({ email: '', staff_number: '', name: '', title: '', approver_role: '' as string });
    const create = (e: FormEvent): void => {
        e.preventDefault();
        form.post('/admin/staff', { preserveScroll: true, onSuccess: () => form.reset() });
    };
    const roleLabel = (value: string | null): string => approverRoles.find((r) => r.value === value)?.label ?? '—';
    const save = (row: StaffRow, changes: Partial<StaffRow>): void =>
        router.put(`/admin/staff/${row.id}`, { name: row.name, title: row.title, staff_number: row.staff_number, approver_role: row.approver_role, active: row.active, ...changes }, { preserveScroll: true });
    // Employee ID: printed beside advisors on the proposal's committee chart.
    function editStaffNumber(row: StaffRow): void {
        const value = window.prompt(`Employee ID for ${row.name}`, row.staff_number ?? '');
        if (value !== null) save(row, { staff_number: value.trim() || null });
    }

    return (
        <PortalLayout title="Staff accounts">
            <Head title="Staff" />
            <div className="grid gap-6">
                <Card title="Add staff">
                    <form className="grid gap-3 md:grid-cols-[1fr_1fr_8rem_1fr_12rem_auto] md:items-end" onSubmit={create}>
                        <Input value={form.data.email} onChange={(v) => form.setData('email', v)} type="email" label="QIU email" required error={form.errors.email} />
                        <Input value={form.data.name} onChange={(v) => form.setData('name', v)} label="Name" required error={form.errors.name} />
                        <Input value={form.data.staff_number} onChange={(v) => form.setData('staff_number', v)} label="Employee ID" error={form.errors.staff_number} />
                        <Input value={form.data.title} onChange={(v) => form.setData('title', v)} label="Title" error={form.errors.title} />
                        <Select
                            value={form.data.approver_role}
                            onChange={(v) => form.setData('approver_role', String(v))}
                            label="Approver role"
                            options={[{ value: '', label: 'None' }, ...approverRoles]}
                            error={form.errors.approver_role}
                        />
                        <Button type="submit" loading={form.processing}>
                            Add
                        </Button>
                    </form>
                </Card>
                <Table
                    columns={COLUMNS}
                    rows={staff}
                    rowKey="id"
                    cells={{
                        name: (row) => (
                            <>
                                {row.name} {!row.signed_in && <Badge>not signed in yet</Badge>}
                            </>
                        ),
                        staff_number: (row) => (
                            <button type="button" className="text-left hover:text-brand-blue" title="Set employee ID" onClick={() => editStaffNumber(row)}>
                                {row.staff_number || 'Set'}
                            </button>
                        ),
                        approver_role: (row) => roleLabel(row.approver_role),
                        active: (row) => (
                            <button type="button" onClick={() => save(row, { active: !row.active })}>
                                <Badge tone={row.active ? 'success' : 'neutral'}>{row.active ? 'Active' : 'Inactive'}</Badge>
                            </button>
                        ),
                    }}
                />
            </div>
        </PortalLayout>
    );
}
