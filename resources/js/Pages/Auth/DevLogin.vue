<script setup lang="ts">
import Avatar from '@/Components/ui/Avatar.vue';
import Card from '@/Components/ui/Card.vue';
import GuestLayout from '@/Layouts/GuestLayout.vue';
import Button from '@/Components/ui/Button.vue';
import Input from '@/Components/ui/Input.vue';
import { Head, router, useForm } from '@inertiajs/vue3';

defineProps<{ locked: boolean; accounts: { email: string; name: string; detail: string }[] }>();

const loginAs = (email: string): void => router.post('/dev/login-as', { email });
const unlock = useForm({ passcode: '' });
</script>

<template>
    <Head title="Dev login" />
    <GuestLayout>
        <Card>
            <template #title>Demo login</template>
            <form v-if="locked" class="grid gap-3" @submit.prevent="unlock.post('/dev/login-as/unlock', { onFinish: () => unlock.reset() })">
                <p class="text-sm text-ink-muted">Testing access. Enter the demo passcode to continue.</p>
                <Input v-model="unlock.passcode" type="password" label="Passcode" autocomplete="off" :error="unlock.errors.passcode" />
                <Button type="submit" :loading="unlock.processing">Unlock</Button>
            </form>
            <ul v-else class="grid max-h-[70dvh] gap-1 overflow-y-auto">
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
