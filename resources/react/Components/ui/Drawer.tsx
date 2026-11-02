import { useDialog } from '@react/hooks/useDialog';
import type { ReactNode } from 'react';

const positions = {
    left: 'mr-auto h-full max-h-none w-[min(22rem,90vw)]',
    right: 'ml-auto h-full max-h-none w-[min(22rem,90vw)]',
    bottom: 'mt-auto w-full max-w-none rounded-t-card',
};

interface DrawerProps {
    open: boolean;
    onClose: () => void;
    title?: string;
    side?: keyof typeof positions;
    children?: ReactNode;
}

export default function Drawer({ open, onClose, title, side = 'right', children }: DrawerProps) {
    const ref = useDialog(open);
    return (
        <dialog ref={ref} className={`m-0 bg-surface p-0 shadow-pop backdrop:bg-ink/50 ${positions[side]}`} onClose={onClose} onClick={(e) => e.target === e.currentTarget && onClose()}>
            <div className="p-5">
                <header className="mb-4 flex items-center justify-between">
                    <h2 className="text-lg font-semibold">{title}</h2>
                    <button type="button" className="text-ink-muted hover:text-ink" aria-label="Close" onClick={onClose}>
                        ✕
                    </button>
                </header>
                {children}
            </div>
        </dialog>
    );
}
