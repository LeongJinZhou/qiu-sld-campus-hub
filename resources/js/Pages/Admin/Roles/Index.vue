<script setup lang="ts">
import Checkbox from '@/Components/ui/Checkbox.vue';
import FilterBar from '@/Components/ui/FilterBar.vue';
import Pagination from '@/Components/ui/Pagination.vue';
import PortalLayout from '@/Layouts/PortalLayout.vue';
import type { Option, Paginated } from '@/types';
import { Head, router } from '@inertiajs/vue3';
import { watchDebounced } from '@vueuse/core';
import { ref } from 'vue';

interface UserRow {
    id: number;
    name: string;
    email: string;
    roles: string[];
    is_me: boolean;
}

const props = defineProps<{ search: string; users: Paginated<UserRow>; managedRoles: Option[] }>();

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
    <PortalLayout title="Role management">
        <div class="grid gap-4">
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
                                    :disabled="user.is_me"
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
