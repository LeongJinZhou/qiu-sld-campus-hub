import Avatar from '@/Components/ui/Avatar';
import Button from '@/Components/ui/Button';
import CameraCapture from '@/Components/ui/CameraCapture';
import Icon from '@/Components/ui/Icon';
import ImageAdjuster, { type ImageAdjusterHandle } from '@/Components/ui/ImageAdjuster';
import Modal from '@/Components/ui/Modal';
import { router } from '@inertiajs/react';
import { useRef, useState } from 'react';

interface PhotoEditorProps {
    kind?: 'photo' | 'cover';
    current: string | null;
    name: string;
    hasOverride: boolean;
    open: boolean;
    onClose: () => void;
}

/**
 * Profile photo or cover picture: upload, take one with the camera (photo only) or re-adjust the current one,
 * then drag and zoom it into the frame before saving. "Remove" goes back to the Google photo / default cover.
 */
export default function PhotoEditor({ kind = 'photo', current, name, hasOverride, open, onClose }: PhotoEditorProps) {
    const isCover = kind === 'cover';
    const [mode, setMode] = useState<'choose' | 'camera' | 'adjust'>('choose');
    const [source, setSource] = useState<Blob | null>(null);
    const [saving, setSaving] = useState(false);
    const [error, setError] = useState('');
    const adjuster = useRef<ImageAdjusterHandle>(null);

    function pick(f: Blob | null | undefined): void {
        if (!f) return;
        setSource(f);
        setError('');
        setMode('adjust');
    }
    // Re-adjust the photo already saved (only our own uploads: other sites' images cannot be read back).
    async function adjustCurrent(): Promise<void> {
        if (!current) return;
        const response = await fetch(current).catch(() => null);
        if (response?.ok) pick(await response.blob());
        else setError('Could not load the current picture. Upload it again instead.');
    }
    function send(data: Record<string, File | number>): void {
        setSaving(true);
        router.post('/me', data, {
            forceFormData: true,
            preserveScroll: true,
            onFinish: () => setSaving(false),
            onSuccess: () => {
                onClose();
                setMode('choose');
                setSource(null);
            },
        });
    }
    async function save(): Promise<void> {
        const blob = await adjuster.current?.exportBlob();
        if (blob) send({ [kind]: new File([blob], `${kind}.jpg`, { type: 'image/jpeg' }) });
    }

    return (
        <Modal open={open} onClose={onClose} title={isCover ? 'Cover picture' : 'Profile photo'}>
            <div className="grid gap-5">
                {mode === 'adjust' && source ? (
                    <>
                        <ImageAdjuster ref={adjuster} file={source} aspect={isCover ? 3 : 1} round={!isCover} outputWidth={isCover ? 1500 : 640} />
                        <div className="grid grid-cols-2 gap-2">
                            <Button variant="secondary" onClick={() => setMode('choose')}>
                                Back
                            </Button>
                            <Button loading={saving} onClick={() => void save()}>
                                Save
                            </Button>
                        </div>
                    </>
                ) : (
                    <>
                        <div className="grid justify-items-center gap-2">
                            {isCover ? (
                                <div className="aspect-[3/1] w-full overflow-hidden rounded-card bg-gradient-to-r from-gold via-accent to-primary-strong">
                                    {current && <img src={current} alt="Current cover" className="size-full object-cover" />}
                                </div>
                            ) : (
                                <Avatar src={current} name={name} size="xl" ring />
                            )}
                            <p className="text-xs text-ink-muted">{hasOverride ? 'Your current picture' : isCover ? 'Default cover' : 'Your Google photo'}</p>
                        </div>
                        {mode === 'camera' ? (
                            <CameraCapture onCapture={pick} />
                        ) : (
                            <div className={`grid gap-2 ${isCover ? 'grid-cols-1' : 'grid-cols-2'}`}>
                                <label className="flex cursor-pointer items-center justify-center gap-2 rounded-control border border-line py-3 text-sm font-medium hover:bg-surface-muted">
                                    <Icon name="grid" size={18} /> Upload
                                    <input type="file" accept="image/jpeg,image/png,image/webp" className="sr-only" onChange={(e) => pick(e.target.files?.[0])} />
                                </label>
                                {!isCover && (
                                    <button type="button" className="flex items-center justify-center gap-2 rounded-control border border-line py-3 text-sm font-medium hover:bg-surface-muted" onClick={() => setMode('camera')}>
                                        <Icon name="camera" size={18} /> Take photo
                                    </button>
                                )}
                            </div>
                        )}
                        {hasOverride && mode === 'choose' && (
                            <Button variant="secondary" onClick={() => void adjustCurrent()}>
                                <Icon name="edit" size={16} /> Adjust current picture
                            </Button>
                        )}
                        {error && <p className="text-sm text-danger">{error}</p>}
                        {hasOverride && mode === 'choose' && (
                            <Button variant="ghost" loading={saving} onClick={() => send({ [`remove_${kind}`]: 1 })}>
                                {isCover ? 'Use the default cover' : 'Use my Google photo'}
                            </Button>
                        )}
                    </>
                )}
            </div>
        </Modal>
    );
}
