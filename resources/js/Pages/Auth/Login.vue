<script setup lang="ts">
import Button from '@/Components/ui/Button.vue';
import Card from '@/Components/ui/Card.vue';
import GuestLayout from '@/Layouts/GuestLayout.vue';
import { Head } from '@inertiajs/vue3';
</script>

<template>
    <Head title="Sign in" />
    <GuestLayout>
        <Card class="grid gap-6 text-center">
            <img src="/brand/QIU_Student_Activity_Black.png" alt="QIU Student Activity" class="mx-auto h-16 w-auto" />
            <div class="grid gap-1">
                <h1 class="text-2xl font-bold">
                    <span class="text-gradient-warm">Campus</span> <span class="text-gradient-cool">Hub</span>
                </h1>
                <p class="text-sm text-ink-muted">Clubs, events and student life at QIU.</p>
            </div>
            <a href="/auth/google/redirect" class="block">
                <Button block size="lg">Continue with Google</Button>
            </a>
            <p class="text-xs text-ink-muted">Use your @qiu.edu.my account.</p>
        </Card>
    </GuestLayout>
</template>
