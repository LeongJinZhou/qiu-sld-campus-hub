import type { AutosaveState } from '@/hooks/useAutosave';

const text: Record<AutosaveState, string> = {
    idle: 'Changes save automatically',
    saving: 'Saving…',
    saved: 'All changes saved',
    error: "Couldn't save yet — check the highlighted fields",
};

export default function AutosaveNote({ state, className = '' }: { state: AutosaveState; className?: string }) {
    return (
        <span className={`text-xs ${state === 'error' ? 'text-danger' : 'text-ink-muted'} ${className}`.trim()} aria-live="polite">
            {text[state]}
        </span>
    );
}
