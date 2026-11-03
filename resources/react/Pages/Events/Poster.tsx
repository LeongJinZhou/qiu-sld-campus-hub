import FormSection from '@react/Components/forms/FormSection';
import Button from '@react/Components/ui/Button';
import Checkbox from '@react/Components/ui/Checkbox';
import FileUpload from '@react/Components/ui/FileUpload';
import Input from '@react/Components/ui/Input';
import Tabs from '@react/Components/ui/Tabs';
import AppLayout from '@react/Layouts/AppLayout';
import { Head, useForm } from '@inertiajs/react';
import { useEffect, useRef, useState, type FormEvent } from 'react';

interface PosterProps {
    event: {
        slug: string;
        name: string;
        summary: string | null;
        venue: string | null;
        cca_participant: number;
        starts_at: string;
        ends_at: string;
        poster_url: string | null;
        club: { name: string; logo_url: string | null; instagram: string | null };
    };
    checklist: Record<string, string>;
    ticked: Record<string, boolean>;
}
const W = 1080;
const H = 1350;

function load(src: string): Promise<HTMLImageElement> {
    return new Promise((resolve, reject) => {
        const img = new Image();
        img.crossOrigin = 'anonymous';
        img.onload = () => resolve(img);
        img.onerror = reject;
        img.src = src;
    });
}
function wrap(ctx: CanvasRenderingContext2D, text: string, x: number, y: number, maxWidth: number, lineHeight: number): number {
    for (const paragraph of text.split('\n')) {
        let line = '';
        for (const word of paragraph.split(' ')) {
            const test = line ? `${line} ${word}` : word;
            if (ctx.measureText(test).width > maxWidth && line) {
                ctx.fillText(line, x, y);
                line = word;
                y += lineHeight;
            } else line = test;
        }
        ctx.fillText(line, x, y);
        y += lineHeight;
    }
    return y;
}

/** Upload a poster against SLD's checklist, or build one: layout zones A–F with the Student Activity logo placed automatically. */
export default function Poster({ event, checklist, ticked }: PosterProps) {
    const form = useForm({ poster: [] as File[], checklist: Object.fromEntries(Object.keys(checklist).map((k) => [k, ticked[k] ?? false])) as Record<string, boolean> });
    const allTicked = Object.values(form.data.checklist).every(Boolean);
    const save = (e: FormEvent): void => {
        e.preventDefault();
        form.transform((d) => ({ ...d, poster: d.poster[0] ?? null }));
        form.post(`/events/${event.slug}/poster`);
    };

    const [tab, setTab] = useState('upload');
    const start = new Date(event.starts_at);
    const end = new Date(event.ends_at);
    const fmt = (d: Date, o: Intl.DateTimeFormatOptions): string => d.toLocaleString('en-MY', o);
    const [zones, setZones] = useState({
        title: event.name,
        tagline: '',
        when: `${fmt(start, { weekday: 'long', day: 'numeric', month: 'long', year: 'numeric' })}\n${fmt(start, { hour: 'numeric', minute: '2-digit' })} – ${fmt(end, { hour: 'numeric', minute: '2-digit' })}`,
        venue: event.venue ?? '',
        description: event.summary ?? '',
        registration: 'Register on QIU Campus Hub',
        cca: `${event.cca_participant} CCA points`,
        contact: '',
        social: event.club.instagram ? `@${event.club.instagram}` : '',
        photo: [] as File[],
    });
    const zone = (key: keyof Omit<typeof zones, 'photo'>) => ({ value: zones[key], onChange: (v: string) => setZones({ ...zones, [key]: v }) });
    const canvas = useRef<HTMLCanvasElement>(null);

    useEffect(() => {
        if (tab !== 'builder') return;
        let cancelled = false;
        void (async () => {
            const ctx = canvas.current?.getContext('2d');
            if (!ctx) return;
            const grad = ctx.createLinearGradient(0, 0, W, H);
            grad.addColorStop(0, '#FDDA1A');
            grad.addColorStop(0.55, '#FD8F1A');
            grad.addColorStop(1, '#E01CEB');
            ctx.fillStyle = grad;
            ctx.fillRect(0, 0, W, H);
            const hasPhoto = !!zones.photo[0];
            if (zones.photo[0]) {
                const photo = await load(URL.createObjectURL(zones.photo[0]));
                const h = 480;
                ctx.drawImage(photo, 0, (photo.height - (photo.width * h) / W) / 2, photo.width, (photo.width * h) / W, 0, 420, W, h);
            }
            // A: logos, top corners
            const sa = await load('/brand/student-activity-white.png');
            if (cancelled) return;
            ctx.drawImage(sa, 48, 40, 220, (220 * sa.height) / sa.width);
            if (event.club.logo_url) {
                const club = await load(event.club.logo_url).catch(() => null);
                if (club) ctx.drawImage(club, W - 48 - 140, 40, 140, 140);
            } else {
                ctx.fillStyle = '#fff';
                ctx.font = '600 28px Poppins, sans-serif';
                ctx.textAlign = 'right';
                ctx.fillText(event.club.name, W - 48, 110);
                ctx.textAlign = 'left';
            }
            if (cancelled) return;
            ctx.fillStyle = '#ffffff';
            // B: title + tagline
            ctx.font = '700 84px Poppins, sans-serif';
            let y = wrap(ctx, zones.title.toUpperCase(), 48, 270, W - 96, 92);
            ctx.font = '500 34px Poppins, sans-serif';
            if (zones.tagline) wrap(ctx, zones.tagline, 48, y, W - 96, 42);
            // C: date / time / venue
            y = hasPhoto ? 960 : 520;
            ctx.fillStyle = '#1a1a1f';
            ctx.fillRect(48, y - 50, W - 96, 190);
            ctx.fillStyle = '#ffffff';
            ctx.font = '600 34px Poppins, sans-serif';
            y = wrap(ctx, zones.when, 80, y, W - 160, 44);
            ctx.font = '400 30px Poppins, sans-serif';
            wrap(ctx, `📍 ${zones.venue}`, 80, y, W - 160, 40);
            // D: description, registration, CCA
            y = hasPhoto ? 1170 : 780;
            ctx.font = '400 30px Poppins, sans-serif';
            if (!hasPhoto) y = wrap(ctx, zones.description, 48, y, W - 96, 40) + 20;
            ctx.font = '600 30px Poppins, sans-serif';
            wrap(ctx, `${zones.registration} · ${zones.cca}`, 48, y, W - 96, 40);
            // E: contact, F: socials
            ctx.font = '500 26px Poppins, sans-serif';
            ctx.fillText(zones.contact ? `Contact: ${zones.contact}` : '', 48, H - 50);
            ctx.textAlign = 'right';
            ctx.fillText(zones.social, W - 48, H - 50);
            ctx.textAlign = 'left';
        })();
        return () => {
            cancelled = true;
        };
    }, [zones, tab, event.club.logo_url, event.club.name]);

    function useBuilt(): void {
        canvas.current?.toBlob((blob) => {
            if (!blob) return;
            form.setData('poster', [new File([blob], `${event.slug}-poster.png`, { type: 'image/png' })]);
            setTab('upload');
        }, 'image/png');
    }

    return (
        <AppLayout>
            <Head title={`Poster · ${event.name}`} />
            <div className="grid gap-6">
                <h1 className="text-xl font-bold">Poster · {event.name}</h1>
                <Tabs
                    tabs={[
                        { key: 'upload', label: 'Upload & check' },
                        { key: 'builder', label: 'Poster builder' },
                    ]}
                    active={tab}
                    onChange={setTab}
                >
                    {tab === 'upload' ? (
                        <form className="grid gap-6 lg:grid-cols-[1fr_20rem]" onSubmit={save}>
                            <FormSection title="Poster checklist" hint="SLD poster guidelines. Every item must be on the poster.">
                                {Object.entries(checklist).map(([key, label]) => (
                                    <Checkbox key={key} checked={form.data.checklist[key] ?? false} onChange={(v) => form.setData('checklist', { ...form.data.checklist, [key]: v })} label={label} />
                                ))}
                                <FileUpload files={form.data.poster} onChange={(f) => form.setData('poster', f)} label="Poster image" accept="image/jpeg,image/png,image/webp" error={form.errors.poster} />
                                <Button type="submit" disabled={!allTicked || !form.data.poster.length} loading={form.processing} className="justify-self-start">
                                    Save poster
                                </Button>
                            </FormSection>
                            {event.poster_url && <img src={event.poster_url} alt="Current poster" className="rounded-card border border-line" />}
                        </form>
                    ) : (
                        <div className="grid gap-6 lg:grid-cols-[22rem_1fr]">
                            <div className="grid content-start gap-3">
                                <p className="text-sm text-ink-muted">A logos · B title · C date/time/venue · D description · E contact · F social</p>
                                <Input {...zone('title')} label="B · Title" />
                                <Input {...zone('tagline')} label="B · Tagline" />
                                <Input {...zone('venue')} label="C · Venue / platform" />
                                <Input {...zone('registration')} label="D · Registration method" />
                                <Input {...zone('cca')} label="D · CCA points" />
                                <Input {...zone('contact')} label="E · Contact name & phone" placeholder="Aisyah · 013-888 9900" />
                                <Input {...zone('social')} label="F · Social handles" />
                                <FileUpload files={zones.photo} onChange={(f) => setZones({ ...zones, photo: f })} label="Real QIU photo (optional)" accept="image/jpeg,image/png,image/webp" />
                                <Button onClick={useBuilt}>Use this poster</Button>
                            </div>
                            <canvas ref={canvas} width={W} height={H} className="w-full max-w-md rounded-card border border-line" aria-label="Poster preview" />
                        </div>
                    )}
                </Tabs>
            </div>
        </AppLayout>
    );
}
