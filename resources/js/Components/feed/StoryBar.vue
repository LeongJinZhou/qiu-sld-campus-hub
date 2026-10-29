<script setup lang="ts">
import Avatar from '@/Components/ui/Avatar.vue';
import type { StoryGroup } from '@/types';
import { ref } from 'vue';
import StoryViewer from './StoryViewer.vue';

defineProps<{ groups: StoryGroup[] }>();
const open = ref<StoryGroup | null>(null);
</script>

<template>
    <div v-if="groups.length" class="flex gap-4 overflow-x-auto pb-1 [scrollbar-width:none]">
        <button v-for="group in groups" :key="group.slug" type="button" class="grid w-16 shrink-0 justify-items-center gap-1" @click="open = group">
            <Avatar :src="group.logo_url" :name="group.name" size="md" ring />
            <span class="w-full truncate text-center text-[11px]">{{ group.name }}</span>
        </button>
    </div>
    <StoryViewer :group="open" @close="open = null" />
</template>
