<script setup lang="ts">
import ApplicationForm from '@/Components/clubs/ApplicationForm.vue';
import AppLayout from '@/Layouts/AppLayout.vue';
import { Head, Link } from '@inertiajs/vue3';

defineProps<{
    categories: { id: number; name: string }[];
    advisors: { id: number; name: string }[];
    positions: string[];
    me: { student_number: string; name: string } | null;
    mine: { id: number; name: string; status: string }[];
}>();
</script>

<template>
    <Head title="Start a new club" />
    <AppLayout>
        <div class="grid gap-5">
            <header>
                <h1 class="text-2xl font-bold">Start a new club</h1>
                <p class="text-sm text-ink-muted">You apply and sign as President → your advisor signs → SLD reviews. The club opens once SLD approves.</p>
            </header>
            <section v-if="mine.length" class="grid gap-2 rounded-card border border-line bg-surface p-4 shadow-card">
                <h2 class="text-sm font-semibold">Your applications</h2>
                <Link v-for="a in mine" :key="a.id" :href="`/club-applications/${a.id}`" class="flex justify-between text-sm hover:underline">
                    <span>{{ a.name }}</span
                    ><span class="text-ink-muted">{{ a.status }}</span>
                </Link>
            </section>
            <ApplicationForm action="/clubs-apply" :categories="categories" :advisors="advisors" :positions="positions" :me="me" />
        </div>
    </AppLayout>
</template>
