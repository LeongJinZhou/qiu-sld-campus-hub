<script setup lang="ts">
import { date } from '@/lib/format';
import CheckboxGroup from '@/Components/forms/CheckboxGroup.vue';
import Badge from '@/Components/ui/Badge.vue';
import Button from '@/Components/ui/Button.vue';
import Drawer from '@/Components/ui/Drawer.vue';
import FilterBar from '@/Components/ui/FilterBar.vue';
import Icon from '@/Components/ui/Icon.vue';
import Input from '@/Components/ui/Input.vue';
import Select from '@/Components/ui/Select.vue';
import AppLayout from '@/Layouts/AppLayout.vue';
import { copy } from '@/lib/clone';
import { Head, Link, router } from '@inertiajs/vue3';
import { watchDebounced } from '@vueuse/core';
import { computed, reactive, ref } from 'vue';

type Filters = Record<string, string | number | string[] | string[][] | undefined>;
interface Row {
    id: number;
    club: string;
    name: string;
    email: string;
    student_number: string | null;
    programme_short: string | null;
    year_group: string | null;
    intake: string | null;
    status: string;
    position: string;
    joined: string | null;
    expires: string | null;
    payment: string;
    method: string | null;
    collected_by: string | null;
    events: number;
    cca: number;
}

const props = defineProps<{
    club: { slug: string; name: string } | null;
    filters: Filters;
    counts: Record<string, number>;
    rows: Row[];
    truncated: boolean;
    options: {
        positions: string[];
        year_groups: string[];
        programmes: string[];
        intakes: string[];
        collectors: string[];
        sessions: string[];
        clubs: { value: number; label: string }[];
        categories: string[];
    };
    views: { id: number; name: string; filters: Filters }[];
}>();

const url = props.club ? `/clubs/${props.club.slug}/members` : '/admin/members';
const f = reactive<Filters>({ status: [], position: [], payment: [], method: [], collected_by: [], year_group: [], programme_short: [], intake: [], club_id: [], category: [], club_status: [], ...(copy(props.filters) as Partial<Filters>) });
const sort = ref<string[][]>(Array.isArray(props.filters.sort) ? (props.filters.sort as string[][]) : [['name', 'asc']]);
const search = ref(typeof props.filters.search === 'string' ? props.filters.search : '');
const open = ref(false);

const apply = (extra: Filters = {}): void => {
    router.get(url, { ...f, search: search.value || undefined, sort: sort.value, ...extra }, { preserveState: true, preserveScroll: true, replace: true });
};
watchDebounced(search, () => apply(), { debounce: 300 });
const toOptions = (list: string[]) => Object.fromEntries(list.map((v) => [v, v]));
const activeCount = computed(() => Object.entries(props.filters).filter(([k, v]) => !['search', 'sort'].includes(k) && (Array.isArray(v) ? v.length : v !== undefined)).length);
const clear = (): void => router.get(url, {}, { preserveScroll: true });
const sortFields = { name: 'Name', student_number: 'Student ID', programme: 'Programme', year_group: 'Year group', status: 'Status', joined: 'Joined', expires: 'Expires', events: 'Events', cca: 'CCA', club: 'Club' };
const toggleSort = (field: string): void => {
    const [first] = sort.value;
    sort.value = [[field, first?.[0] === field && first[1] === 'asc' ? 'desc' : 'asc'], ...sort.value.filter(([k]) => k !== field)].slice(0, 3);
    apply();
};
const saveView = (): void => {
    const name = prompt('Name this view');
    if (name) router.post('/saved-views', { name, filters: { ...props.filters } }, { preserveScroll: true });
};
// Export uses the same simple filters (search, session, dates) as the screen.
const exportQuery = computed(() => new URLSearchParams(Object.entries(props.filters).filter((e): e is [string, string] => typeof e[1] === 'string')).toString());
const cards = [
    ['total', 'Total'], ['active', 'Active'], ['grace', 'Grace'], ['terminated', 'Terminated'],
    ['alumni', 'Alumni'], ['paid', 'Paid'], ['pending', 'Pending'], ['unpaid', 'Unpaid'],
] as const;
const tone: Record<string, 'success' | 'warning' | 'danger' | 'info' | 'neutral'> = { active: 'success', grace: 'warning', terminated: 'danger', alumni: 'info', pending: 'neutral', paid: 'success', unpaid: 'danger' };
</script>

<template>
    <Head :title="club ? `Members · ${club.name}` : 'All members'" />
    <AppLayout wide>
        <div class="grid gap-5">
            <header class="flex flex-wrap items-center justify-between gap-3">
                <div>
                    <h1 class="text-xl font-bold">Members<template v-if="club"> · {{ club.name }}</template></h1>
                    <Link v-if="club" :href="`/clubs/${club.slug}`" class="text-sm text-brand-blue">Back to club</Link>
                </div>
                <div v-if="club" class="flex flex-wrap gap-2">
                    <a :href="`/clubs/${club.slug}/members/export/xlsx?${exportQuery}`"><Button variant="secondary" size="sm"><Icon name="file" :size="16" /> 10 Full Member List (Excel)</Button></a>
                    <a :href="`/clubs/${club.slug}/members/export/pdf?${exportQuery}`"><Button variant="secondary" size="sm">PDF</Button></a>
                    <Button size="sm" variant="secondary" @click="router.post(`/clubs/${club.slug}/members/remind`, {}, { preserveScroll: true })"><Icon name="bell" :size="16" /> Remind unpaid</Button>
                </div>
            </header>

            <dl class="grid grid-cols-4 gap-2 sm:grid-cols-8">
                <button v-for="[key, label] in cards" :key="key" type="button" class="rounded-card border border-line bg-surface p-3 text-center shadow-card transition hover:shadow-pop" @click="apply(key === 'total' ? {} : ['paid', 'pending', 'unpaid'].includes(key) ? { payment: [key] } : { status: [key] })">
                    <dd class="text-xl font-bold tabular-nums">{{ counts[key] ?? 0 }}</dd>
                    <dt class="text-xs text-ink-muted">{{ label }}</dt>
                </button>
            </dl>

            <FilterBar v-model:search="search" placeholder="Search name, student ID, email or programme">
                <Button variant="secondary" @click="open = true"><Icon name="settings" :size="16" /> Filters<template v-if="activeCount"> ({{ activeCount }})</template></Button>
                <Button v-if="activeCount" variant="ghost" @click="clear">Clear</Button>
                <Button variant="ghost" @click="saveView"><Icon name="bookmark" :size="16" /> Save view</Button>
            </FilterBar>

            <div v-if="views.length" class="flex flex-wrap gap-2">
                <span v-for="v in views" :key="v.id" class="inline-flex items-center gap-1 rounded-full bg-surface-muted px-3 py-1 text-sm">
                    <button type="button" class="font-medium" @click="router.get(url, v.filters, { preserveScroll: true })">{{ v.name }}</button>
                    <button type="button" class="text-ink-muted hover:text-danger" :aria-label="`Delete view ${v.name}`" @click="router.delete(`/saved-views/${v.id}`, { preserveScroll: true })">✕</button>
                </span>
            </div>

            <p class="text-xs text-ink-muted">Sorted by {{ sort.map(([k = '', d]) => `${sortFields[k as keyof typeof sortFields] ?? k} ${d === 'asc' ? '↑' : '↓'}`).join(', then ') }} · click a column to sort by it first.</p>

            <div class="overflow-x-auto rounded-card border border-line bg-surface">
                <table class="w-full text-left text-sm">
                    <thead class="bg-surface-muted text-left text-xs uppercase tracking-wide text-ink-muted">
                        <tr>
                            <th v-if="!club" class="px-3 py-3"><button type="button" class="font-medium uppercase tracking-wide hover:text-ink" @click="toggleSort('club')">Club</button></th>
                            <th class="px-3 py-3"><button type="button" class="font-medium uppercase tracking-wide hover:text-ink" @click="toggleSort('name')">Name</button></th>
                            <th class="px-3 py-3"><button type="button" class="font-medium uppercase tracking-wide hover:text-ink" @click="toggleSort('student_number')">Student ID</button></th>
                            <th class="px-3 py-3 font-medium">Position</th>
                            <th class="px-3 py-3"><button type="button" class="font-medium uppercase tracking-wide hover:text-ink" @click="toggleSort('programme')">Programme</button></th>
                            <th class="px-3 py-3"><button type="button" class="font-medium uppercase tracking-wide hover:text-ink" @click="toggleSort('year_group')">Year</button></th>
                            <th class="px-3 py-3"><button type="button" class="font-medium uppercase tracking-wide hover:text-ink" @click="toggleSort('status')">Status</button></th>
                            <th class="px-3 py-3 font-medium">Payment</th>
                            <th class="px-3 py-3"><button type="button" class="font-medium uppercase tracking-wide hover:text-ink" @click="toggleSort('expires')">Expires</button></th>
                            <th class="px-3 py-3 text-right"><button type="button" class="font-medium uppercase tracking-wide hover:text-ink" @click="toggleSort('events')">Events</button></th>
                            <th class="px-3 py-3 text-right"><button type="button" class="font-medium uppercase tracking-wide hover:text-ink" @click="toggleSort('cca')">CCA</button></th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-for="r in rows" :key="r.id" class="border-t border-line">
                            <td v-if="!club" class="px-3 py-2">{{ r.club }}</td>
                            <td class="px-3 py-2"><div class="font-medium">{{ r.name }}</div><div class="text-xs text-ink-muted">{{ r.email }}</div></td>
                            <td class="px-3 py-2 font-mono text-xs">{{ r.student_number }}</td>
                            <td class="px-3 py-2">{{ r.position }}</td>
                            <td class="px-3 py-2">{{ r.programme_short }}</td>
                            <td class="px-3 py-2">{{ r.year_group }}</td>
                            <td class="px-3 py-2"><Badge :tone="tone[r.status] ?? 'neutral'">{{ r.status }}</Badge></td>
                            <td class="px-3 py-2"><Badge :tone="tone[r.payment] ?? 'neutral'">{{ r.payment }}</Badge><span v-if="r.method" class="ml-1 text-xs text-ink-muted">{{ r.method }}<template v-if="r.collected_by"> · {{ r.collected_by }}</template></span></td>
                            <td class="px-3 py-2 text-xs">{{ r.expires ? date(r.expires) : '—' }}</td>
                            <td class="px-3 py-2 text-right tabular-nums">{{ r.events }}</td>
                            <td class="px-3 py-2 text-right tabular-nums">{{ r.cca }}</td>
                        </tr>
                        <tr v-if="!rows.length"><td colspan="11" class="px-3 py-8 text-center text-ink-muted">No members match.</td></tr>
                    </tbody>
                </table>
            </div>
            <p v-if="truncated" class="text-xs text-ink-muted">Showing the first 500 — narrow the filters or export to see everything.</p>
        </div>

        <Drawer v-model:open="open" title="Filters">
            <form class="grid gap-5" @submit.prevent="apply(); open = false">
                <Select v-model="(f as Record<string, unknown>).session as string" label="Session" :options="[{ value: '', label: 'Any' }, ...options.sessions.map((s) => ({ value: s, label: s }))]" />
                <fieldset class="grid gap-2"><legend class="text-sm font-medium">Status</legend>
                    <CheckboxGroup v-model="f.status as string[]" :options="{ active: 'Active', grace: 'Grace', terminated: 'Terminated', alumni: 'Alumni', pending: 'Pending' }" :columns="2" />
                </fieldset>
                <fieldset class="grid gap-2"><legend class="text-sm font-medium">Payment</legend>
                    <CheckboxGroup v-model="f.payment as string[]" :options="{ paid: 'Paid', pending: 'Pending', unpaid: 'Unpaid' }" :columns="3" />
                    <CheckboxGroup v-model="f.method as string[]" :options="{ online: 'Online', cash: 'Cash', free: 'Free' }" :columns="3" />
                </fieldset>
                <fieldset v-if="options.collectors.length" class="grid gap-2"><legend class="text-sm font-medium">Cash collected by</legend><CheckboxGroup v-model="f.collected_by as string[]" :options="toOptions(options.collectors)" /></fieldset>
                <fieldset class="grid gap-2"><legend class="text-sm font-medium">Position</legend><CheckboxGroup v-model="f.position as string[]" :options="toOptions(options.positions)" :columns="2" /></fieldset>
                <fieldset class="grid gap-2"><legend class="text-sm font-medium">Year group</legend><CheckboxGroup v-model="f.year_group as string[]" :options="toOptions(options.year_groups)" :columns="2" /></fieldset>
                <fieldset class="grid gap-2"><legend class="text-sm font-medium">Programme</legend><CheckboxGroup v-model="f.programme_short as string[]" :options="toOptions(options.programmes)" :columns="3" /></fieldset>
                <fieldset class="grid gap-2"><legend class="text-sm font-medium">Intake</legend><CheckboxGroup v-model="f.intake as string[]" :options="toOptions(options.intakes)" :columns="3" /></fieldset>
                <div class="grid grid-cols-2 gap-3">
                    <Input v-model="(f as Record<string, unknown>).joined_from as string" type="date" label="Joined from" />
                    <Input v-model="(f as Record<string, unknown>).joined_to as string" type="date" label="Joined to" />
                    <Input v-model="(f as Record<string, unknown>).expiring_days as string" type="number" label="Expiring within (days)" />
                    <Input v-model="(f as Record<string, unknown>).min_events as string" type="number" label="Min. events attended" />
                    <Input v-model="(f as Record<string, unknown>).min_cca as string" type="number" label="Min. CCA hours" />
                </div>
                <template v-if="!club">
                    <fieldset class="grid gap-2"><legend class="text-sm font-medium">Club</legend><CheckboxGroup v-model="f.club_id as string[]" :options="Object.fromEntries(options.clubs.map((c) => [c.value, c.label]))" /></fieldset>
                    <fieldset class="grid gap-2"><legend class="text-sm font-medium">Category</legend><CheckboxGroup v-model="f.category as string[]" :options="toOptions(options.categories)" /></fieldset>
                    <fieldset class="grid gap-2"><legend class="text-sm font-medium">Club status</legend><CheckboxGroup v-model="f.club_status as string[]" :options="{ active: 'Active', at_risk: 'At-risk', inactive: 'Inactive' }" :columns="3" /></fieldset>
                </template>
                <Button type="submit">Apply filters</Button>
            </form>
        </Drawer>
    </AppLayout>
</template>
