<script setup lang="ts">
import Avatar from '@/Components/ui/Avatar.vue';
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
}
interface Position {
    id: number;
    position: string;
    is_compulsory: boolean;
    winner_id: number | null;
    voted_for: number | null;
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
        positions: Position[];
    } | null;
}>();

const setup = useForm({ nominations_open_at: '', nominations_close_at: '', voting_open_at: '', voting_close_at: '', allow_alumni_vote: false, extra_positions: [] as string[] });
const extra = ref('');
const addExtra = (): void => {
    if (extra.value.trim()) setup.extra_positions.push(extra.value.trim());
    extra.value = '';
};

const standing = ref<Position | null>(null);
const nomination = useForm({ statement: '', photo: [] as File[] });
const nominate = (): void =>
    nomination.transform((d) => ({ statement: d.statement, photo: d.photo[0] ?? null })).post(`/election-positions/${standing.value!.id}/nominate`, { preserveScroll: true, forceFormData: true, onSuccess: () => ((standing.value = null), nomination.reset()) });
const vote = (p: Position, c: CandidateCard): void => {
    if (confirm(`Vote for ${c.name} as ${p.position}? You can't change your vote.`)) router.post(`/election-positions/${p.id}/vote`, { candidate_id: c.id }, { preserveScroll: true });
};
const phaseLabel = computed(() => ({ upcoming: 'Nominations open soon', nominating: 'Nominations open', between: 'Voting opens soon', voting: 'Voting open', closed: 'Voting closed', published: 'Results published' })[props.election?.phase ?? 'upcoming']);
const flagText = { no_candidate: 'compulsory position without a candidate', uncontested: 'single candidate (flagged for SLD)', tie: 'tied — hold a run-off' };
</script>

<template>
    <Head :title="`Election · ${agm.club.name}`" />
    <AppLayout>
        <div class="grid gap-6">
            <header>
                <Link :href="manages ? `/clubs/${agm.club.slug}/agm` : `/clubs/${agm.club.slug}`" class="text-sm text-ink-muted hover:underline">← {{ agm.club.name }}</Link>
                <h1 class="text-2xl font-bold">Committee election<template v-if="election"> {{ election.term }}</template></h1>
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
                            <Badge v-for="(p, i) in setup.extra_positions" :key="p" tone="info">{{ p }} <button type="button" class="ml-1" @click="setup.extra_positions.splice(i, 1)">×</button></Badge>
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
                    <p>{{ election.allow_alumni_vote ? 'Alumni may vote' : 'Active members vote' }} · one vote per position</p>
                </section>

                <ul v-if="election.flags.length" class="grid gap-1 rounded-card border border-warning/40 bg-warning/5 p-4 text-sm">
                    <li v-for="f in election.flags" :key="f.position + f.issue"><strong>{{ f.position }}</strong>: {{ flagText[f.issue] }}</li>
                </ul>

                <Card v-for="p in election.positions" :key="p.id">
                    <template #title>
                        {{ p.position }} <Badge v-if="p.is_compulsory" class="ml-2">compulsory</Badge>
                    </template>
                    <ul class="grid gap-3 sm:grid-cols-2">
                        <li v-for="c in p.candidates" :key="c.id" class="grid gap-2 rounded-card border p-4" :class="p.winner_id === c.id ? 'border-success bg-success/5' : 'border-line'">
                            <div class="flex items-center gap-3">
                                <Avatar :src="c.photo_url" :name="c.name" size="md" />
                                <div class="min-w-0 flex-1">
                                    <p class="font-semibold">{{ c.name }} <Badge v-if="p.winner_id === c.id" tone="success">Elected</Badge></p>
                                    <p class="text-xs text-ink-muted">{{ c.programme }} · {{ c.year_group }}</p>
                                </div>
                                <span v-if="c.votes !== null" class="text-lg font-bold tabular-nums">{{ c.votes }}</span>
                            </div>
                            <p class="whitespace-pre-line text-sm">{{ c.statement }}</p>
                            <Button v-if="election.phase === 'voting' && !p.voted_for" size="sm" @click="vote(p, c)">Vote</Button>
                            <p v-else-if="p.voted_for === c.id" class="text-xs font-medium text-success">✓ Your vote</p>
                        </li>
                        <li v-if="!p.candidates.length" class="text-sm text-ink-muted">No candidates yet.</li>
                    </ul>
                    <Button v-if="election.phase === 'nominating'" variant="secondary" size="sm" class="mt-3" @click="standing = p">Stand for {{ p.position }}</Button>
                </Card>

                <Button v-if="manages && election.phase === 'closed'" class="justify-self-start" @click="router.post(`/elections/${election.id}/publish`, {}, { preserveScroll: true })">Publish results</Button>
            </template>
        </div>

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
