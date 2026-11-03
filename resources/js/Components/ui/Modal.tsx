import { useDialog } from '@/hooks/useDialog';
import type { ReactNode } from 'react';

interface ModalProps {
    open: boolean;
    onClose: () => void;
    title?: string;
    children?: ReactNode;
}

export default function Modal({ open, onClose, title, children }: ModalProps) {
    const ref = useDialog(open);
    return (
        <dialog
            ref={ref}
            className="m-auto w-[min(32rem,calc(100vw-2rem))] rounded-card bg-surface p-0 shadow-pop backdrop:bg-ink/50"
            onClose={onClose}
            onClick={(e) => e.target === e.currentTarget && onClose()}
        >
            <div className="p-5">
                {title && (
                    <header className="mb-4 flex items-center justify-between">
                        <h2 className="text-lg font-semibold">{title}</h2>
                        <button type="button" className="text-ink-muted hover:text-ink" aria-label="Close" onClick={onClose}>
                            ✕
                        </button>
                    </header>
                )}
                {open && children}
            </div>
        </dialog>
    );
}
