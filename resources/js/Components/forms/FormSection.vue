<script setup lang="ts">
/**
 * One part of a form. `required` marks it with a red *, `flag` highlights it with the reviewer's note (a returned
 * proposal), and `editors` shows who else is working on this part right now.
 */
defineProps<{
    title: string;
    hint?: string;
    required?: boolean;
    section?: string;
    flag?: string | null;
    editors?: { id: number; name: string; photo_url: string | null }[];
    /** Pointing someone at a problem: 'flash' three times, or 'blink' until they touch the part. */
    alert?: 'flash' | 'blink' | null;
}>();
</script>

<template>
    <section
        :data-section="section"
        class="grid scroll-mt-24 gap-4 rounded-card border bg-surface p-5 shadow-card transition-[box-shadow,border-color] duration-300"
        :class="[
            alert === 'flash' ? 'problem-flash' : alert === 'blink' ? 'problem-blink' : '',
            flag
                ? 'border-danger ring-4 ring-danger/15'
                : editors?.length
                  ? 'border-brand-cyan ring-2 ring-brand-cyan/20'
                  : 'border-line',
        ]"
    >
        <header class="flex flex-wrap items-start justify-between gap-2">
            <div>
                <h2 class="text-base font-semibold">
                    {{ title
                    }}<span v-if="required" class="text-danger"> *</span>
                </h2>
                <p v-if="hint" class="text-sm text-ink-muted">{{ hint }}</p>
            </div>
            <span
                v-if="editors?.length"
                class="flex items-center gap-1.5 rounded-full bg-brand-cyan/10 px-2 py-1 text-xs font-medium text-brand-blue"
            >
                <span class="relative flex size-2"
                    ><span
                        class="absolute inline-flex size-full animate-ping rounded-full bg-brand-cyan opacity-70" /><span
                        class="relative inline-flex size-2 rounded-full bg-brand-cyan"
                /></span>
                {{
                    editors.map((e) => e.name.split(" ")[0]).join(", ")
                }}
                editing
            </span>
        </header>
        <p
            v-if="flag"
            class="flex gap-2 rounded-control bg-danger/10 px-3 py-2 text-sm text-danger"
            role="alert"
        >
            <strong class="shrink-0">To fix:</strong> {{ flag }}
        </p>
        <slot />
    </section>
</template>
