<script setup lang="ts">
import Card from '@/Components/ui/Card.vue';
import GuestLayout from '@/Layouts/GuestLayout.vue';
import { Head, Link } from '@inertiajs/vue3';
</script>

<template>
    <Head title="Not found in QIU records" />
    <GuestLayout>
        <Card class="grid gap-4 text-center">
            <p class="text-4xl" aria-hidden="true">🔍</p>
            <h1 class="text-lg font-semibold">Not found in QIU records</h1>
            <p class="text-sm text-ink-muted">
                We couldn't match this Google account to a current QIU student or staff record. Sign in with your
                <strong>@qiu.edu.my</strong> account, or contact the Student Life Division (SLD) if you think this is a mistake.
            </p>
            <Link href="/" class="text-sm font-medium text-brand-blue">Try another account</Link>
        </Card>
    </GuestLayout>
</template>
