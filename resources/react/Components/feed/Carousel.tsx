import { useRef, useState, type SyntheticEvent } from 'react';

/**
 * Like Instagram: the first photo sets the frame, kept between 4:5 portrait and 1.91:1 landscape, so posters are
 * not cropped to a square. Square until the first photo has loaded.
 */
export default function Carousel({ images, alt }: { images: string[]; alt: string }) {
    const [index, setIndex] = useState(0);
    const [ratio, setRatio] = useState(1);
    const track = useRef<HTMLDivElement>(null);

    const onScroll = (): void => {
        const el = track.current;
        if (el) setIndex(Math.round(el.scrollLeft / el.clientWidth));
    };
    const onLoad = (event: SyntheticEvent<HTMLImageElement>, i: number): void => {
        const img = event.currentTarget;
        if (i === 0 && img.naturalWidth && img.naturalHeight) setRatio(Math.min(1.91, Math.max(0.8, img.naturalWidth / img.naturalHeight)));
    };
    const go = (step: number): void => track.current?.scrollBy({ left: step * track.current.clientWidth, behavior: 'smooth' });
    const arrow = 'absolute top-1/2 hidden size-8 -translate-y-1/2 place-items-center rounded-full bg-white/90 text-ink shadow-card group-hover:grid';

    return (
        <div className="group relative bg-surface-muted">
            <div ref={track} className="flex snap-x snap-mandatory overflow-x-auto [scrollbar-width:none] [&::-webkit-scrollbar]:hidden" style={{ aspectRatio: ratio }} onScroll={onScroll}>
                {images.map((src, i) => (
                    <img
                        key={`${i}-${src}`}
                        src={src}
                        alt={`${alt} ${i + 1}`}
                        className="h-full w-full shrink-0 snap-center object-cover"
                        loading={i === 0 ? 'eager' : 'lazy'}
                        decoding="async"
                        onLoad={(e) => onLoad(e, i)}
                    />
                ))}
            </div>
            {images.length > 1 && (
                <>
                    {index > 0 && (
                        <button type="button" className={`${arrow} left-2`} aria-label="Previous photo" onClick={() => go(-1)}>
                            ‹
                        </button>
                    )}
                    {index < images.length - 1 && (
                        <button type="button" className={`${arrow} right-2`} aria-label="Next photo" onClick={() => go(1)}>
                            ›
                        </button>
                    )}
                    <span className="absolute right-3 top-3 rounded-full bg-black/60 px-2 py-0.5 text-xs font-medium text-white">
                        {index + 1}/{images.length}
                    </span>
                    <div className="absolute inset-x-0 bottom-2 flex justify-center gap-1" aria-hidden="true">
                        {images.map((_, i) => (
                            <span key={i} className={`size-1.5 rounded-full transition ${i === index ? 'bg-white' : 'bg-white/50'}`} />
                        ))}
                    </div>
                </>
            )}
        </div>
    );
}
