import type { ReactNode } from 'react';

interface TabsProps {
    tabs: { key: string; label: string }[];
    active: string;
    onChange: (key: string) => void;
    /** The panel for the active tab. */
    children?: ReactNode;
}

export default function Tabs({ tabs, active, onChange, children }: TabsProps) {
    return (
        <div>
            <div role="tablist" className="flex gap-6 overflow-x-auto border-b border-line [scrollbar-width:none]">
                {tabs.map((tab) => (
                    <button
                        key={tab.key}
                        role="tab"
                        type="button"
                        aria-selected={active === tab.key}
                        className={`-mb-px whitespace-nowrap border-b-2 py-3 text-sm font-medium transition ${active === tab.key ? 'border-ink text-ink' : 'border-transparent text-ink-muted hover:text-ink'}`}
                        onClick={() => onChange(tab.key)}
                    >
                        {tab.label}
                    </button>
                ))}
            </div>
            <div role="tabpanel" className="pt-4">
                {children}
            </div>
        </div>
    );
}
