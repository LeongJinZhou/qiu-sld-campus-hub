<script setup lang="ts">
import { computed } from 'vue';

const props = withDefaults(
    defineProps<{ src?: string | null; name: string; size?: 'xs' | 'sm' | 'md' | 'lg' | 'xl'; ring?: boolean }>(),
    { src: null, size: 'md', ring: false },
);

const sizes = { xs: 'size-6 text-[10px]', sm: 'size-9 text-xs', md: 'size-14 text-base', lg: 'size-20 text-xl', xl: 'size-28 text-3xl' };
const initials = computed(() =>
    props.name
        .split(/\s+/)
        .filter((word) => /^\p{Lu}/u.test(word))
        .slice(0, 2)
        .map((word) => word[0])
        .join('') || '?',
);
</script>

<template>
    <span class="inline-grid shrink-0 place-items-center rounded-full" :class="ring ? 'ring-story p-[3px]' : ''">
        <span class="grid place-items-center overflow-hidden rounded-full bg-surface" :class="[sizes[size], ring ? 'border-2 border-surface' : '']">
            <img v-if="src" :src="src" :alt="name" class="size-full object-cover" referrerpolicy="no-referrer" />
            <span v-else class="grid size-full place-items-center bg-gradient-to-br from-brand-orange to-brand-magenta font-semibold text-white" aria-hidden="true">
                {{ initials }}
            </span>
        </span>
    </span>
</template>
