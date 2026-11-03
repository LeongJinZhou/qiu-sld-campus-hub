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
import PortalLayout from '@/Layouts/PortalLayout.vue';
import { copy } from '@/lib/clone';
import { Head, Link, router } from '@inertiajs/vue3';
import { watchDebounced } from '@vueuse/core';
import { computed, reactive, ref } from 'vue';

type Filters = Record<string, string | number | string[] | string[][] | undefined>;
interface ClubLine {
    club: string;
    club_id: number;
    club_slug: string;
    position: string;
    status: string;
    payment: string;
    method: string | null;
    collected_by: string | null;
    expires: string | null;
}
interface Row {
    id: number | string;
    /** All clubs (SLD): the person's clubs, one row per person. */
    clubs?: ClubLine[];
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
    portal?: boolean;
    committeeFees?: { name: string; student_number: string | null; position: string | null; status: 'paid' | 'unpaid' | 'checking' | 'no_fee' }[];
    filters: Filters;
    counts: Record<string, number>;
    rows: Row[];
    pagination: { page: number; per_page: number; total: number; last_page: number };
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

// SLD stays inside the admin portal; the club's own committee and advisor use the club page.
const url = props.club ? (props.portal ? `/admin/clubs/${props.club.slug}/members` : `/clubs/${props.club.slug}/members`) : '/admin/members';
const layout = computed(() => (props.portal ? PortalLayout : AppLayout));
const layoutProps = computed(() => (props.portal ? { title: props.club ? `Members · ${props.club.name}` : 'All members' } : { wide: true }));
const feeTone = { paid: 'success', unpaid: 'danger', checking: 'info', no_fee: 'neutral' } as const;
const feeLabel = { paid: 'Paid', unpaid: 'Not paid', checking: 'Payment being checked', no_fee: 'No fee set' } as const;
const LISTS = ['status', 'position', 'payment', 'method', 'collected_by', 'year_group', 'programme_short', 'intake', 'club_id', 'category', 'club_status'] as const;
// The drawer's state, rebuilt from what the server applied (club ids as numbers, so their boxes show ticked).
const fromServer = (): Filters => {
    const out: Filters = { ...(copy(props.filters) as Filters) };
    for (const k of LISTS) out[k] = Array.isArray(out[k]) ? (out[k] as string[]).map((v) => (k === 'club_id' ? Number(v) : v)) as string[] : [];
    delete out.sort;
    delete out.search;
    return out;
};
const f = reactive<Filters>(fromServer());
const sort = ref<string[][]>(Array.isArray(props.filters.sort) ? (props.filters.sort as string[][]) : [['name', 'asc']]);
const search = ref(typeof props.filters.search === 'string' ? props.filters.search : '');
const open = ref(false);

// Sort levels travel as "field:dir" strings: nested arrays do not survive the query string.
const query = (extra: Filters = {}): Record<string, unknown> => ({
    ...Object.fromEntries(Object.entries(f).filter(([, v]) => (Array.isArray(v) ? v.length : v !== '' && v !== undefined && v !== null))),
    search: search.value || undefined,
    sort: sort.value.map(([k, d]) => `${k}:${d}`),
    ...extra,
});
const visit = (extra: Filters = {}): void => {
    router.get(url, query(extra) as never, { preserveState: true, preserveScroll: true, replace: true, onSuccess: () => Object.assign(f, fromServer()) });
};
const apply = (): void => visit();
// The stat cards replace the status/payment filter (Total clears both) and the drawer shows it.
const pick = (key: string): void => {
    f.status = ['active', 'grace', 'terminated', 'alumni'].includes(key) ? [key] : [];
    f.payment = ['paid', 'pending', 'unpaid'].includes(key) ? [key] : [];
    visit();
};
watchDebounced(search, () => apply(), { debounce: 300 });
const goPage = (page: number): void => visit({ page } as Filters);
const toOptions = (list: string[]) => Object.fromEntries(list.map((v) => [v, v]));
const activeCount = computed(() => Object.entries(props.filters).filter(([k, v]) => !['search', 'sort'].includes(k) && (Array.isArray(v) ? v.length : v !== undefined)).length);
const clear = (): void => {
    for (const k of Object.keys(f)) delete f[k];
    Object.assign(f, Object.fromEntries(LISTS.map((k) => [k, []])));
    search.value = '';
    router.get(url, {}, { preserveScroll: true, onSuccess: () => Object.assign(f, fromServer()) });
};
const sortFields = { name: 'Name', student_number: 'Student ID', programme: 'Programme', year_group: 'Year group', status: 'Status', joined: 'Joined', expires: 'Expires', events: 'Events', cca: 'CCA', club: 'Club' };
const toggleSort = (field: string): void => {
    const [first] = sort.value;
    sort.value = [[field, first?.[0] === field && first[1] === 'asc' ? 'desc' : 'asc'], ...sort.value.filter(([k]) => k !== field)].slice(0, 3);
    apply();
};
const openView = (filters: Filters): void => {
    const saved = filters.sort;
    sort.value = Array.isArray(saved) && saved.length ? (saved as unknown[]).map((x) => (Array.isArray(x) ? (x as string[]) : String(x).split(':'))) : [['name', 'asc']];
    for (const k of LISTS) f[k] = Array.isArray(filters[k]) ? (filters[k] as string[]) : [];
    Object.assign(f, Object.fromEntries(Object.entries(filters).filter(([k]) => !(LISTS as readonly string[]).includes(k) && k !== 'sort' && k !== 'search')));
    search.value = typeof filters.search === 'string' ? filters.search : '';
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
const tone: Record<string, 'success' | 'warning' | 'danger' | 'info' | 'neutral'> = { active: 'success', grace: 'warning', terminated: 'danger', alumni: 'info', pending: 'neutral', paid: 'success', unpaid: 'danger', committee: 'info', advisor: 'neutral', exempt: 'neutral' };
</script>

<template>
    <Head :title="club ? `Members · ${club.name}` : 'All members'" />
    <component :is="layout" v-bind="layoutProps">
        <div class="grid gap-5">
            <header class="flex flex-wrap items-center justify-between gap-3">
                <div>
                    <h1 class="text-xl font-bold">Members<template v-if="club"> · {{ club.name }}</template></h1>
                    <Link v-if="club" :href="portal ? '/admin/clubs' : `/clubs/${club.slug}`" class="text-sm text-brand-blue">{{ portal ? 'All clubs' : 'Back to club' }}</Link>
                </div>
                <div v-if="club" class="flex flex-wrap gap-2">
                    <a :href="`/clubs/${club.slug}/members/export/xlsx?${exportQuery}`"><Button variant="secondary" size="sm"><Icon name="file" :size="16" /> 10 Full Member List (Excel)</Button></a>
                    <a :href="`/clubs/${club.slug}/members/export/pdf?${exportQuery}`"><Button variant="secondary" size="sm">PDF</Button></a>
                    <Button size="sm" variant="secondary" @click="router.post(`/clubs/${club.slug}/members/remind`, {}, { preserveScroll: true })"><Icon name="bell" :size="16" /> Remind unpaid</Button>
                </div>
            </header>

            <section v-if="committeeFees?.length" class="grid gap-2 rounded-card border border-line bg-surface p-4 shadow-card">
                <h2 class="text-sm font-semibold">Committee club fees · this session</h2>
                <ul class="grid gap-2 sm:grid-cols-2">
                    <li v-for="c in committeeFees" :key="`${c.position}-${c.student_number}`" class="flex items-center justify-between gap-2 text-sm">
                        <span class="min-w-0 truncate"
                            ><strong>{{ c.position }}</strong> · {{ c.name }} <span class="text-ink-muted">{{ c.student_number }}</span></span
                        >
                        <Badge :tone="feeTone[c.status]">{{ feeLabel[c.status] }}</Badge>
                    </li>
                </ul>
            </section>

            <dl class="grid grid-cols-4 gap-2 sm:grid-cols-8">
                <button v-for="[key, label] in cards" :key="key" type="button" class="rounded-card border border-line bg-surface p-3 text-center shadow-card transition hover:shadow-pop" @click="pick(key)">
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
                    <button type="button" class="font-medium" @click="openView(v.filters)">{{ v.name }}</button>
                    <button type="button" class="text-ink-muted hover:text-danger" :aria-label="`Delete view ${v.name}`" @click="router.delete(`/saved-views/${v.id}`, { preserveScroll: true })">✕</button>
                </span>
            </div>

            <p class="text-xs text-ink-muted">Sorted by {{ sort.map(([k = '', d]) => `${sortFields[k as keyof typeof sortFields] ?? k} ${d === 'asc' ? '↑' : '↓'}`).join(', then ') }} · click a column to sort by it first.</p>

            <!-- All clubs: one row per person, their clubs listed together. -->
            <div v-if="!club" class="overflow-x-auto rounded-card border border-line bg-surface">
                <table class="w-full text-left text-sm">
                    <thead class="bg-surface-muted text-left text-xs uppercase tracking-wide text-ink-muted">
                        <tr>
                            <th class="px-3 py-3"><button type="button" class="font-medium uppercase tracking-wide hover:text-ink" @click="toggleSort('name')">Name</button></th>
                            <th class="px-3 py-3"><button type="button" class="font-medium uppercase tracking-wide hover:text-ink" @click="toggleSort('student_number')">Student ID</button></th>
                            <th class="px-3 py-3"><button type="button" class="font-medium uppercase tracking-wide hover:text-ink" @click="toggleSort('programme')">Programme</button></th>
                            <th class="px-3 py-3"><button type="button" class="font-medium uppercase tracking-wide hover:text-ink" @click="toggleSort('year_group')">Year</button></th>
                            <th class="px-3 py-3"><button type="button" class="font-medium uppercase tracking-wide hover:text-ink" @click="toggleSort('club')">Clubs</button></th>
                            <th class="px-3 py-3 text-right"><button type="button" class="font-medium uppercase tracking-wide hover:text-ink" @click="toggleSort('events')">Events</button></th>
                            <th class="px-3 py-3 text-right"><button type="button" class="font-medium uppercase tracking-wide hover:text-ink" @click="toggleSort('cca')">CCA</button></th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-for="r in rows" :key="r.id" class="border-t border-line align-top">
                            <td class="px-3 py-2"><div class="font-medium">{{ r.name }}</div><div class="text-xs text-ink-muted">{{ r.email }}</div></td>
                            <td class="px-3 py-2 font-mono text-xs">{{ r.student_number }}</td>
                            <td class="px-3 py-2">{{ r.programme_short }}</td>
                            <td class="px-3 py-2 whitespace-nowrap">{{ r.year_group }}</td>
                            <td class="px-3 py-2">
                                <ul class="grid min-w-[22rem] gap-2">
                                    <li v-for="c in r.clubs ?? []" :key="c.club_id" class="grid grid-cols-[minmax(0,1fr)_auto] items-center gap-x-3">
                                        <span class="min-w-0">
                                            <Link :href="`/admin/clubs/${c.club_slug}/members`" class="block truncate font-medium hover:underline">{{ c.club }}</Link>
                                            <span class="block truncate text-xs text-ink-muted">{{ c.position }}<template v-if="c.expires"> · until {{ date(c.expires) }}</template></span>
                                        </span>
                                        <span class="flex items-center gap-1">
                                            <Badge :tone="tone[c.status] ?? 'neutral'">{{ c.status }}</Badge>
                                            <Badge :tone="tone[c.payment] ?? 'neutral'">{{ c.payment }}</Badge>
                                        </span>
                                    </li>
                                </ul>
                            </td>
                            <td class="px-3 py-2 text-right tabular-nums">{{ r.events }}</td>
                            <td class="px-3 py-2 text-right tabular-nums">{{ r.cca }}</td>
                        </tr>
                        <tr v-if="!rows.length"><td colspan="7" class="px-3 py-8 text-center text-ink-muted">No members match.</td></tr>
                    </tbody>
                </table>
            </div>
            <div v-else class="overflow-x-auto rounded-card border border-line bg-surface">
                <table class="w-full text-left text-sm">
                    <thead class="bg-surface-muted text-left text-xs uppercase tracking-wide text-ink-muted">
                        <tr>
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
            <nav class="flex flex-wrap items-center justify-between gap-2 text-sm" aria-label="Pages">
                <p class="text-ink-muted">
                    <template v-if="pagination.total">
                        Showing {{ (pagination.page - 1) * pagination.per_page + 1 }}–{{ Math.min(pagination.page * pagination.per_page, pagination.total) }} of
                        <strong class="text-ink">{{ pagination.total.toLocaleString() }}</strong> members
                    </template>
                    <template v-else>0 members</template>
                </p>
                <div v-if="pagination.last_page > 1" class="flex items-center gap-1">
                    <button type="button" class="h-9 rounded-control border border-line px-3 disabled:opacity-40" :disabled="pagination.page <= 1" @click="goPage(pagination.page - 1)">‹ Previous</button>
                    <span class="px-2 tabular-nums">Page {{ pagination.page }} of {{ pagination.last_page }}</span>
                    <button type="button" class="h-9 rounded-control border border-line px-3 disabled:opacity-40" :disabled="pagination.page >= pagination.last_page" @click="goPage(pagination.page + 1)">
                        Next ›
                    </button>
                </div>
            </nav>
        </div>

        <Drawer v-model:open="open" title="Filters">
            <form class="grid gap-5" @submit.prevent="apply(); open = false">
                <Select v-model="(f as Record<string, unknown>).session as string" label="Session" :options="[{ value: '', label: 'Any' }, ...options.sessions.map((s) => ({ value: s, label: s }))]" />
                <fieldset class="grid gap-2"><legend class="text-sm font-medium">Status</legend>
                    <CheckboxGroup v-model="f.status as string[]" :options="{ active: 'Active', grace: 'Grace', terminated: 'Terminated', alumni: 'Alumni', pending: 'Pending', committee: 'Committee (not joined yet)', advisor: 'Advisor' }" :columns="2" />
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
    </component>
</template>
