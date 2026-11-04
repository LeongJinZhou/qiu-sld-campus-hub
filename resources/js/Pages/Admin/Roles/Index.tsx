import Badge from '@/Components/ui/Badge';
import Button from '@/Components/ui/Button';
import Checkbox from '@/Components/ui/Checkbox';
import FilterBar from '@/Components/ui/FilterBar';
import Input from '@/Components/ui/Input';
import Pagination from '@/Components/ui/Pagination';
import { useFilterVisit } from '@/hooks/useFilterVisit';
import PortalLayout from '@/Layouts/PortalLayout';
import type { Option, Paginated } from '@/types';
import { Head, router, useForm } from '@inertiajs/react';
import { useState, type FormEvent } from 'react';

interface UserRow {
    id: number;
    name: string;
    email: string;
    roles: string[];
    is_me: boolean;
    locked: boolean;
}

const ROLE_LABELS: Record<string, string> = { superadmin: 'Super Admin', admin: 'SLD Officer', staff: 'Staff', student: 'Student' };

/** Super Admin and SLD Officers add SLD admins and grant or revoke the roles they may hand out. */
export default function Index({ search: initial, users, managedRoles, domain }: { search: string; users: Paginated<UserRow>; managedRoles: Option[]; domain: string }) {
    const add = useForm({ name: '', email: '', title: '' });
    const [search, setSearch] = useState(initial);
    useFilterVisit('/admin/roles', { search }, 300);
    const submitAdd = (e: FormEvent): void => {
        e.preventDefault();
        add.post('/admin/roles', { preserveScroll: true, onSuccess: () => add.reset() });
    };
    function toggle(user: UserRow, role: string, on: boolean): void {
        const managed = managedRoles.map((r) => String(r.value));
        const roles = user.roles.filter((r) => managed.includes(r) && r !== role);
        router.put(`/admin/roles/${user.id}`, { roles: on ? [...roles, role] : roles }, { preserveScroll: true });
    }

    return (
        <PortalLayout title="Admins & roles">
            <Head title="Admins & roles" />
            <div className="grid gap-4">
                <p className="text-sm text-ink-muted">
                    SLD Officers can add and remove other SLD Officers. Only a Super Admin can grant Super Admin. Nobody can change their own roles. Every change is recorded in the audit log.
                </p>
                <form className="grid gap-3 rounded-card border border-line bg-surface p-4 sm:grid-cols-[1fr_1fr_1fr_auto] sm:items-end" onSubmit={submitAdd}>
                    <p className="text-sm font-semibold sm:col-span-4">
                        Add an SLD admin <span className="font-normal text-ink-muted">· they sign in with their QIU Google account</span>
                    </p>
                    <Input value={add.data.name} onChange={(v) => add.setData('name', v)} label="Name" required error={add.errors.name} />
                    <Input value={add.data.email} onChange={(v) => add.setData('email', v)} type="email" label="QIU email" placeholder={`name@${domain}`} required error={add.errors.email} />
                    <Input value={add.data.title} onChange={(v) => add.setData('title', v)} label="Title (optional)" placeholder="SLD Officer" error={add.errors.title} />
                    <Button type="submit" loading={add.processing}>
                        Add admin
                    </Button>
                </form>
                <FilterBar search={search} onSearch={setSearch} placeholder="Search name or email" />
                <div className="overflow-x-auto rounded-card border border-line bg-surface">
                    <table className="w-full text-left text-sm">
                        <thead className="bg-surface-muted text-xs uppercase text-ink-muted">
                            <tr>
                                <th className="px-4 py-3">User</th>
                                <th className="px-4 py-3">Roles</th>
                                {managedRoles.map((role) => (
                                    <th key={role.value} className="px-4 py-3">
                                        {role.label}
                                    </th>
                                ))}
                            </tr>
                        </thead>
                        <tbody>
                            {users.data.map((user) => (
                                <tr key={user.id} className="border-t border-line">
                                    <td className="px-4 py-3">
                                        <div className="font-medium">{user.name}</div>
                                        <div className="text-xs text-ink-muted">{user.email}</div>
                                    </td>
                                    <td className="px-4 py-3">
                                        <div className="flex flex-wrap gap-1">
                                            {user.roles.length
                                                ? user.roles.map((r) => (
                                                      <Badge key={r} tone={r === 'superadmin' || r === 'admin' ? 'danger' : r === 'staff' ? 'info' : 'neutral'}>
                                                          {ROLE_LABELS[r] ?? r}
                                                      </Badge>
                                                  ))
                                                : '—'}
                                        </div>
                                    </td>
                                    {managedRoles.map((role) => (
                                        <td key={role.value} className="px-4 py-3" title={`${role.label} for ${user.name}`}>
                                            <Checkbox label="" checked={user.roles.includes(String(role.value))} disabled={user.locked} onChange={(on) => toggle(user, String(role.value), on)} />
                                        </td>
                                    ))}
                                </tr>
                            ))}
                        </tbody>
                    </table>
                </div>
                <Pagination links={users.links} />
            </div>
        </PortalLayout>
    );
}
