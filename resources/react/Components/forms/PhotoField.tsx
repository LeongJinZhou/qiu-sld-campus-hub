import Icon from '@react/Components/ui/Icon';
import { useState, type ChangeEvent } from 'react';

interface PhotoFieldProps {
    uploadUrl: string;
    imageBase: string;
    label: string;
    value: string;
    onChange: (path: string) => void;
    required?: boolean;
    readOnly?: boolean;
    error?: string;
    shape?: 'portrait' | 'wide';
}

const xsrf = (): string => decodeURIComponent(document.cookie.match(/XSRF-TOKEN=([^;]+)/)?.[1] ?? '');

/**
 * One picture on a form: pick a file, it uploads straight away (cleaned on the server) and the field keeps the
 * stored path. Used for speaker photos (portrait) and pictures in a proposal's added parts (wide).
 */
export default function PhotoField({ uploadUrl, imageBase, label, value, onChange, required, readOnly, error, shape = 'portrait' }: PhotoFieldProps) {
    const [busy, setBusy] = useState(false);
    const [failed, setFailed] = useState<string | null>(null);
    const url = value ? imageBase.replace(/\/?$/, '/') + value : null;
    const wide = shape === 'wide';

    async function pick(e: ChangeEvent<HTMLInputElement>): Promise<void> {
        const file = e.target.files?.[0];
        e.target.value = '';
        if (!file) return;
        setBusy(true);
        setFailed(null);
        const body = new FormData();
        body.append('image', file);
        try {
            const res = await fetch(uploadUrl, {
                method: 'POST',
                credentials: 'same-origin',
                headers: { Accept: 'application/json', 'X-XSRF-TOKEN': xsrf(), 'X-Requested-With': 'XMLHttpRequest' },
                body,
            });
            const json = (await res.json().catch(() => ({}))) as { path?: string; message?: string; errors?: Record<string, string[]> };
            if (!res.ok || !json.path) {
                setFailed((json.errors && Object.values(json.errors)[0]?.[0]) ?? json.message ?? 'Upload failed. Try another picture (JPG or PNG, up to 8 MB).');
                return;
            }
            onChange(json.path);
        } catch {
            setFailed('Upload failed. Check your connection and try again.');
        } finally {
            setBusy(false);
        }
    }

    return (
        <div className="grid gap-1.5">
            <span className="text-sm font-medium">
                {label}
                {required && <span className="text-danger"> *</span>}
            </span>
            <div className="flex flex-wrap items-center gap-3">
                <div
                    className={`grid shrink-0 place-items-center overflow-hidden rounded-control border bg-surface-muted ${wide ? 'h-40 w-full max-w-md' : 'h-28 w-22'} ${error && !value ? 'border-danger' : 'border-line'}`}
                >
                    {url ? (
                        <img src={url} alt={label} className={wide ? 'max-h-full max-w-full object-contain' : 'size-full object-cover'} />
                    ) : (
                        <Icon name={wide ? 'camera' : 'user'} size={28} className="text-ink-muted" />
                    )}
                </div>
                {!readOnly && (
                    <label className="inline-flex cursor-pointer items-center gap-1.5 rounded-control border border-line bg-surface px-3 py-2 text-sm font-medium hover:bg-surface-muted">
                        <Icon name="camera" size={16} />
                        {busy ? 'Uploading…' : value ? 'Change picture' : 'Upload picture'}
                        <input type="file" accept="image/jpeg,image/png,image/webp" className="sr-only" disabled={busy} onChange={(e) => void pick(e)} />
                    </label>
                )}
            </div>
            {(failed || error) && <p className="text-xs text-danger">{failed ?? error}</p>}
        </div>
    );
}
