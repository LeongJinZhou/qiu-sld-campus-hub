<script setup lang="ts">
import { Link } from '@inertiajs/vue3';

defineProps<{ links: { url: string | null; label: string; active: boolean }[] }>();
</script>

<template>
    <nav v-if="links.length > 3" class="flex flex-wrap gap-1" aria-label="Pagination">
        <template v-for="link in links" :key="link.label">
            <Link v-if="link.url" :href="link.url" preserve-scroll class="rounded-control px-3 py-1.5 text-sm" :class="link.active ? 'bg-ink text-white' : 'hover:bg-surface-muted'">
                <span v-html="link.label" />
            </Link>
            <span v-else class="px-3 py-1.5 text-sm text-ink-muted" v-html="link.label" />
        </template>
    </nav>
</template>
