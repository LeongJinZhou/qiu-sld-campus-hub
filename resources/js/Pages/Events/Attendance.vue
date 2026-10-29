<script setup lang="ts">
import Badge from '@/Components/ui/Badge.vue';
import Button from '@/Components/ui/Button.vue';
import Card from '@/Components/ui/Card.vue';
import Input from '@/Components/ui/Input.vue';
import QrCode from '@/Components/ui/QrCode.vue';
import QrScanner from '@/Components/ui/QrScanner.vue';
import Table from '@/Components/ui/Table.vue';
import AppLayout from '@/Layouts/AppLayout.vue';
import { Head, Link, router, useForm } from '@inertiajs/vue3';
import { useIntervalFn } from '@vueuse/core';
import { ref } from 'vue';

const props = defineProps<{
    event: { slug: string; name: string; is_outdoor: boolean; attendance_open: boolean; starts_at: string };
    action: 'in' | 'out';
    qrSvg: string | null;
    rows: { id: number; name: string; student_number: string | null; role: string; in: string | null; out: string | null; method: string }[];
    counts: { registered: number; present: number };
}>();

// The on-screen code rotates every minute; refresh it (and the list) every 30 s.
useIntervalFn(() => router.reload({ only: ['qrSvg', 'rows', 'counts'] }), 30_000);

const setAction = (action: 'in' | 'out'): void => router.get(`/events/${props.event.slug}/attendance`, { action }, { preserveState: true, replace: true });

const scanning = ref(false);
let lastScan = '';
function onScan(code: string): void {
    if (code === lastScan) return;
    lastScan = code;
    router.post(`/events/${props.event.slug}/attendance`, { action: props.action, code }, { preserveScroll: true, onFinish: () => setTimeout(() => (lastScan = ''), 3000) });
}

const manual = useForm({ action: 'manual', student_number: '' });
const addManual = (): void => manual.post(`/events/${props.event.slug}/attendance`, { preserveScroll: true, onSuccess: () => manual.reset('student_number') });

const columns = [
    { key: 'name' as const, label: 'Name', sortable: true },
    { key: 'student_number' as const, label: 'Student ID' },
    { key: 'role' as const, label: 'Role', sortable: true },
    { key: 'in' as const, label: 'In' },
    { key: 'out' as const, label: 'Out' },
    { key: 'method' as const, label: 'How' },
];
</script>

<template>
    <Head :title="`Attendance · ${event.name}`" />
    <AppLayout>
        <div class="grid gap-6">
            <header class="flex flex-wrap items-center justify-between gap-3">
                <div>
                    <h1 class="text-xl font-bold">Attendance</h1>
                    <p class="text-sm text-ink-muted">{{ event.name }} · {{ counts.present }} present / {{ counts.registered }} registered</p>
                </div>
                <div class="flex gap-2">
                    <a :href="`/events/${event.slug}/attendance/export`"><Button variant="secondary">Download 03 Attendance List (XLSX)</Button></a>
                    <Link v-if="event.is_outdoor" :href="`/events/${event.slug}/indemnities`"><Button variant="ghost">Indemnities</Button></Link>
                </div>
            </header>

            <p v-if="!event.attendance_open" class="rounded-card bg-warning/10 px-4 py-3 text-sm text-warning">Attendance opens once the proposal is approved.</p>

            <div class="grid gap-6 lg:grid-cols-2">
                <Card>
                    <template #title>Show this to participants</template>
                    <template #actions>
                        <div class="flex gap-1 rounded-control bg-surface-muted p-1 text-sm">
                            <button v-for="a in ['in', 'out'] as const" :key="a" type="button" class="rounded-control px-3 py-1" :class="action === a ? 'bg-surface font-semibold shadow-card' : ''" @click="setAction(a)">Clock {{ a }}</button>
                        </div>
                    </template>
                    <QrCode v-if="qrSvg" :svg="qrSvg" :label="`Scan to clock ${action} · refreshes every minute`" />
                    <p v-if="event.is_outdoor" class="mt-3 text-center text-xs text-ink-muted">Outdoor event: anyone without a signed indemnity is blocked.</p>
                </Card>

                <Card>
                    <template #title>Scan a student's profile QR</template>
                    <div class="grid gap-4">
                        <QrScanner v-if="scanning" @scan="onScan" />
                        <Button :variant="scanning ? 'secondary' : 'primary'" @click="scanning = !scanning">{{ scanning ? 'Stop camera' : `Start scanner (clock ${action})` }}</Button>
                        <form class="flex items-end gap-2" @submit.prevent="addManual">
                            <Input v-model="manual.student_number" label="Add by student ID" placeholder="QIU-202407-0001" class="flex-1" />
                            <Button type="submit" variant="secondary" :loading="manual.processing">Add</Button>
                        </form>
                    </div>
                </Card>
            </div>

            <Table :columns="columns" :rows="rows" row-key="id">
                <template #cell-role="{ row }"><Badge :tone="row.role === 'committee' ? 'info' : 'neutral'">{{ row.role }}</Badge></template>
            </Table>
        </div>
    </AppLayout>
</template>
