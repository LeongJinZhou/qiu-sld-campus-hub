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
            <ul class="grid grid-cols-3 gap-x-3 gap-y-6 sm:grid-cols-4 lg:grid-cols-6">
                <li v-for="club in clubs" :key="club.slug">
                    <Link :href="`/clubs/${club.slug}`" class="grid justify-items-center gap-2 text-center">
                        <Avatar :src="club.logo_url" :name="club.name" size="lg" ring />
                        <span class="line-clamp-2 text-xs font-medium">{{ club.name }}</span>
                        <span v-if="club.category" class="text-[11px] text-ink-muted">{{ club.category }}</span>
                    </Link>
                </li>
            </ul>
        </div>
    </AppLayout>
</template>
