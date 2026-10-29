<script setup lang="ts">
import FormSection from '@/Components/forms/FormSection.vue';
import Button from '@/Components/ui/Button.vue';
import Checkbox from '@/Components/ui/Checkbox.vue';
import FileUpload from '@/Components/ui/FileUpload.vue';
import Input from '@/Components/ui/Input.vue';
import Tabs from '@/Components/ui/Tabs.vue';
import AppLayout from '@/Layouts/AppLayout.vue';
import { Head, useForm } from '@inertiajs/vue3';
import { computed, onMounted, reactive, ref, watch } from 'vue';

const props = defineProps<{
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
}>();

const form = useForm({
    poster: [] as File[],
    checklist: Object.fromEntries(Object.keys(props.checklist).map((k) => [k, props.ticked[k] ?? false])) as Record<string, boolean>,
});
const allTicked = computed(() => Object.values(form.checklist).every(Boolean));
const save = (): void => {
    form.transform((d) => ({ ...d, poster: d.poster[0] ?? null })).post(`/events/${props.event.slug}/poster`);
};

// Poster builder: SLD layout zones A–F, Student Activity logo placed automatically (zone A).
const tab = ref('upload');
const start = new Date(props.event.starts_at);
const end = new Date(props.event.ends_at);
const fmt = (d: Date, o: Intl.DateTimeFormatOptions) => d.toLocaleString('en-MY', o);
const zones = reactive({
    title: props.event.name,
    tagline: '',
    when: `${fmt(start, { weekday: 'long', day: 'numeric', month: 'long', year: 'numeric' })}\n${fmt(start, { hour: 'numeric', minute: '2-digit' })} – ${fmt(end, { hour: 'numeric', minute: '2-digit' })}`,
    venue: props.event.venue ?? '',
    description: props.event.summary ?? '',
    registration: 'Register on QIU Campus Hub',
    cca: `${props.event.cca_participant} CCA points`,
    contact: '',
    social: props.event.club.instagram ? `@${props.event.club.instagram}` : '',
    photo: [] as File[],
});

const canvas = ref<HTMLCanvasElement | null>(null);
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

async function draw(): Promise<void> {
    const ctx = canvas.value?.getContext('2d');
    if (!ctx) return;
    const grad = ctx.createLinearGradient(0, 0, W, H);
    grad.addColorStop(0, '#FDDA1A');
    grad.addColorStop(0.55, '#FD8F1A');
    grad.addColorStop(1, '#E01CEB');
    ctx.fillStyle = grad;
    ctx.fillRect(0, 0, W, H);

    if (zones.photo[0]) {
        const photo = await load(URL.createObjectURL(zones.photo[0]));
        const h = 480;
        ctx.drawImage(photo, 0, (photo.height - (photo.width * h) / W) / 2, photo.width, (photo.width * h) / W, 0, 420, W, h);
    }

    // A: logos, top corners
    const sa = await load('/brand/student-activity-white.png');
    ctx.drawImage(sa, 48, 40, 220, (220 * sa.height) / sa.width);
    if (props.event.club.logo_url) {
        const club = await load(props.event.club.logo_url).catch(() => null);
        if (club) ctx.drawImage(club, W - 48 - 140, 40, 140, 140);
    } else {
        ctx.fillStyle = '#fff';
        ctx.font = '600 28px Poppins, sans-serif';
        ctx.textAlign = 'right';
        ctx.fillText(props.event.club.name, W - 48, 110);
        ctx.textAlign = 'left';
    }

    ctx.fillStyle = '#ffffff';
    // B: title + tagline
    ctx.font = '700 84px Poppins, sans-serif';
    let y = wrap(ctx, zones.title.toUpperCase(), 48, 270, W - 96, 92);
    ctx.font = '500 34px Poppins, sans-serif';
    if (zones.tagline) wrap(ctx, zones.tagline, 48, y, W - 96, 42);

    // C: date / time / venue
    y = zones.photo[0] ? 960 : 520;
    ctx.fillStyle = '#1a1a1f';
    ctx.fillRect(48, y - 50, W - 96, 190);
    ctx.fillStyle = '#ffffff';
    ctx.font = '600 34px Poppins, sans-serif';
    y = wrap(ctx, zones.when, 80, y, W - 160, 44);
    ctx.font = '400 30px Poppins, sans-serif';
    wrap(ctx, `📍 ${zones.venue}`, 80, y, W - 160, 40);

    // D: description, registration, CCA
    y = zones.photo[0] ? 1170 : 780;
    ctx.fillStyle = '#ffffff';
    ctx.font = '400 30px Poppins, sans-serif';
    if (!zones.photo[0]) y = wrap(ctx, zones.description, 48, y, W - 96, 40) + 20;
    ctx.font = '600 30px Poppins, sans-serif';
    wrap(ctx, `${zones.registration} · ${zones.cca}`, 48, y, W - 96, 40);

    // E: contact, F: socials
    ctx.font = '500 26px Poppins, sans-serif';
    ctx.fillText(zones.contact ? `Contact: ${zones.contact}` : '', 48, H - 50);
    ctx.textAlign = 'right';
    ctx.fillText(zones.social, W - 48, H - 50);
    ctx.textAlign = 'left';
}

watch(zones, () => void draw(), { deep: true });
onMounted(() => void draw());

function useBuilt(): void {
    canvas.value?.toBlob((blob) => {
        if (!blob) return;
        form.poster = [new File([blob], `${props.event.slug}-poster.png`, { type: 'image/png' })];
        tab.value = 'upload';
    }, 'image/png');
}
</script>

<template>
    <Head :title="`Poster · ${event.name}`" />
    <AppLayout>
        <div class="grid gap-6">
            <h1 class="text-xl font-bold">Poster · {{ event.name }}</h1>
            <Tabs v-model="tab" :tabs="[{ key: 'upload', label: 'Upload & check' }, { key: 'builder', label: 'Poster builder' }]">
                <template #upload>
                    <form class="grid gap-6 lg:grid-cols-[1fr_20rem]" @submit.prevent="save">
                        <FormSection title="Poster checklist" hint="SLD poster guidelines. Every item must be on the poster.">
                            <Checkbox v-for="(label, key) in checklist" :key="key" v-model="form.checklist[key]" :label="label" />
                            <FileUpload v-model="form.poster" label="Poster image" accept="image/jpeg,image/png,image/webp" :error="form.errors.poster" />
                            <Button type="submit" :disabled="!allTicked || !form.poster.length" :loading="form.processing" class="justify-self-start">Save poster</Button>
                        </FormSection>
                        <img v-if="event.poster_url" :src="event.poster_url" alt="Current poster" class="rounded-card border border-line" />
                    </form>
                </template>
                <template #builder>
                    <div class="grid gap-6 lg:grid-cols-[22rem_1fr]">
                        <div class="grid content-start gap-3">
                            <p class="text-sm text-ink-muted">A logos · B title · C date/time/venue · D description · E contact · F social</p>
                            <Input v-model="zones.title" label="B · Title" />
                            <Input v-model="zones.tagline" label="B · Tagline" />
                            <Input v-model="zones.venue" label="C · Venue / platform" />
                            <Input v-model="zones.registration" label="D · Registration method" />
                            <Input v-model="zones.cca" label="D · CCA points" />
                            <Input v-model="zones.contact" label="E · Contact name & phone" placeholder="Aisyah · 013-888 9900" />
                            <Input v-model="zones.social" label="F · Social handles" />
                            <FileUpload v-model="zones.photo" label="Real QIU photo (optional)" accept="image/jpeg,image/png,image/webp" />
                            <Button @click="useBuilt">Use this poster</Button>
                        </div>
                        <canvas ref="canvas" :width="W" :height="H" class="w-full max-w-md rounded-card border border-line" aria-label="Poster preview" />
                    </div>
                </template>
            </Tabs>
        </div>
    </AppLayout>
</template>
