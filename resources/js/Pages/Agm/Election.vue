<script setup lang="ts">
import Avatar from '@/Components/ui/Avatar.vue';
import PersonPicker from '@/Components/ui/PersonPicker.vue';
import Badge from '@/Components/ui/Badge.vue';
import Button from '@/Components/ui/Button.vue';
import Card from '@/Components/ui/Card.vue';
import Checkbox from '@/Components/ui/Checkbox.vue';
import EmptyState from '@/Components/ui/EmptyState.vue';
import FileUpload from '@/Components/ui/FileUpload.vue';
import Input from '@/Components/ui/Input.vue';
import Modal from '@/Components/ui/Modal.vue';
import Textarea from '@/Components/ui/Textarea.vue';
import AppLayout from '@/Layouts/AppLayout.vue';
import { dateTime } from '@/lib/format';
import { Head, Link, router, useForm } from '@inertiajs/vue3';
import { computed, ref } from 'vue';

interface CandidateCard {
    id: number;
    name: string;
    statement: string;
    photo_url: string | null;
    programme: string | null;
    year_group: string | null;
    votes: number | null;
    is_me: boolean;
    share_url: string;
    qr_svg: string | null;
}
interface Position {
    id: number;
    position: string;
    is_compulsory: boolean;
    winner_id: number | null;
    voted_for: number | null;
    changes_left: number;
    candidates: CandidateCard[];
}

const props = defineProps<{
    agm: { id: number; club: { slug: string; name: string }; starts_at: string };
    manages: boolean;
    election: {
        id: number;
        phase: 'upcoming' | 'nominating' | 'between' | 'voting' | 'closed' | 'published';
        term: string;
        allow_alumni_vote: boolean;
        nominations: [string, string];
        voting: [string, string];
        flags: { position: string; issue: 'no_candidate' | 'uncontested' | 'tie' }[];
        handover_on: string;
        handed_over: boolean;
        vote_changes: number;
        positions: Position[];
    } | null;
    highlight: number | null;
    positions: string[];
}>();

// Committee: add or remove positions before voting, and name candidates by name or student ID.
const newPosition = ref('');
const addPosition = (): void => {
    if (newPosition.value.trim())
        router.post(`/elections/${props.election!.id}/positions`, { position: newPosition.value.trim() }, { preserveScroll: true, onSuccess: () => (newPosition.value = '') });
};
const removePosition = (p: Position): void => {
    if (confirm(`Remove ${p.position} from the ballot?`)) router.delete(`/election-positions/${p.id}`, { preserveScroll: true });
};
const naming = ref<Position | null>(null);
const pick = useForm({ student_number: '', statement: '' });
const typed = ref(false);
const nameCandidate = (): void =>
    pick.post(`/election-positions/${naming.value!.id}/nominate-member`, { preserveScroll: true, onSuccess: () => ((naming.value = null), pick.reset(), (typed.value = false)) });
const sharing = ref<CandidateCard | null>(null);
const copied = ref(false);
const copyLink = async (url: string): Promise<void> => {
    await navigator.clipboard.writeText(url);
    copied.value = true;
};
const editable = computed(() => ['upcoming', 'nominating', 'between'].includes(props.election?.phase ?? ''));

const setup = useForm({ nominations_open_at: '', nominations_close_at: '', voting_open_at: '', voting_close_at: '', allow_alumni_vote: false, extra_positions: [] as string[] });
const extra = ref('');
const addExtra = (): void => {
    if (extra.value.trim()) setup.extra_positions.push(extra.value.trim());
    extra.value = '';
};

const standing = ref<Position | null>(null);
const nomination = useForm({ statement: '', photo: [] as File[] });
const nominate = (): void =>
    nomination
        .transform((d) => ({ statement: d.statement, photo: d.photo[0] ?? null }))
        .post(`/election-positions/${standing.value!.id}/nominate`, { preserveScroll: true, forceFormData: true, onSuccess: () => ((standing.value = null), nomination.reset()) });
const vote = (p: Position, c: CandidateCard): void => {
    const change = p.voted_for !== null;
    const msg = change
        ? `Change your ${p.position} vote to ${c.name}? You can change it ${p.changes_left} more time${p.changes_left === 1 ? '' : 's'}${p.changes_left === 1 ? ' (this is the last)' : ''}.`
        : `Vote for ${c.name} as ${p.position}? You can change your mind up to ${props.election!.vote_changes} times before voting closes.`;
    if (confirm(msg)) router.post(`/election-positions/${p.id}/vote`, { candidate_id: c.id }, { preserveScroll: true });
};
const phaseLabel = computed(
    () =>
        ({
            upcoming: 'Nominations open soon',
            nominating: 'Nominations open',
            between: 'Voting opens soon',
            voting: 'Voting open',
            closed: 'Voting closed',
            published: 'Results published',
        })[props.election?.phase ?? 'upcoming'],
);
const flagText = { no_candidate: 'compulsory position without a candidate', uncontested: 'single candidate (flagged for SLD)', tie: 'tied — hold a run-off' };
</script>

<template>
    <Head :title="`Election · ${agm.club.name}`" />
    <AppLayout>
        <div class="grid gap-6">
            <header>
                <Link :href="manages ? `/clubs/${agm.club.slug}/agm` : `/clubs/${agm.club.slug}`" class="text-sm text-ink-muted hover:underline">← {{ agm.club.name }}</Link>
                <h1 class="text-2xl font-bold">
                    Committee election<template v-if="election"> {{ election.term }}</template>
                </h1>
            </header>

            <template v-if="!election">
                <Card v-if="manages">
                    <template #title>Set up the election</template>
                    <form class="grid gap-4" @submit.prevent="setup.post(`/agms/${agm.id}/election`, { preserveScroll: true })">
                        <p class="text-sm text-ink-muted">President, Vice President, Secretary and Treasurer are always on the ballot. Add other positions if needed.</p>
                        <div class="grid gap-4 sm:grid-cols-2">
                            <Input v-model="setup.nominations_open_at" type="datetime-local" label="Nominations open" :error="setup.errors.nominations_open_at" />
                            <Input v-model="setup.nominations_close_at" type="datetime-local" label="Nominations close" :error="setup.errors.nominations_close_at" />
                            <Input v-model="setup.voting_open_at" type="datetime-local" label="Voting opens" :error="setup.errors.voting_open_at" />
                            <Input v-model="setup.voting_close_at" type="datetime-local" label="Voting closes" :error="setup.errors.voting_close_at" />
                        </div>
                        <div class="flex items-end gap-2">
                            <div class="flex-1"><Input v-model="extra" label="Extra position" placeholder="e.g. Publicity Director" @keydown.enter.prevent="addExtra" /></div>
                            <Button type="button" variant="secondary" @click="addExtra">Add</Button>
                        </div>
                        <div v-if="setup.extra_positions.length" class="flex flex-wrap gap-2">
                            <Badge v-for="(p, i) in setup.extra_positions" :key="p" tone="info"
                                >{{ p }} <button type="button" class="ml-1" @click="setup.extra_positions.splice(i, 1)">×</button></Badge
                            >
                        </div>
                        <Checkbox v-model="setup.allow_alumni_vote" label="Allow alumni members to vote" />
                        <div><Button type="submit" :loading="setup.processing">Create election</Button></div>
                    </form>
                </Card>
                <EmptyState v-else title="No election yet" description="The committee will announce the nomination and voting dates." />
            </template>

            <template v-else>
                <section class="grid gap-2 rounded-card bg-gradient-to-r from-brand-yellow/25 to-brand-orange/15 p-5 text-sm sm:grid-cols-3">
                    <p class="text-lg font-bold sm:col-span-3">{{ phaseLabel }}</p>
                    <p>Nominations: {{ dateTime(election.nominations[0]) }} → {{ dateTime(election.nominations[1]) }}</p>
                    <p>Voting: {{ dateTime(election.voting[0]) }} → {{ dateTime(election.voting[1]) }}</p>
                    <p>
                        {{ election.allow_alumni_vote ? 'Alumni may vote' : 'Active members vote' }} · one ticket per position · change your mind up to
                        {{ election.vote_changes }} times
                    </p>
                    <p v-if="election.phase === 'published'" class="font-medium sm:col-span-3">
                        {{
                            election.handed_over
                                ? 'The new committee has taken over.'
                                : `The current committee hands over until ${new Date(election.handover_on).toLocaleDateString('en-MY', { dateStyle: 'long' })}, when the new committee takes the titles and access.`
                        }}
                    </p>
                </section>

                <section v-if="manages && editable" class="flex flex-wrap items-end gap-2 rounded-card border border-line bg-surface p-4">
                    <div class="min-w-48 flex-1">
                        <Input v-model="newPosition" label="Add a position to the ballot" placeholder="e.g. Publicity Director" @keydown.enter.prevent="addPosition" />
                    </div>
                    <Button variant="secondary" @click="addPosition">Add position</Button>
                </section>

                <ul v-if="election.flags.length" class="grid gap-1 rounded-card border border-warning/40 bg-warning/5 p-4 text-sm">
                    <li v-for="f in election.flags" :key="f.position + f.issue">
                        <strong>{{ f.position }}</strong
                        >: {{ flagText[f.issue] }}
                    </li>
                </ul>

                <Card v-for="p in election.positions" :key="p.id">
                    <template #title>
                        <span class="flex flex-wrap items-center gap-2">
                            {{ p.position }} <Badge v-if="p.is_compulsory">compulsory</Badge>
                            <button
                                v-if="manages && editable && !p.is_compulsory"
                                type="button"
                                class="ml-auto text-xs font-normal text-ink-muted hover:text-danger"
                                @click="removePosition(p)"
                            >
                                Remove
                            </button>
                        </span>
                    </template>
                    <ul class="grid gap-3 sm:grid-cols-2">
                        <li
                            v-for="c in p.candidates"
                            :key="c.id"
                            class="grid gap-2 rounded-card border p-4 transition"
                            :class="[p.winner_id === c.id ? 'border-success bg-success/5' : 'border-line', highlight === c.id ? 'ring-4 ring-brand-magenta/30' : '']"
                        >
                            <div class="flex items-center gap-3">
                                <Avatar :src="c.photo_url" :name="c.name" size="md" />
                                <div class="min-w-0 flex-1">
                                    <p class="font-semibold">{{ c.name }} <Badge v-if="p.winner_id === c.id" tone="success">Elected</Badge></p>
                                    <p class="text-xs text-ink-muted">{{ c.programme }} · {{ c.year_group }}</p>
                                </div>
                                <span v-if="c.votes !== null" class="text-lg font-bold tabular-nums">{{ c.votes }}</span>
                            </div>
                            <p class="whitespace-pre-line text-sm">{{ c.statement }}</p>
                            <p v-if="c.is_me" class="text-xs text-ink-muted">You cannot vote for yourself.</p>
                            <p v-else-if="p.voted_for === c.id" class="text-xs font-medium text-success">✓ Your vote</p>
                            <Button
                                v-else-if="election.phase === 'voting' && (!p.voted_for || p.changes_left > 0)"
                                size="sm"
                                :variant="p.voted_for ? 'secondary' : 'primary'"
                                @click="vote(p, c)"
                            >
                                {{ p.voted_for ? `Change vote to ${c.name.split(' ')[0]}` : 'Vote' }}
                            </Button>
                            <button v-if="c.qr_svg" type="button" class="justify-self-start text-xs font-medium text-brand-blue" @click="sharing = c">Share QR to promote</button>
                        </li>
                        <li v-if="!p.candidates.length" class="text-sm text-ink-muted">No candidates yet.</li>
                    </ul>
                    <p v-if="election.phase === 'voting' && p.voted_for" class="mt-2 text-xs text-ink-muted">
                        {{ p.changes_left > 0 ? `You can change this vote ${p.changes_left} more time${p.changes_left === 1 ? '' : 's'}.` : 'Your vote is final.' }}
                    </p>
                    <div v-if="election.phase === 'nominating' || (manages && editable)" class="mt-3 flex flex-wrap gap-2">
                        <Button v-if="election.phase === 'nominating'" variant="secondary" size="sm" @click="standing = p">Stand for {{ p.position }}</Button>
                        <Button v-if="manages && editable" variant="secondary" size="sm" @click="naming = p">＋ Name a candidate</Button>
                    </div>
                </Card>

                <Button
                    v-if="manages && election.phase === 'closed'"
                    class="justify-self-start"
                    @click="router.post(`/elections/${election.id}/publish`, {}, { preserveScroll: true })"
                    >Publish results</Button
                >
            </template>
        </div>

        <Modal :open="naming !== null" :title="`Name a candidate for ${naming?.position}`" @update:open="(v: boolean) => v || (naming = null)">
            <form class="grid gap-4" @submit.prevent="nameCandidate">
                <PersonPicker
                    v-if="!typed"
                    v-model="pick.student_number"
                    label="Name or student ID"
                    placeholder="Type a name (e.g. tan) or student ID"
                    :error="pick.errors.student_number"
                />
                <Input v-else v-model="pick.student_number" label="Student ID" placeholder="e.g. QIU-202407-0001" required :error="pick.errors.student_number" />
                <button type="button" class="justify-self-start text-xs text-brand-blue" @click="((typed = !typed), (pick.student_number = ''))">
                    {{ typed ? 'Search by name instead' : "Can't find them? Type the student ID" }}
                </button>
                <Textarea v-model="pick.statement" label="Why they are standing (optional)" :rows="3" />
                <p class="text-xs text-ink-muted">They must be an active member who can serve the full {{ election?.term }} term.</p>
                <Button type="submit" :disabled="!pick.student_number" :loading="pick.processing">Add candidate</Button>
            </form>
        </Modal>

        <Modal :open="sharing !== null" :title="`Vote ${sharing?.name}`" @update:open="(v: boolean) => v || ((sharing = null), (copied = false))">
            <div v-if="sharing" class="grid justify-items-center gap-3 text-center text-sm">
                <div class="size-56 rounded-card bg-white p-3 [&_svg]:size-full" v-html="sharing.qr_svg" />
                <p class="text-ink-muted">Members scan this to open the ballot with {{ sharing.name }} highlighted. Print it or share it in your club chats.</p>
                <Button variant="secondary" @click="copyLink(sharing.share_url)">{{ copied ? 'Link copied' : 'Copy link' }}</Button>
            </div>
        </Modal>

        <Modal :open="standing !== null" :title="`Stand for ${standing?.position}`" @update:open="(v: boolean) => v || (standing = null)">
            <form class="grid gap-4" @submit.prevent="nominate">
                <p class="text-sm text-ink-muted">You must be an active member and able to serve the full {{ election?.term }} term (July–June).</p>
                <Textarea v-model="nomination.statement" label="Your statement" :rows="5" :error="nomination.errors.statement" />
                <FileUpload v-model="nomination.photo" label="Candidate photo (optional)" accept="image/*" />
                <Button type="submit" :loading="nomination.processing">Submit nomination</Button>
            </form>
        </Modal>
    </AppLayout>
</template>
