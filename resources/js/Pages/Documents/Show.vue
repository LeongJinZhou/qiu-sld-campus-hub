<script setup lang="ts">
import ApprovalTracker from '@/Components/forms/ApprovalTracker.vue';
import ProposalView from '@/Components/forms/ProposalView.vue';
import Badge from '@/Components/ui/Badge.vue';
import Button from '@/Components/ui/Button.vue';
import Card from '@/Components/ui/Card.vue';
import AppLayout from '@/Layouts/AppLayout.vue';
import type { PresentedDocument } from '@/types';
import { Head, Link } from '@inertiajs/vue3';

defineProps<{ document: PresentedDocument; canEdit: boolean; downloads: string[] }>();
</script>

<template>
    <Head :title="document.type_label" />
    <AppLayout>
        <div class="grid gap-6 lg:grid-cols-[1fr_24rem]">
            <div class="grid content-start gap-4">
                <header class="flex flex-wrap items-center justify-between gap-3">
                    <div>
                        <h1 class="text-xl font-bold">{{ document.type_label }}</h1>
                        <p class="text-sm text-ink-muted">{{ document.club.name }}<template v-if="document.event"> · {{ document.event.name }}</template><template v-if="document.version"> · v{{ document.version }}</template></p>
                    </div>
                    <Badge :tone="document.status === 'approved' ? 'success' : document.status === 'returned' ? 'warning' : document.status === 'rejected' ? 'danger' : 'neutral'">
                        {{ document.status.replace('_', ' ') }}
                    </Badge>
                </header>
                <div v-if="downloads.length" class="flex flex-wrap gap-2">
                    <a v-for="format in downloads" :key="format" :href="`/documents/${document.id}/download/${format}`">
                        <Button variant="secondary" size="sm">Download official {{ format.toUpperCase() }}</Button>
                    </a>
                </div>
                <Link v-if="canEdit && document.event" :href="`/events/${document.event.slug}/proposal`"><Button>Edit and resubmit</Button></Link>
                <ProposalView v-if="document.type === 'proposal' && document.version" :document="document" />
                <Card v-else-if="document.type === 'attendance_list'">
                    <template #title>03 Event Attendance List</template>
                    <dl class="grid grid-cols-3 gap-3 text-center">
                        <div class="rounded-control bg-surface-muted p-3"><dd class="text-2xl font-bold">{{ (document.data as unknown as Record<string, number>).participants ?? 0 }}</dd><dt class="text-xs text-ink-muted">Participants</dt></div>
                        <div class="rounded-control bg-surface-muted p-3"><dd class="text-2xl font-bold">{{ (document.data as unknown as Record<string, number>).committee ?? 0 }}</dd><dt class="text-xs text-ink-muted">Committee</dt></div>
                        <div class="rounded-control bg-surface-muted p-3"><dd class="text-2xl font-bold">{{ (document.data as unknown as Record<string, number>).registered ?? 0 }}</dd><dt class="text-xs text-ink-muted">Registered</dt></div>
                    </dl>
                    <p class="mt-3 text-sm text-ink-muted">Download the official form above. Signatures appear on it as each person signs.</p>
                </Card>
            </div>
            <aside class="order-first lg:order-none">
                <Card>
                    <template #title>Approval progress</template>
                    <ApprovalTracker :progress="document.progress" :steps="document.steps" />
                </Card>
            </aside>
        </div>
    </AppLayout>
</template>
