import Icon from '@react/Components/ui/Icon';
import { router } from '@inertiajs/react';
import { useState, type ChangeEvent } from 'react';

export interface Evidence {
    type: string;
    id: number;
    minimum: number;
    photos: { id: number | null; url: string }[];
}

/** Evidence photos for a weekly report, an AGM or an event: shows the count against SLD's minimum, add and remove. */
export default function EvidencePhotos({ evidence, canEdit, title }: { evidence: Evidence; canEdit?: boolean; title?: string }) {
    const [uploading, setUploading] = useState(false);
    const enough = evidence.photos.length >= evidence.minimum;

    function add(e: ChangeEvent<HTMLInputElement>): void {
        const files = [...(e.target.files ?? [])];
        e.target.value = '';
        if (!files.length) return;
        setUploading(true);
        router.post(`/evidence/${evidence.type}/${evidence.id}`, { photos: files }, { forceFormData: true, preserveScroll: true, onFinish: () => setUploading(false) });
    }
    const remove = (id: number): void => {
        if (confirm('Remove this photo?')) router.delete(`/evidence-photos/${id}`, { preserveScroll: true });
    };

    return (
        <section className={`grid gap-3 rounded-card border bg-surface p-4 shadow-card ${enough ? 'border-line' : 'border-warning/50'}`}>
            <header className="flex flex-wrap items-center justify-between gap-2">
                <div>
                    <h2 className="font-semibold">
                        {title ?? 'Photos'}
                        <span className="text-danger"> *</span>
                    </h2>
                    <p className={`text-xs ${enough ? 'text-success' : 'text-warning'}`}>
                        {evidence.photos.length} of at least {evidence.minimum} required{enough ? ' ✓' : ''}
                    </p>
                </div>
                {canEdit && (
                    <label className="inline-flex h-9 cursor-pointer items-center gap-1.5 rounded-control bg-ink px-3 text-sm font-medium text-white">
                        <Icon name="camera" size={16} /> {uploading ? 'Uploading…' : 'Add photos'}
                        <input type="file" accept="image/jpeg,image/png,image/webp" multiple className="sr-only" onChange={add} />
                    </label>
                )}
            </header>
            {evidence.photos.length > 0 && (
                <ul className="grid grid-cols-3 gap-2 sm:grid-cols-5">
                    {evidence.photos.map((p, i) => (
                        <li key={i} className="relative aspect-square overflow-hidden rounded-control bg-surface-muted">
                            <img src={p.url} alt="" className="size-full object-cover" loading="lazy" />
                            {canEdit && p.id && (
                                <button type="button" className="absolute right-1 top-1 grid size-6 place-items-center rounded-full bg-black/60 text-white" aria-label="Remove photo" onClick={() => remove(p.id!)}>
                                    <Icon name="x" size={12} />
                                </button>
                            )}
                        </li>
                    ))}
                </ul>
            )}
        </section>
    );
}
