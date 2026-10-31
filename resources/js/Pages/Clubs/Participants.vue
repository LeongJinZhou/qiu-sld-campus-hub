<script setup lang="ts">
import Avatar from '@/Components/ui/Avatar.vue';
import Badge from '@/Components/ui/Badge.vue';
import Button from '@/Components/ui/Button.vue';
import EmptyState from '@/Components/ui/EmptyState.vue';
import Icon from '@/Components/ui/Icon.vue';
import Input from '@/Components/ui/Input.vue';
import Select from '@/Components/ui/Select.vue';
import AppLayout from '@/Layouts/AppLayout.vue';
import { date } from '@/lib/format';
import { Head, Link, router } from '@inertiajs/vue3';
import { computed, ref } from 'vue';

type Member = 'committee' | 'member' | 'renewing' | 'pending' | 'alumni' | 'former' | 'none';
interface Row {
    user_id: number;
    name: string;
    photo_url: string | null;
    profile_url: string;
    student_number: string | null;
    programme: string | null;
    year_group: string | null;
    member: Member;
    registered: number;
    attended: number;
    cca_hours: number;
    last_date: string | null;
    events: { name: string; slug: string; date: string; registered: boolean; paid: boolean | null; in: string | null; out: string | null; role: string | null }[];
}

const props = defineProps<{ club: { slug: string; name: string }; session: string; sessions: string[]; events: number; rows: Row[]; labels: Record<Member, string> }>();

const url = `/clubs/${props.club.slug}/participants`;
const show = ref<'all' | 'non' | 'members'>('non');
const search = ref('');
const sort = ref<'attended' | 'recent' | 'name'>('attended');
const open = ref<number | null>(null);

const isMember = (m: Member): boolean => ['committee', 'member', 'renewing', 'alumni'].includes(m);
const nonMembers = computed(() => props.rows.filter((r) => !isMember(r.member)));
const rows = computed(() => {
    const q = search.value.trim().toLowerCase();
    const list = props.rows.filter((r) => (show.value === 'all' || (show.value === 'non' ? !isMember(r.member) : isMember(r.member))) && (!q || `${r.name} ${r.student_number ?? ''} ${r.programme ?? ''}`.toLowerCase().includes(q)));
    return [...list].sort((a, b) => (sort.value === 'name' ? a.name.localeCompare(b.name) : sort.value === 'recent' ? (b.last_date ?? '').localeCompare(a.last_date ?? '') : b.attended - a.attended || b.registered - a.registered));
});
const tone = (m: Member): 'success' | 'info' | 'warning' | 'neutral' => (m === 'member' || m === 'committee' ? 'success' : m === 'alumni' || m === 'renewing' ? 'info' : m === 'pending' ? 'warning' : 'neutral');
const changeSession = (s: string | number | null | undefined): void => router.get(url, { session: s }, { preserveScroll: true });
const exportUrl = (format: string): string => `${url}/export/${format}?session=${encodeURIComponent(props.session)}`;
</script>

<template>
    <Head :title="`Participants · ${club.name}`" />
    <AppLayout>
        <div class="grid gap-5">
            <header class="flex flex-wrap items-end justify-between gap-3">
                <div>
                    <Link :href="`/clubs/${club.slug}`" class="text-sm text-brand-blue">← {{ club.name }}</Link>
                    <h1 class="text-xl font-bold">Event participants</h1>
                    <p class="text-sm text-ink-muted">Everyone who registered for or attended {{ club.name }} events — members or not.</p>
                </div>
                <div class="flex flex-wrap items-end gap-2">
                    <Select :model-value="session" label="Session" :options="sessions.map((s) => ({ value: s, label: s }))" @update:model-value="changeSession" />
                    <a :href="exportUrl('xlsx')"><Button variant="secondary"><Icon name="file" :size="16" /> Excel</Button></a>
                    <a :href="exportUrl('pdf')"><Button variant="secondary">PDF</Button></a>
                </div>
            </header>

            <div class="grid grid-cols-2 gap-3 lg:grid-cols-4">
                <div class="rounded-card border border-line bg-surface p-4 shadow-card"><p class="text-xs text-ink-muted">Events this session</p><p class="text-xl font-bold">{{ events }}</p></div>
                <div class="rounded-card border border-line bg-surface p-4 shadow-card"><p class="text-xs text-ink-muted">People who took part</p><p class="text-xl font-bold">{{ props.rows.length }}</p></div>
                <div class="rounded-card border-2 border-brand-orange/40 bg-brand-orange/5 p-4 shadow-card"><p class="text-xs text-ink-muted">Joined events, not the club</p><p class="text-xl font-bold text-brand-orange">{{ nonMembers.length }}</p></div>
                <div class="rounded-card border border-line bg-surface p-4 shadow-card"><p class="text-xs text-ink-muted">Members who took part</p><p class="text-xl font-bold">{{ props.rows.length - nonMembers.length }}</p></div>
            </div>

            <div class="grid gap-3 rounded-card border border-line bg-surface p-4 shadow-card sm:grid-cols-[auto_1fr_12rem] sm:items-end">
                <div class="grid grid-cols-3 gap-1 rounded-control bg-surface-muted p-1 text-sm" role="radiogroup" aria-label="Show">
                    <button v-for="o in [{ k: 'non', l: 'Not members' }, { k: 'members', l: 'Members' }, { k: 'all', l: 'Everyone' }] as const" :key="o.k" type="button" role="radio" :aria-checked="show === o.k" class="whitespace-nowrap rounded-control px-3 py-1.5" :class="show === o.k ? 'bg-surface font-semibold shadow-card' : ''" @click="show = o.k">{{ o.l }}</button>
                </div>
                <Input v-model="search" label="Search" placeholder="Name, student ID or programme" />
                <Select v-model="sort" label="Sort" :options="[{ value: 'attended', label: 'Most events' }, { value: 'recent', label: 'Most recent' }, { value: 'name', label: 'Name' }]" />
            </div>

            <EmptyState v-if="!rows.length" :title="show === 'non' ? 'Everyone who took part is a member' : 'Nobody yet'" description="People appear here once they register for or attend this club's events." />

            <ul v-else class="grid gap-2">
                <li v-for="r in rows" :key="r.user_id" class="overflow-hidden rounded-card border border-line bg-surface shadow-card">
                    <button type="button" class="flex w-full items-center gap-3 p-3 text-left" :aria-expanded="open === r.user_id" @click="open = open === r.user_id ? null : r.user_id">
                        <Avatar :src="r.photo_url" :name="r.name" size="sm" />
                        <span class="grid min-w-0 flex-1">
                            <strong class="truncate text-sm">{{ r.name }}</strong>
                            <span class="truncate text-xs text-ink-muted">{{ [r.student_number, r.programme, r.year_group].filter(Boolean).join(' · ') }}</span>
                        </span>
                        <span class="hidden text-right text-xs sm:grid">
                            <span><strong class="text-sm">{{ r.attended }}</strong> attended · {{ r.registered }} registered</span>
                            <span class="text-ink-muted">Last {{ r.last_date ? date(r.last_date) : '—' }}<template v-if="r.cca_hours"> · {{ r.cca_hours }} CCA</template></span>
                        </span>
                        <Badge :tone="tone(r.member)">{{ labels[r.member] }}</Badge>
                        <Icon name="more" :size="18" class="shrink-0 text-ink-muted" />
                    </button>
                    <div v-if="open === r.user_id" class="grid gap-2 border-t border-line bg-surface-muted/50 p-3">
                        <p class="text-xs text-ink-muted sm:hidden">{{ r.attended }} attended · {{ r.registered }} registered<template v-if="r.cca_hours"> · {{ r.cca_hours }} CCA</template></p>
                        <ol class="grid gap-1.5">
                            <li v-for="e in r.events" :key="e.slug" class="flex flex-wrap items-center justify-between gap-2 rounded-control bg-surface px-3 py-2 text-sm">
                                <Link :href="`/events/${e.slug}`" class="min-w-0"><span class="font-medium">{{ e.name }}</span> <span class="text-xs text-ink-muted">· {{ date(e.date) }}</span></Link>
                                <span class="flex flex-wrap items-center gap-1.5 text-xs">
                                    <Badge v-if="e.paid === false" tone="warning">Not paid</Badge>
                                    <Badge v-if="e.in" tone="success">In {{ e.in }}<template v-if="e.out"> · Out {{ e.out }}</template></Badge>
                                    <Badge v-else-if="e.registered">Registered, didn't attend</Badge>
                                    <Badge v-if="e.role === 'committee'" tone="info">Committee</Badge>
                                </span>
                            </li>
                        </ol>
                        <a :href="r.profile_url" class="justify-self-start text-xs font-medium text-brand-blue">View profile →</a>
                    </div>
                </li>
            </ul>
        </div>
    </AppLayout>
</template>
