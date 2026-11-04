import type { ReactNode } from 'react';

interface FilterBarProps {
    search: string;
    onSearch: (value: string) => void;
    placeholder?: string;
    children?: ReactNode;
}

export default function FilterBar({ search, onSearch, placeholder = 'Search', children }: FilterBarProps) {
    return (
        <div className="flex flex-wrap items-center gap-3">
            <input
                type="search"
                value={search}
                onChange={(e) => onSearch(e.target.value)}
                placeholder={placeholder}
                className="h-10 min-w-48 flex-1 rounded-control border border-line bg-surface px-3 text-sm outline-none focus:border-primary focus:ring-2 focus:ring-primary/30"
            />
            {children}
        </div>
    );
}
