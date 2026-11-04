import { useState } from 'react';

interface FileUploadProps {
    label?: string;
    accept?: string;
    multiple?: boolean;
    error?: string;
    files: File[];
    onChange: (files: File[]) => void;
}

export default function FileUpload({ label, accept, multiple = false, error, files, onChange }: FileUploadProps) {
    const [dragging, setDragging] = useState(false);
    const add = (list: FileList | null | undefined): void => {
        if (list) onChange([...files, ...Array.from(list)]);
    };

    return (
        <div className="grid gap-2">
            {label && <span className="text-sm font-medium">{label}</span>}
            <label
                className={`grid cursor-pointer place-items-center rounded-card border-2 border-dashed px-4 py-8 text-center text-sm transition ${dragging ? 'border-primary bg-primary/5' : 'border-line hover:bg-surface-muted'}`}
                onDragOver={(e) => {
                    e.preventDefault();
                    setDragging(true);
                }}
                onDragLeave={() => setDragging(false)}
                onDrop={(e) => {
                    e.preventDefault();
                    setDragging(false);
                    add(e.dataTransfer?.files);
                }}
            >
                <span>
                    <strong>Choose file</strong> or drag it here
                </span>
                <input type="file" className="sr-only" accept={accept} multiple={multiple} onChange={(e) => add(e.target.files)} />
            </label>
            {files.length > 0 && (
                <ul className="grid gap-1 text-sm">
                    {files.map((file, index) => (
                        <li key={`${file.name}-${index}`} className="flex items-center justify-between rounded-control bg-surface-muted px-3 py-2">
                            <span className="truncate">{file.name}</span>
                            <button type="button" className="text-ink-muted hover:text-danger" aria-label={`Remove ${file.name}`} onClick={() => onChange(files.filter((_, i) => i !== index))}>
                                ✕
                            </button>
                        </li>
                    ))}
                </ul>
            )}
            {error && <p className="text-xs text-danger">{error}</p>}
        </div>
    );
}
