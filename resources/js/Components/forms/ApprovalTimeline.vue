<script setup lang="ts">
import Badge from '@/Components/ui/Badge.vue';
import type { ApprovalStep } from '@/types';

defineProps<{ steps: ApprovalStep[] }>();

const tone = { approved: 'success', returned: 'warning', rejected: 'danger', pending: 'neutral' } as const;
</script>

<template>
    <ol class="grid gap-3">
        <li v-for="step in steps" :key="step.id" class="grid grid-cols-[2rem_1fr] gap-3">
            <span
                class="grid size-8 place-items-center rounded-full text-xs font-semibold"
                :class="step.decision === 'approved' ? 'bg-success text-white' : step.is_current ? 'bg-ink text-white' : 'bg-surface-muted text-ink-muted'"
            >
                {{ step.decision === 'approved' ? '✓' : step.step_no }}
            </span>
            <div class="grid gap-0.5 text-sm">
                <div class="flex flex-wrap items-center gap-2">
                    <strong>{{ step.role }}</strong>
                    <Badge :tone="tone[step.decision]">{{ step.is_current ? 'waiting' : step.decision }}</Badge>
                </div>
                <p v-if="step.approver" class="text-ink-muted">{{ step.approver }}<template v-if="step.position"> · {{ step.position }}</template> · {{ step.decided_at }}</p>
                <p v-else-if="step.is_current && step.due_at" class="text-ink-muted">Due {{ step.due_at }}</p>
                <p v-if="step.comment" class="rounded-control bg-surface-muted px-3 py-2">“{{ step.comment }}”</p>
                <a v-if="step.verification_code" :href="`/verify/${step.verification_code}`" class="text-xs text-brand-blue">Verify {{ step.verification_code }}</a>
            </div>
        </li>
    </ol>
</template>
