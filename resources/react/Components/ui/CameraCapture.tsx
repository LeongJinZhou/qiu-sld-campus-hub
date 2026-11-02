import { useEffect, useRef, useState } from 'react';
import Button from './Button';

/** Take a photo with the device camera, preview it, then confirm or retake. */
export default function CameraCapture({ onCapture }: { onCapture: (photo: Blob) => void }) {
    const video = useRef<HTMLVideoElement>(null);
    const photo = useRef<Blob | null>(null);
    const [preview, setPreview] = useState<string | null>(null);
    const [error, setError] = useState<string | null>(null);

    useEffect(() => {
        let stream: MediaStream | null = null;
        navigator.mediaDevices
            .getUserMedia({ video: { facingMode: 'environment' }, audio: false })
            .then((s) => {
                stream = s;
                if (video.current) video.current.srcObject = s;
            })
            .catch(() => setError('Camera unavailable. Allow camera access and try again.'));
        return () => stream?.getTracks().forEach((track) => track.stop());
    }, []);
    useEffect(() => () => void (preview && URL.revokeObjectURL(preview)), [preview]);

    const take = (): void => {
        const source = video.current;
        if (!source) return;
        const canvas = document.createElement('canvas');
        canvas.width = source.videoWidth;
        canvas.height = source.videoHeight;
        canvas.getContext('2d')?.drawImage(source, 0, 0);
        canvas.toBlob(
            (blob) => {
                if (!blob) return;
                photo.current = blob;
                setPreview(URL.createObjectURL(blob));
            },
            'image/jpeg',
            0.9,
        );
    };
    const retake = (): void => {
        setPreview(null);
        photo.current = null;
    };

    return (
        <div className="grid gap-3">
            {error && <p className="text-sm text-danger">{error}</p>}
            {preview && <img src={preview} alt="Captured photo" className="rounded-card" />}
            <video ref={video} autoPlay playsInline muted className="rounded-card bg-ink" hidden={!!preview} />
            <div className="flex gap-2">
                {preview ? (
                    <>
                        <Button variant="secondary" onClick={retake}>
                            Retake
                        </Button>
                        <Button onClick={() => photo.current && onCapture(photo.current)}>Use photo</Button>
                    </>
                ) : (
                    <Button disabled={!!error} onClick={take}>
                        Take photo
                    </Button>
                )}
            </div>
        </div>
    );
}
