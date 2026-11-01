<script setup lang="ts">
import { watchDebounced } from '@vueuse/core';
import { ref, useId } from 'vue';
import Icon from './Icon.vue';

export interface Person {
    student_number: string;
    name: string;
    email: string | null;
    programme: string | null;
}

/**
 * Type a name, QIU email or student ID; matching students appear as you type. Picking one fills the name,
 * email and student ID from university records (v-model is the student ID).
 */
withDefaults(
    defineProps<{
        label?: string;
        error?: string;
        placeholder?: string;
        required?: boolean;
    }>(),
    {
        label: 'Name, email or student ID',
        placeholder: 'Start typing a name or email…',
        required: false,
    },
);
const model = defineModel<string>({ default: '' });
const emit = defineEmits<{ select: [person: Person | null] }>();

const id = useId();
const q = ref('');
const results = ref<Person[]>([]);
const picked = ref<Person | null>(null);
const open = ref(false);
const loading = ref(false);
const active = ref(0);

watchDebounced(
    q,
    async (value) => {
        if (picked.value || value.trim().length < 1) {
            results.value = [];
            return;
        }
        loading.value = true;
        try {
            const res = await fetch(`/people/lookup?q=${encodeURIComponent(value.trim())}`, { headers: { Accept: 'application/json' } });
            results.value = res.ok ? ((await res.json()) as Person[]) : [];
            active.value = 0;
            open.value = true;
        } finally {
            loading.value = false;
        }
    },
    { debounce: 250 },
);

function choose(person: Person): void {
    picked.value = person;
    model.value = person.student_number;
    open.value = false;
    emit('select', person);
}
function clear(): void {
    picked.value = null;
    model.value = '';
    q.value = '';
    emit('select', null);
}
function onKey(e: KeyboardEvent): void {
    if (!open.value || !results.value.length) return;
    if (e.key === 'ArrowDown') active.value = (active.value + 1) % results.value.length;
    else if (e.key === 'ArrowUp') active.value = (active.value - 1 + results.value.length) % results.value.length;
    else if (e.key === 'Enter') choose(results.value[active.value] as Person);
    else if (e.key === 'Escape') open.value = false;
    else return;
    e.preventDefault();
}
const closeSoon = (): void => void window.setTimeout(() => (open.value = false), 150);
defineExpose({ clear });
</script>

<template>
    <div class="relative grid gap-1.5">
        <label :for="id" class="text-sm font-medium text-ink">{{ label }}<span v-if="required" class="text-danger"> *</span></label>
        <Transition name="fade-up" mode="out-in">
            <div v-if="picked" key="picked" class="flex items-center gap-3 rounded-control border border-brand-cyan/50 bg-brand-cyan/5 px-3 py-2 text-sm">
                <span class="grid size-8 shrink-0 place-items-center rounded-full bg-gradient-to-br from-brand-cyan to-brand-blue text-xs font-bold text-white">{{
                    picked.name.slice(0, 1)
                }}</span>
                <span class="min-w-0 flex-1">
                    <span class="block truncate font-semibold">{{ picked.name }}</span>
                    <span class="block truncate text-xs text-ink-muted"
                        >{{ picked.student_number }} · {{ picked.email }}<template v-if="picked.programme"> · {{ picked.programme }}</template></span
                    >
                </span>
                <button type="button" class="text-xs font-medium text-brand-blue" @click="clear">Change</button>
            </div>
            <div v-else key="search" class="relative">
                <Icon name="search" :size="16" class="pointer-events-none absolute left-3 top-1/2 -translate-y-1/2 text-ink-muted" />
                <input
                    :id="id"
                    v-model="q"
                    type="search"
                    role="combobox"
                    autocomplete="off"
                    :aria-expanded="open"
                    :aria-controls="`${id}-list`"
                    :aria-invalid="!!error"
                    :placeholder="placeholder"
                    class="h-10 w-full rounded-control border border-line bg-surface pl-9 pr-9 text-sm outline-none transition focus:border-brand-cyan focus:ring-2 focus:ring-brand-cyan/30 aria-invalid:border-danger"
                    @keydown="onKey"
                    @focus="open = results.length > 0"
                    @blur="closeSoon"
                />
                <span v-if="loading" class="absolute right-3 top-1/2 size-4 -translate-y-1/2 animate-spin rounded-full border-2 border-brand-cyan border-t-transparent" />
            </div>
        </Transition>
        <Transition name="fade-up">
            <ul
                v-if="open && !picked && q.trim().length >= 1"
                :id="`${id}-list`"
                role="listbox"
                class="absolute inset-x-0 top-full z-30 mt-1 max-h-72 overflow-y-auto rounded-control border border-line bg-surface py-1 shadow-pop"
            >
                <li
                    v-for="(p, i) in results"
                    :key="p.student_number"
                    role="option"
                    :aria-selected="i === active"
                    class="cursor-pointer px-3 py-2 text-sm"
                    :class="i === active ? 'bg-surface-muted' : ''"
                    @mousedown.prevent="choose(p)"
                    @mouseenter="active = i"
                >
                    <span class="block font-medium">{{ p.name }}</span>
                    <span class="block text-xs text-ink-muted"
                        >{{ p.student_number }} · {{ p.email }}<template v-if="p.programme"> · {{ p.programme }}</template></span
                    >
                </li>
                <li v-if="!results.length && !loading" class="px-3 py-2 text-sm text-ink-muted">No active student matches.</li>
            </ul>
        </Transition>
        <p v-if="error" class="text-xs text-danger">{{ error }}</p>
    </div>
</template>
