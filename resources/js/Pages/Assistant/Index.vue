<script setup lang="ts">
import Button from '@/Components/ui/Button.vue';
import Icon from '@/Components/ui/Icon.vue';
import AppLayout from '@/Layouts/AppLayout.vue';
import { Head, Link, router, useForm } from '@inertiajs/vue3';
import { nextTick, onMounted, ref, watch } from 'vue';

interface Message {
    id: number;
    role: 'user' | 'assistant';
    content: string;
    links: { title: string; kind: string; url: string }[];
    at: string | null;
}
const props = defineProps<{ messages: Message[] }>();

const form = useForm({ question: '' });
const list = ref<HTMLElement | null>(null);
const toBottom = (): void => void nextTick(() => list.value?.lastElementChild?.scrollIntoView({ block: 'end' }));
onMounted(toBottom);
watch(() => props.messages.length, toBottom);

const ask = (): void => {
    if (!form.question.trim() || form.processing) return;
    form.post('/assistant', { preserveScroll: true, onSuccess: () => form.reset() });
};
const forget = (m: Message): void => router.delete(`/assistant/${m.id}`, { preserveScroll: true });
const clearAll = (): void => {
    if (confirm('Delete your whole assistant history? This cannot be undone.')) router.delete('/assistant', { preserveScroll: true });
};
const examples = ['What do I need to sign this week?', 'When is the next event of my club?', 'How do I start a new club?', 'Which weekly reports are still due?'];
</script>

<template>
    <Head title="Assistant" />
    <AppLayout>
        <div class="grid gap-4">
            <header class="flex flex-wrap items-start justify-between gap-3">
                <div>
                    <h1 class="flex items-center gap-2 text-2xl font-bold"><Icon name="sparkles" /> Assistant</h1>
                    <p class="text-sm text-ink-muted">Answers from what you are allowed to open, with links straight to it. Only you see this history.</p>
                </div>
                <Button v-if="messages.length" variant="secondary" size="sm" @click="clearAll">Delete history</Button>
            </header>

            <ol ref="list" class="grid gap-3" aria-live="polite">
                <li v-if="!messages.length" class="grid gap-2 rounded-card border border-dashed border-line p-5 text-sm text-ink-muted">
                    <p>Ask anything about your clubs, events, approvals and deadlines. Try:</p>
                    <div class="flex flex-wrap gap-2">
                        <button v-for="e in examples" :key="e" type="button" class="rounded-full border border-line px-3 py-1 hover:bg-surface-muted" @click="form.question = e">
                            {{ e }}
                        </button>
                    </div>
                </li>
                <li v-for="m in messages" :key="m.id" class="group flex" :class="m.role === 'user' ? 'justify-end' : 'justify-start'">
                    <div
                        class="relative grid max-w-[85%] gap-2 rounded-card px-4 py-3 text-sm"
                        :class="m.role === 'user' ? 'bg-brand-blue text-white' : 'border border-line bg-surface shadow-card'"
                    >
                        <p class="whitespace-pre-line">{{ m.content }}</p>
                        <ul v-if="m.links.length" class="grid gap-1">
                            <li v-for="l in m.links" :key="l.url">
                                <Link :href="l.url" class="inline-flex items-center gap-1 font-medium text-brand-blue hover:underline"
                                    ><span class="rounded bg-surface-muted px-1.5 text-[11px] uppercase text-ink-muted">{{ l.kind }}</span> {{ l.title }}</Link
                                >
                            </li>
                        </ul>
                        <button
                            type="button"
                            class="absolute -top-2 right-1 hidden rounded-full bg-surface px-1.5 text-xs text-ink-muted shadow-card group-hover:block focus:block"
                            aria-label="Delete this message"
                            @click="forget(m)"
                        >
                            ×
                        </button>
                    </div>
                </li>
                <li v-if="form.processing" class="text-sm text-ink-muted">Thinking…</li>
            </ol>

            <form class="sticky bottom-20 flex gap-2 rounded-card border border-line bg-surface p-2 shadow-card md:bottom-4" @submit.prevent="ask">
                <input
                    v-model="form.question"
                    type="text"
                    maxlength="1000"
                    class="h-11 min-w-0 flex-1 bg-transparent px-2 text-[15px] outline-none"
                    placeholder="Ask about your clubs, events, approvals…"
                    aria-label="Your question"
                />
                <Button type="submit" :loading="form.processing" :disabled="!form.question.trim()">Ask</Button>
            </form>
            <p v-if="form.errors.question" class="text-sm text-danger">{{ form.errors.question }}</p>
        </div>
    </AppLayout>
</template>
