<script setup lang="ts">
import Avatar from '@/Components/ui/Avatar.vue';
import Card from '@/Components/ui/Card.vue';
import GuestLayout from '@/Layouts/GuestLayout.vue';
import { Head, router } from '@inertiajs/vue3';

defineProps<{ accounts: { email: string; name: string; detail: string }[] }>();

const loginAs = (email: string): void => router.post('/dev/login-as', { email });
</script>

<template>
    <Head title="Dev login" />
    <GuestLayout>
        <Card>
            <template #title>Log in as (local only)</template>
            <ul class="grid max-h-[70dvh] gap-1 overflow-y-auto">
                <li v-for="account in accounts" :key="account.email">
                    <button type="button" class="flex w-full items-center gap-3 rounded-control p-2 text-left hover:bg-surface-muted" @click="loginAs(account.email)">
                        <Avatar :name="account.name" size="sm" />
                        <span class="grid">
                            <span class="text-sm font-medium">{{ account.name }}</span>
                            <span class="text-xs text-ink-muted">{{ account.detail }}</span>
                        </span>
                    </button>
                </li>
            </ul>
        </Card>
    </GuestLayout>
</template>
