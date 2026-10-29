<script setup lang="ts">
import Card from '@/Components/ui/Card.vue';
import GuestLayout from '@/Layouts/GuestLayout.vue';
import { Head } from '@inertiajs/vue3';

defineProps<{ code: string; certificate: { title: string; name: string; statement: string; issued_at: string } | null }>();
</script>

<template>
    <Head title="Verify certificate" />
    <GuestLayout>
        <Card class="grid gap-4">
            <img src="/brand/student-activity-black.png" alt="QIU Student Activity" class="mx-auto h-12 w-auto" />
            <h1 class="text-center text-lg font-semibold">Certificate verification</h1>
            <p class="text-center font-mono text-sm text-ink-muted">{{ code }}</p>
            <dl v-if="certificate" class="grid grid-cols-[7rem_1fr] gap-x-3 gap-y-2 text-sm">
                <dt class="text-ink-muted">Status</dt><dd class="font-semibold text-success">✓ Issued by QIU Student Life Division</dd>
                <dt class="text-ink-muted">Certificate</dt><dd>{{ certificate.title }}</dd>
                <dt class="text-ink-muted">Awarded to</dt><dd>{{ certificate.name }}</dd>
                <dt class="text-ink-muted">For</dt><dd>{{ certificate.name }} {{ certificate.statement }}</dd>
                <dt class="text-ink-muted">Issued</dt><dd>{{ certificate.issued_at }}</dd>
            </dl>
            <p v-else class="text-center text-sm font-medium text-danger">No certificate matches this code.</p>
        </Card>
    </GuestLayout>
</template>
