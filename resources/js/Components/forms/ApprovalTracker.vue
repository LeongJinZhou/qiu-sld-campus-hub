<script setup lang="ts">
import Icon from '@/Components/ui/Icon.vue';
import type { ApprovalProgress, ApprovalStep } from '@/types';
import { computed, onMounted, ref } from 'vue';

// Shows exactly where a document is in its approval chain and whose approval it is waiting for.
const props = withDefaults(defineProps<{ progress: ApprovalProgress; steps?: ApprovalStep[]; compact?: boolean }>(), { steps: () => [], compact: false });

const percent = computed(() => (props.progress.total ? Math.round((props.progress.done / props.progress.total) * 100) : 0));
// Animate the bar from 0 on first paint so the movement is visible.
const shown = ref(0);
onMounted(() => requestAnimationFrame(() => (shown.value = percent.value)));

const headline = computed(() => {
    const p = props.progress;
    switch (p.status) {
        case 'approved':
            return { tone: 'success', icon: 'award' as const, title: 'Fully approved', body: `All ${p.total} signatures collected.` };
        case 'returned':
            return { tone: 'warning', icon: 'edit' as const, title: 'Returned to the club', body: 'Edit and resubmit. Earlier approvals stay unless the budget or dates change.' };
        case 'rejected':
            return { tone: 'danger', icon: 'x' as const, title: 'Rejected', body: 'This document will not continue.' };
        case 'in_review':
            return {
                tone: 'active',
                icon: 'clock' as const,
                title: `Waiting for ${p.current_role}`,
                body: p.waiting_on.length ? p.waiting_on.join(', ') : 'No one holds this role yet — SLD has been notified.',
            };
        default:
            return { tone: 'neutral', icon: 'file' as const, title: 'Draft', body: 'Not submitted yet.' };
    }
});
const tones: Record<string, string> = {
    success: 'from-success/15 to-brand-cyan/10 text-success',
    warning: 'from-warning/15 to-brand-yellow/10 text-warning',
    danger: 'from-danger/15 to-brand-magenta/10 text-danger',
    active: 'from-brand-magenta/10 to-brand-cyan/10 text-ink',
    neutral: 'from-surface-muted to-surface-muted text-ink-muted',
};
const barColour = computed(() => (props.progress.status === 'approved' ? 'bg-success' : props.progress.status === 'returned' ? 'bg-warning' : props.progress.status === 'rejected' ? 'bg-danger' : 'bg-gradient-to-r from-brand-orange via-brand-magenta to-brand-cyan'));
const stateOf = (s: ApprovalStep) => (s.decision === 'approved' ? 'done' : s.is_current ? 'current' : s.decision === 'returned' ? 'returned' : s.decision === 'rejected' ? 'rejected' : 'todo');
</script>

<template>
    <!-- Compact: one line for lists -->
    <div v-if="compact" class="grid gap-1.5">
        <div class="h-1.5 overflow-hidden rounded-full bg-surface-muted">
            <div class="h-full rounded-full transition-[width] duration-1000 ease-out" :class="[barColour, progress.status === 'in_review' ? 'animate-tracker-flow' : '']" :style="{ width: `${shown}%` }" />
        </div>
        <p class="text-xs text-ink-muted">
            {{ progress.done }}/{{ progress.total }} signed
            <template v-if="progress.status === 'in_review'"> · waiting for <strong class="text-ink">{{ progress.current_role }}</strong><template v-if="progress.waiting_on.length"> ({{ progress.waiting_on[0] }}<template v-if="progress.waiting_on.length > 1"> +{{ progress.waiting_on.length - 1 }}</template>)</template></template>
        </p>
    </div>

    <section v-else class="grid gap-5" aria-label="Approval progress">
        <!-- Where it is now -->
        <div class="grid gap-3 rounded-card bg-gradient-to-r p-4" :class="tones[headline.tone]">
            <div class="flex items-start gap-3">
                <span class="grid size-10 shrink-0 place-items-center rounded-full bg-surface shadow-card" :class="progress.status === 'in_review' ? 'animate-tracker-pulse' : ''">
                    <Icon :name="headline.icon" :size="20" />
                </span>
                <div class="grid gap-0.5">
                    <p class="font-semibold">{{ headline.title }}</p>
                    <p class="text-sm text-ink-muted">{{ headline.body }}</p>
                    <p v-if="progress.status === 'in_review' && progress.due_at" class="text-xs font-medium" :class="progress.overdue ? 'text-danger' : 'text-ink-muted'">
                        {{ progress.overdue ? 'Overdue since' : 'Due' }} {{ progress.due_at }}
                    </p>
                </div>
            </div>
            <div>
                <div class="mb-1 flex justify-between text-xs font-medium text-ink-muted"><span>{{ progress.done }} of {{ progress.total }} signed</span><span>{{ percent }}%</span></div>
                <div class="h-2.5 overflow-hidden rounded-full bg-surface/70">
                    <div class="h-full rounded-full transition-[width] duration-1000 ease-out" :class="[barColour, progress.status === 'in_review' ? 'animate-tracker-flow' : '']" :style="{ width: `${shown}%` }" />
                </div>
            </div>
        </div>

        <!-- The chain at a glance -->
        <ol v-if="steps.length" class="flex items-start overflow-x-auto pb-1">
            <li v-for="(step, i) in steps" :key="step.id" class="relative flex min-w-[5.5rem] flex-1 flex-col items-center text-center">
                <span v-if="i > 0" class="absolute right-1/2 top-4 h-0.5 w-full -translate-y-1/2 overflow-hidden bg-line" aria-hidden="true">
                    <span class="block h-full bg-success transition-[width] duration-700 ease-out" :style="{ width: stateOf(step) === 'done' || stateOf(step) === 'current' ? '100%' : '0%', transitionDelay: `${i * 120}ms` }" />
                </span>
                <span
                    class="relative z-10 grid size-8 place-items-center rounded-full text-xs font-bold"
                    :class="{
                        'animate-tracker-pop bg-success text-white': stateOf(step) === 'done',
                        'animate-tracker-pulse bg-brand-magenta text-white': stateOf(step) === 'current',
                        'bg-warning text-white': stateOf(step) === 'returned',
                        'bg-danger text-white': stateOf(step) === 'rejected',
                        'border-2 border-line bg-surface text-ink-muted': stateOf(step) === 'todo',
                    }"
                    :style="{ animationDelay: stateOf(step) === 'done' ? `${i * 120}ms` : undefined }"
                    :aria-current="step.is_current ? 'step' : undefined"
                >
                    <Icon v-if="stateOf(step) === 'done'" name="check" :size="14" :stroke-width="3" />
                    <template v-else>{{ step.step_no }}</template>
                </span>
                <span class="mt-1.5 px-1 text-[11px] font-medium leading-tight" :class="stateOf(step) === 'todo' ? 'text-ink-muted' : 'text-ink'">{{ step.role }}</span>
            </li>
        </ol>

        <!-- Detail per step -->
        <ol v-if="steps.length" class="grid gap-2">
            <li
                v-for="step in steps"
                :key="step.id"
                class="grid gap-1 rounded-control border p-3 text-sm transition"
                :class="step.is_current ? 'border-brand-magenta/40 bg-brand-magenta/5' : 'border-line'"
            >
                <div class="flex flex-wrap items-center justify-between gap-2">
                    <strong>{{ step.step_no }}. {{ step.role }}</strong>
                    <span class="text-xs font-medium" :class="{ 'text-success': stateOf(step) === 'done', 'text-brand-magenta': step.is_current, 'text-warning': stateOf(step) === 'returned', 'text-danger': stateOf(step) === 'rejected', 'text-ink-muted': stateOf(step) === 'todo' }">
                        {{ stateOf(step) === 'done' ? 'Signed' : step.is_current ? 'Waiting' : stateOf(step) === 'todo' ? 'Next' : step.decision }}
                    </span>
                </div>
                <p v-if="step.approver" class="text-ink-muted">{{ step.approver }}<template v-if="step.position"> · {{ step.position }}</template> · {{ step.decided_at }}</p>
                <p v-else-if="step.is_current" class="text-ink-muted">Waiting for {{ step.waiting_on.join(', ') || 'an approver' }}<template v-if="step.due_at"> · due {{ step.due_at }}</template></p>
                <p v-if="step.comment" class="rounded-control bg-surface-muted px-3 py-2">“{{ step.comment }}”</p>
                <a v-if="step.verification_code" :href="`/verify/${step.verification_code}`" class="text-xs text-brand-blue">Verify signature {{ step.verification_code }}</a>
            </li>
        </ol>
    </section>
</template>
