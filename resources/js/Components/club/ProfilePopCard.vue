<script setup lang="ts">
import Avatar from '@/Components/ui/Avatar.vue';
import Modal from '@/Components/ui/Modal.vue';
import type { MemberCard } from '@/types';

defineProps<{ member: MemberCard | null }>();
const open = defineModel<boolean>('open', { default: false });
</script>

<template>
    <Modal v-model:open="open">
        <div v-if="member" class="grid justify-items-center gap-3 text-center">
            <Avatar :src="member.photo_url" :name="member.name" size="xl" ring />
            <div>
                <p class="text-lg font-semibold">{{ member.name }}</p>
                <p class="text-gradient-warm font-medium">{{ member.position }}</p>
                <p class="text-sm text-ink-muted">
                    {{ [member.programme_short, member.year_group, member.title].filter(Boolean).join(' · ') }}
                </p>
            </div>
            <a :href="member.profile_url" class="mt-2 rounded-control bg-ink px-4 py-2 text-sm font-medium text-white">View full profile</a>
        </div>
    </Modal>
</template>
