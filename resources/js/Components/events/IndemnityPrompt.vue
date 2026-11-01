<script setup lang="ts">
import Button from '@/Components/ui/Button.vue';
import Icon from '@/Components/ui/Icon.vue';
import Modal from '@/Components/ui/Modal.vue';
import { Link } from '@inertiajs/vue3';
import { onMounted, ref } from 'vue';

/** Pops up once a place is confirmed for an outdoor event: sign the indemnity to get the ticket. */
const props = defineProps<{
    slug: string;
    name: string;
    closesAt?: string | null;
}>();
const open = ref(false);
onMounted(() => window.setTimeout(() => (open.value = true), 350));
</script>

<template>
    <div class="flex items-center gap-3 rounded-card border border-warning/40 bg-warning/10 p-3 text-sm">
        <Icon name="file" :size="20" class="shrink-0 text-warning" />
        <p class="flex-1">One step left: sign the indemnity form to get your ticket.</p>
        <Link :href="`/events/${props.slug}/indemnity`" class="font-semibold text-brand-blue">Sign</Link>
    </div>
    <Modal v-model:open="open">
        <div class="grid justify-items-center gap-4 py-2 text-center">
            <span class="grid size-16 animate-float-soft place-items-center rounded-full bg-gradient-to-br from-brand-orange to-brand-magenta text-white shadow-pop">
                <Icon name="file" :size="30" />
            </span>
            <div class="grid gap-1">
                <h2 class="text-lg font-bold">Almost there!</h2>
                <p class="text-sm text-ink-muted">{{ name }} is an outdoor event. Sign the indemnity form and your ticket appears. Your name and details are already filled in.</p>
                <p v-if="closesAt" class="text-xs text-ink-muted">Signing closes {{ closesAt }}.</p>
            </div>
            <Link :href="`/events/${props.slug}/indemnity`" class="w-full"><Button block size="lg">Sign now</Button></Link>
            <button type="button" class="text-sm text-ink-muted underline" @click="open = false">Later (you will be reminded)</button>
        </div>
    </Modal>
</template>
