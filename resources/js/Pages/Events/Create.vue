<script setup lang="ts">
import Button from '@/Components/ui/Button.vue';
import Card from '@/Components/ui/Card.vue';
import Input from '@/Components/ui/Input.vue';
import AppLayout from '@/Layouts/AppLayout.vue';
import { Head, useForm } from '@inertiajs/vue3';
import { computed } from 'vue';

const props = defineProps<{ club: { slug: string; name: string }; earliest: { small: string; large: string }; largeThreshold: number }>();

const form = useForm({ name: '', starts_at: '', ends_at: '', expected_participants: 30 });
const minDate = computed(() => (form.expected_participants >= props.largeThreshold ? props.earliest.large : props.earliest.small));
</script>

<template>
    <Head title="New event" />
    <AppLayout>
        <Card class="mx-auto max-w-xl">
            <template #title>New event · {{ club.name }}</template>
            <form class="grid gap-4" @submit.prevent="form.post(`/clubs/${club.slug}/events`)">
                <Input v-model="form.name" label="Event name" required :error="form.errors.name" />
                <Input v-model="form.expected_participants" type="number" label="Expected participants" required :error="form.errors.expected_participants" />
                <p class="rounded-control bg-brand-cyan/10 px-3 py-2 text-sm">
                    {{ form.expected_participants >= largeThreshold ? `${largeThreshold} or more` : `Under ${largeThreshold}` }} participants: submit at least
                    {{ form.expected_participants >= largeThreshold ? '3 months' : '1.5 months' }} ahead. Earliest event date if you submit today: <strong>{{ minDate }}</strong>.
                </p>
                <div class="grid gap-4 sm:grid-cols-2">
                    <Input v-model="form.starts_at" type="datetime-local" label="Starts" required :error="form.errors.starts_at" />
                    <Input v-model="form.ends_at" type="datetime-local" label="Ends" required :error="form.errors.ends_at" />
                </div>
                <Button type="submit" :loading="form.processing" class="justify-self-start">Create and open proposal</Button>
            </form>
        </Card>
    </AppLayout>
</template>
