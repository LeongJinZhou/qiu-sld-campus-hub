<script setup lang="ts">
defineProps<{ tabs: { key: string; label: string }[] }>();
const active = defineModel<string>({ required: true });
</script>

<template>
    <div>
        <div role="tablist" class="flex gap-6 overflow-x-auto border-b border-line">
            <button
                v-for="tab in tabs"
                :key="tab.key"
                role="tab"
                type="button"
                :aria-selected="active === tab.key"
                class="-mb-px whitespace-nowrap border-b-2 py-3 text-sm font-medium transition"
                :class="active === tab.key ? 'border-ink text-ink' : 'border-transparent text-ink-muted hover:text-ink'"
                @click="active = tab.key"
            >
                {{ tab.label }}
            </button>
        </div>
        <div role="tabpanel" class="pt-4">
            <slot :name="active" />
        </div>
    </div>
</template>
