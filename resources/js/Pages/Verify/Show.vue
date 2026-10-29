<script setup lang="ts">
import Card from '@/Components/ui/Card.vue';
import GuestLayout from '@/Layouts/GuestLayout.vue';
import { Head } from '@inertiajs/vue3';

defineProps<{
    code: string;
    record: { signer: string; position: string; signed_at: string; document: string | null; club: string | null; event: string | null; status: string | null } | null;
}>();
</script>

<template>
    <Head title="Verify signature" />
    <GuestLayout>
        <Card class="grid gap-4">
            <img src="/brand/QIU_Student_Activity_Black.png" alt="QIU Student Activity" class="mx-auto h-12 w-auto" />
            <h1 class="text-center text-lg font-semibold">Signature verification</h1>
            <p class="text-center font-mono text-sm text-ink-muted">{{ code }}</p>
            <dl v-if="record" class="grid grid-cols-[7rem_1fr] gap-x-3 gap-y-2 text-sm">
                <dt class="text-ink-muted">Status</dt><dd class="font-semibold text-success">✓ Valid signature</dd>
                <dt class="text-ink-muted">Signed by</dt><dd>{{ record.signer }}</dd>
                <dt class="text-ink-muted">Position</dt><dd>{{ record.position }}</dd>
                <dt class="text-ink-muted">Signed at</dt><dd>{{ record.signed_at }}</dd>
                <dt class="text-ink-muted">Document</dt><dd>{{ record.document }}</dd>
                <dt class="text-ink-muted">Organisation</dt><dd>{{ record.club }}</dd>
                <dt v-if="record.event" class="text-ink-muted">Event</dt><dd v-if="record.event">{{ record.event }}</dd>
                <dt class="text-ink-muted">Document status</dt><dd class="capitalize">{{ record.status?.replace('_', ' ') }}</dd>
            </dl>
            <p v-else class="text-center text-sm font-medium text-danger">No signature matches this code.</p>
        </Card>
    </GuestLayout>
</template>
