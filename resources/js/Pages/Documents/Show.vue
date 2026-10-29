<script setup lang="ts">
import ApprovalTimeline from '@/Components/forms/ApprovalTimeline.vue';
import ProposalView from '@/Components/forms/ProposalView.vue';
import Badge from '@/Components/ui/Badge.vue';
import Button from '@/Components/ui/Button.vue';
import Card from '@/Components/ui/Card.vue';
import AppLayout from '@/Layouts/AppLayout.vue';
import type { PresentedDocument } from '@/types';
import { Head, Link } from '@inertiajs/vue3';

defineProps<{ document: PresentedDocument; canEdit: boolean }>();
</script>

<template>
    <Head :title="document.type_label" />
    <AppLayout>
        <div class="grid gap-6 lg:grid-cols-[1fr_20rem]">
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
                <Link v-if="canEdit && document.event" :href="`/events/${document.event.slug}/proposal`"><Button>Edit and resubmit</Button></Link>
                <ProposalView v-if="document.type === 'proposal' && document.version" :document="document" />
            </div>
            <aside>
                <Card>
                    <template #title>Approvals</template>
                    <ApprovalTimeline :steps="document.steps" />
                </Card>
            </aside>
        </div>
    </AppLayout>
</template>
