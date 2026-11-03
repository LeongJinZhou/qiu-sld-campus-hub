<script setup lang="ts">
import Button from '@/Components/ui/Button.vue';
import Checkbox from '@/Components/ui/Checkbox.vue';
import Input from '@/Components/ui/Input.vue';
import FilterBar from '@/Components/ui/FilterBar.vue';
import Pagination from '@/Components/ui/Pagination.vue';
import PortalLayout from '@/Layouts/PortalLayout.vue';
import type { Option, Paginated } from '@/types';
import { Head, router, useForm } from '@inertiajs/vue3';
import { watchDebounced } from '@vueuse/core';
import { ref } from 'vue';

interface UserRow {
    id: number;
    name: string;
    email: string;
    roles: string[];
    is_me: boolean;
    locked: boolean;
}

const props = defineProps<{ search: string; users: Paginated<UserRow>; managedRoles: Option[]; domain: string }>();
const add = useForm({ name: '', email: '', title: '' });
const submitAdd = (): void => add.post('/admin/roles', { preserveScroll: true, onSuccess: () => add.reset() });

const search = ref(props.search);
watchDebounced(search, (value) => router.get('/admin/roles', value ? { search: value } : {}, { preserveState: true, replace: true }), { debounce: 300 });

function toggle(user: UserRow, role: string, on: boolean): void {
    const managed = props.managedRoles.map((r) => String(r.value));
    const roles = user.roles.filter((r) => managed.includes(r) && r !== role);
    router.put(`/admin/roles/${user.id}`, { roles: on ? [...roles, role] : roles }, { preserveScroll: true });
}
</script>

<template>
    <Head title="Roles" />
    <PortalLayout title="Admins & roles">
        <div class="grid gap-4">
            <form class="grid gap-3 rounded-card border border-line bg-surface p-4 sm:grid-cols-[1fr_1fr_1fr_auto] sm:items-end" @submit.prevent="submitAdd">
                <p class="text-sm font-semibold sm:col-span-4">Add an SLD admin <span class="font-normal text-ink-muted">· they sign in with their QIU Google account</span></p>
                <Input v-model="add.name" label="Name" required :error="add.errors.name" />
                <Input v-model="add.email" type="email" label="QIU email" :placeholder="`name@${domain}`" required :error="add.errors.email" />
                <Input v-model="add.title" label="Title (optional)" placeholder="SLD Officer" :error="add.errors.title" />
                <Button type="submit" :loading="add.processing">Add admin</Button>
            </form>
            <FilterBar v-model:search="search" placeholder="Search name or email" />
            <div class="overflow-x-auto rounded-card border border-line bg-surface">
                <table class="w-full text-left text-sm">
                    <thead class="bg-surface-muted text-xs uppercase text-ink-muted">
                        <tr>
                            <th class="px-4 py-3">User</th>
                            <th class="px-4 py-3">Roles</th>
                            <th v-for="role in managedRoles" :key="role.value" class="px-4 py-3">{{ role.label }}</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-for="user in users.data" :key="user.id" class="border-t border-line">
                            <td class="px-4 py-3">
                                <div class="font-medium">{{ user.name }}</div>
                                <div class="text-xs text-ink-muted">{{ user.email }}</div>
                            </td>
                            <td class="px-4 py-3 text-xs text-ink-muted">{{ user.roles.join(', ') || '—' }}</td>
                            <td v-for="role in managedRoles" :key="role.value" class="px-4 py-3">
                                <Checkbox
                                    :model-value="user.roles.includes(String(role.value))"
                                    :disabled="user.locked"
                                    :label="''"
                                    :aria-label="`${role.label} for ${user.name}`"
                                    @update:model-value="(on: boolean) => toggle(user, String(role.value), on)"
                                />
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
            <Pagination :links="users.links" />
        </div>
    </PortalLayout>
</template>
