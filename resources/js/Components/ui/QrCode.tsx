/** Renders an SVG QR generated server-side by simple-qrcode (never user-supplied markup). */
export default function QrCode({ svg, label, downloadName }: { svg: string; label?: string; downloadName?: string }) {
    return (
        <figure className="grid justify-items-center gap-2">
            <div className="size-48 rounded-card bg-white p-3 [&>svg]:size-full" role="img" aria-label={label ?? 'QR code'} dangerouslySetInnerHTML={{ __html: svg }} />
            {label && <figcaption className="text-sm text-ink-muted">{label}</figcaption>}
            {downloadName && (
                <a href={`data:image/svg+xml;charset=utf-8,${encodeURIComponent(svg)}`} download={`${downloadName}.svg`} className="text-sm font-medium text-primary">
                    Download QR
                </a>
            )}
        </figure>
    );
}
