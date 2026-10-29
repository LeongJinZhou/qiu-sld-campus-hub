<script setup lang="ts">
import Avatar from '@/Components/ui/Avatar.vue';
import EmptyState from '@/Components/ui/EmptyState.vue';
import FilterBar from '@/Components/ui/FilterBar.vue';
import AppLayout from '@/Layouts/AppLayout.vue';
import { Head, Link, router } from '@inertiajs/vue3';
import { watchDebounced } from '@vueuse/core';
import { ref } from 'vue';

const props = defineProps<{ search: string; clubs: { slug: string; name: string; logo_url: string | null; category: string | null }[] }>();

const search = ref(props.search);
watchDebounced(search, (value) => router.get('/clubs', value ? { search: value } : {}, { preserveState: true, replace: true }), { debounce: 300 });
</script>

<template>
    <Head title="Clubs" />
    <AppLayout>
        <div class="grid gap-6">
            <FilterBar v-model:search="search" placeholder="Search clubs" />
            <EmptyState v-if="clubs.length === 0" title="No clubs found" description="Try a different name." />
            <h1 class="text-xl font-bold">Discover clubs</h1>
            <ul class="grid grid-cols-2 gap-3 sm:grid-cols-3">
                <li v-for="club in clubs" :key="club.slug">
                    <Link :href="`/clubs/${club.slug}`" class="group grid overflow-hidden rounded-card border border-line bg-surface text-center shadow-card transition hover:-translate-y-0.5 hover:shadow-pop">
                        <span class="h-14 bg-gradient-to-r from-brand-yellow via-brand-orange to-brand-magenta" />
                        <span class="-mt-9 grid justify-items-center gap-1.5 px-3 pb-4">
                            <span class="rounded-full bg-surface p-0.5"><Avatar :src="club.logo_url" :name="club.name" size="lg" ring /></span>
                            <span class="line-clamp-2 text-sm font-semibold">{{ club.name }}</span>
                            <span v-if="club.category" class="rounded-full bg-surface-muted px-2 py-0.5 text-[11px] text-ink-muted">{{ club.category }}</span>
                        </span>
                    </Link>
                </li>
            </ul>
        </div>
    </AppLayout>
</template>
