import Button from '@/Components/ui/Button';
import Icon from '@/Components/ui/Icon';
import AppLayout from '@/Layouts/AppLayout';
import { Head, Link, router, useForm } from '@inertiajs/react';
import { useEffect, useRef, type FormEvent } from 'react';

interface Message {
    id: number;
    role: 'user' | 'assistant';
    content: string;
    links: { title: string; kind: string; url: string }[];
    followups?: string[];
    at: string | null;
}
const EXAMPLES = ['What do I need to sign this week?', 'When is the next event of my club?', 'How do I start a new club?', 'Which weekly reports are still due?'];

export default function Index({ messages }: { messages: Message[] }) {
    const form = useForm({ question: '' });
    const list = useRef<HTMLOListElement>(null);
    useEffect(() => list.current?.lastElementChild?.scrollIntoView({ block: 'end' }), [messages.length]);

    const send = (question: string): void => {
        if (!question.trim() || form.processing) return;
        form.transform(() => ({ question }));
        form.post('/assistant', { preserveScroll: true, onSuccess: () => form.reset() });
    };
    const ask = (e: FormEvent): void => {
        e.preventDefault();
        send(form.data.question);
    };
    const clearAll = (): void => {
        if (confirm('Delete your whole assistant history? This cannot be undone.')) router.delete('/assistant', { preserveScroll: true });
    };
    const lastId = messages[messages.length - 1]?.id;

    return (
        <AppLayout>
            <Head title="Assistant" />
            <div className="grid gap-4">
                <header className="flex flex-wrap items-start justify-between gap-3">
                    <div>
                        <h1 className="flex items-center gap-2 text-2xl font-bold">
                            <Icon name="sparkles" /> Assistant
                        </h1>
                        <p className="text-sm text-ink-muted">
                            Answers only from what your role may see (fees and events for everyone; a club's money and members for its own committee; everything for SLD). Only you see this history.
                        </p>
                    </div>
                    {messages.length > 0 && (
                        <Button variant="secondary" size="sm" onClick={clearAll}>
                            Delete history
                        </Button>
                    )}
                </header>
                <ol ref={list} className="grid gap-3" aria-live="polite">
                    {!messages.length && (
                        <li className="grid gap-2 rounded-card border border-dashed border-line p-5 text-sm text-ink-muted">
                            <p>Ask anything about your clubs, events, approvals and deadlines. Try:</p>
                            <div className="flex flex-wrap gap-2">
                                {EXAMPLES.map((e) => (
                                    <button key={e} type="button" className="rounded-full border border-line px-3 py-1 hover:bg-surface-muted" onClick={() => form.setData('question', e)}>
                                        {e}
                                    </button>
                                ))}
                            </div>
                        </li>
                    )}
                    {messages.map((m) => (
                        <li key={m.id} className={`group flex ${m.role === 'user' ? 'justify-end' : 'justify-start'}`}>
                            <div className={`relative grid max-w-[85%] gap-2 rounded-card px-4 py-3 text-sm ${m.role === 'user' ? 'bg-primary text-white' : 'border border-line bg-surface shadow-card'}`}>
                                <p className="whitespace-pre-line">{m.content}</p>
                                {m.links.length > 0 && (
                                    <ul className="grid gap-1">
                                        {m.links.map((l) => (
                                            <li key={l.url}>
                                                <Link href={l.url} className="inline-flex items-center gap-1 font-medium text-primary hover:underline">
                                                    <span className="rounded bg-surface-muted px-1.5 text-[11px] uppercase text-ink-muted">{l.kind}</span> {l.title}
                                                </Link>
                                            </li>
                                        ))}
                                    </ul>
                                )}
                                {!!m.followups?.length && m.id === lastId && (
                                    <div className="flex flex-wrap gap-1.5 pt-1">
                                        {m.followups.map((f) => (
                                            <button
                                                key={f}
                                                type="button"
                                                className="rounded-full border border-line px-2.5 py-1 text-xs text-ink-muted hover:bg-surface-muted hover:text-ink"
                                                onClick={() => {
                                                    form.setData('question', f);
                                                    send(f);
                                                }}
                                            >
                                                {f}
                                            </button>
                                        ))}
                                    </div>
                                )}
                                <button
                                    type="button"
                                    className="absolute -top-2 right-1 hidden rounded-full bg-surface px-1.5 text-xs text-ink-muted shadow-card focus:block group-hover:block"
                                    aria-label="Delete this message"
                                    onClick={() => router.delete(`/assistant/${m.id}`, { preserveScroll: true })}
                                >
                                    ×
                                </button>
                            </div>
                        </li>
                    ))}
                    {form.processing && <li className="text-sm text-ink-muted">Thinking…</li>}
                </ol>
                <form className="sticky bottom-20 flex gap-2 rounded-card border border-line bg-surface p-2 shadow-card md:bottom-4" onSubmit={ask}>
                    <input
                        value={form.data.question}
                        onChange={(e) => form.setData('question', e.target.value)}
                        type="text"
                        maxLength={1000}
                        className="h-11 min-w-0 flex-1 bg-transparent px-2 text-[15px] outline-none"
                        placeholder="Ask about your clubs, events, approvals…"
                        aria-label="Your question"
                    />
                    <Button type="submit" loading={form.processing} disabled={!form.data.question.trim()}>
                        Ask
                    </Button>
                </form>
                {form.errors.question && <p className="text-sm text-danger">{form.errors.question}</p>}
            </div>
        </AppLayout>
    );
}
