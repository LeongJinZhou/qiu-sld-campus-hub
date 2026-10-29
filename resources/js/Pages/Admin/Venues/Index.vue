<script setup lang="ts">
import Badge from '@/Components/ui/Badge.vue';
import Button from '@/Components/ui/Button.vue';
import Card from '@/Components/ui/Card.vue';
import Input from '@/Components/ui/Input.vue';
import PortalLayout from '@/Layouts/PortalLayout.vue';
import { Head, router } from '@inertiajs/vue3';
import { reactive } from 'vue';

defineProps<{
    bookings: { id: number; club: string; by: string; type: string; summary: string; activity: string; status: string; sld_note: string | null }[];
}>();

const notes = reactive<Record<number, string>>({});
const decide = (id: number, confirm: boolean): void => router.post(`/admin/venues/${id}`, { confirm, note: notes[id] ?? null }, { preserveScroll: true });
const tone = (s: string): 'success' | 'danger' | 'warning' => (s === 'confirmed' ? 'success' : s === 'declined' ? 'danger' : 'warning');
</script>

<template>
    <Head title="Venue requests" />
    <PortalLayout title="Venue requests">
        <Card>
            <ul class="divide-y divide-line">
                <li v-for="b in bookings" :key="b.id" class="grid gap-2 py-4 text-sm">
                    <div class="flex flex-wrap items-center justify-between gap-2">
                        <span><strong>{{ b.club }}</strong> · {{ b.type }} · by {{ b.by }}</span>
                        <Badge :tone="tone(b.status)">{{ b.status }}</Badge>
                    </div>
                    <p>{{ b.summary }}</p>
                    <p class="text-ink-muted">{{ b.activity }}</p>
                    <p v-if="b.sld_note" class="text-xs text-ink-muted">Note: {{ b.sld_note }}</p>
                    <div v-if="b.status === 'pending'" class="flex flex-wrap items-end gap-2">
                        <div class="min-w-48 flex-1"><Input v-model="notes[b.id]" placeholder="Note to the club (optional)" /></div>
                        <Button size="sm" @click="decide(b.id, true)">Confirm</Button>
                        <Button size="sm" variant="secondary" @click="decide(b.id, false)">Decline</Button>
                    </div>
                </li>
                <li v-if="!bookings.length" class="py-3 text-sm text-ink-muted">No venue requests.</li>
            </ul>
        </Card>
    </PortalLayout>
</template>
