<script setup lang="ts">
import Avatar from '@/Components/ui/Avatar.vue';
import Badge from '@/Components/ui/Badge.vue';
import Button from '@/Components/ui/Button.vue';
import Card from '@/Components/ui/Card.vue';
import FileUpload from '@/Components/ui/FileUpload.vue';
import Input from '@/Components/ui/Input.vue';
import Select from '@/Components/ui/Select.vue';
import Textarea from '@/Components/ui/Textarea.vue';
import PortalLayout from '@/Layouts/PortalLayout.vue';
import type { MemberCard, Option } from '@/types';
import { Head, router, useForm } from '@inertiajs/vue3';
import { computed, watch } from 'vue';

interface ClubData {
    id: number;
    slug: string;
    name: string;
    category_id: number | null;
    established_year: number | null;
    bio: string | null;
    instagram_handle: string | null;
    managed_by: string;
    logo_url: string | null;
    transferred_at: string | null;
}

const props = defineProps<{
    club: ClubData | null;
    categories: Option[];
    session?: string;
    isCurrentSession?: boolean;
    sessions?: string[];
    committee?: MemberCard[];
    advisors?: MemberCard[];
    compulsoryPositions?: string[];
    missingCompulsory?: string[];
    staff?: { user_id: number; label: string }[];
}>();

const details = useForm({
    name: props.club?.name ?? '',
    slug: props.club?.slug ?? '',
    category_id: props.club?.category_id ?? null,
    established_year: props.club?.established_year ?? null,
    bio: props.club?.bio ?? '',
    instagram_handle: props.club?.instagram_handle ?? '',
    logo: [] as File[],
});

// New clubs get a slug from the name until it is edited by hand.
watch(
    () => details.name,
    (name, previous) => {
        const slugify = (value: string) => value.toLowerCase().replace(/[^a-z0-9]+/g, '-').replace(/^-|-$/g, '');
        if (!props.club && (details.slug === '' || details.slug === slugify(previous))) {
            details.slug = slugify(name);
        }
    },
);

function saveDetails(): void {
    details
        .transform((data) => ({ ...data, logo: data.logo[0] ?? null, ...(props.club ? { _method: 'put' } : {}) }))
        .post(props.club ? `/admin/clubs/${props.club.slug}` : '/admin/clubs', { preserveScroll: true });
}

const assign = useForm({ session: props.session ?? '', student_number: '', position: '' });
const advisor = useForm({ session: props.session ?? '', user_id: null as number | null });

const holders = computed(() => new Map((props.committee ?? []).map((m) => [m.position, m])));
const optional = computed(() => (props.committee ?? []).filter((m) => !m.is_compulsory));

function assignTo(position: string): void {
    assign.position = position;
}

function submitAssign(): void {
    assign.post(`/admin/clubs/${props.club?.slug}/committee`, { preserveScroll: true, onSuccess: () => assign.reset('student_number', 'position') });
}

function submitAdvisor(): void {
    advisor.post(`/admin/clubs/${props.club?.slug}/advisors`, { preserveScroll: true, onSuccess: () => advisor.reset('user_id') });
}

function remove(member: MemberCard): void {
    if (confirm(`Remove ${member.name} (${member.position})?`)) {
        router.delete(`/admin/clubs/${props.club?.slug}/assignments/${member.assignment_id}`, { preserveScroll: true });
    }
}

function changeSession(event: Event): void {
    router.get(`/admin/clubs/${props.club?.slug}/edit`, { session: (event.target as HTMLSelectElement).value }, { preserveScroll: true });
}

const transfer = (): void => router.post(`/admin/clubs/${props.club?.slug}/transfer`, {}, { preserveScroll: true });
const takeBack = (): void => router.delete(`/admin/clubs/${props.club?.slug}/transfer`, { preserveScroll: true });
</script>

<template>
    <Head :title="club ? club.name : 'New club'" />
    <PortalLayout :title="club ? club.name : 'New club'">
        <div class="grid gap-6 lg:grid-cols-[1fr_22rem]">
            <div class="grid content-start gap-6">
                <Card>
                    <template #title>Club details</template>
                    <form class="grid gap-4" @submit.prevent="saveDetails">
                        <div class="flex items-center gap-4">
                            <Avatar :src="club?.logo_url" :name="details.name || 'Club'" size="lg" />
                            <FileUpload v-model="details.logo" label="Logo" accept="image/jpeg,image/png,image/webp" :error="details.errors.logo" class="flex-1" />
                        </div>
                        <div class="grid gap-4 sm:grid-cols-2">
                            <Input v-model="details.name" label="Name" required :error="details.errors.name" />
                            <Input v-model="details.slug" label="URL slug" required :error="details.errors.slug" />
                            <Select v-model="details.category_id" label="Category" :options="categories" placeholder="Choose a category" :error="details.errors.category_id" />
                            <Input v-model="details.established_year" type="number" label="Established year" :error="details.errors.established_year" />
                            <Input v-model="details.instagram_handle" label="Instagram handle" placeholder="gps_qiu" :error="details.errors.instagram_handle" />
                        </div>
                        <Textarea v-model="details.bio" label="Bio" :rows="3" :error="details.errors.bio" />
                        <Button type="submit" :loading="details.processing" class="justify-self-start">{{ club ? 'Save' : 'Create club' }}</Button>
                    </form>
                </Card>

                <Card v-if="club">
                    <template #title>Committee</template>
                    <template #actions>
                        <select :value="session" class="h-9 rounded-control border border-line px-2 text-sm" aria-label="Session" @change="changeSession">
                            <option v-for="name in sessions" :key="name" :value="name">{{ name }}</option>
                        </select>
                    </template>

                    <p v-if="!isCurrentSession" class="mb-4 text-sm text-ink-muted">Past session — kept as history.</p>

                    <ul class="grid gap-2">
                        <li v-for="position in compulsoryPositions" :key="position" class="flex flex-wrap items-center justify-between gap-3 rounded-control border border-line p-3">
                            <div class="flex items-center gap-3">
                                <Avatar :src="holders.get(position)?.photo_url" :name="holders.get(position)?.name ?? '?'" size="sm" />
                                <div class="grid">
                                    <span class="text-sm font-medium">{{ position }} <Badge tone="info">Compulsory</Badge></span>
                                    <span class="text-xs text-ink-muted">{{ holders.get(position)?.name ?? 'Vacant' }}</span>
                                </div>
                            </div>
                            <div class="flex flex-wrap gap-2">
                                <Button size="sm" variant="secondary" @click="assignTo(position)">{{ holders.get(position) ? 'Replace' : 'Assign' }}</Button>
                                <Button v-if="holders.get(position)" size="sm" variant="ghost" @click="remove(holders.get(position)!)">Remove</Button>
                            </div>
                        </li>
                        <li v-for="member in optional" :key="member.assignment_id" class="flex items-center justify-between gap-3 rounded-control border border-line p-3">
                            <div class="flex items-center gap-3">
                                <Avatar :src="member.photo_url" :name="member.name" size="sm" />
                                <div class="grid">
                                    <span class="text-sm font-medium">{{ member.position }}</span>
                                    <span class="text-xs text-ink-muted">{{ member.name }}</span>
                                </div>
                            </div>
                            <Button size="sm" variant="ghost" @click="remove(member)">Remove</Button>
                        </li>
                    </ul>

                    <form class="mt-5 grid gap-3 rounded-control bg-surface-muted p-4 sm:grid-cols-[1fr_1fr_auto] sm:items-end" @submit.prevent="submitAssign">
                        <Input v-model="assign.position" label="Position" placeholder="e.g. Publicity Director" required :error="assign.errors.position" />
                        <Input v-model="assign.student_number" label="Student ID" placeholder="QIU-202404-0001" required :error="assign.errors.student_number" />
                        <Button type="submit" :loading="assign.processing">Assign</Button>
                    </form>
                    <p v-if="missingCompulsory?.length" class="mt-3 text-sm text-warning">Still missing: {{ missingCompulsory.join(', ') }}</p>
                </Card>
            </div>

            <aside v-if="club" class="grid content-start gap-6">
                <Card>
                    <template #title>Advisors</template>
                    <ul class="mb-4 grid gap-2">
                        <li v-for="member in advisors" :key="member.assignment_id" class="flex items-center justify-between gap-2 text-sm">
                            <span>{{ member.name }}</span>
                            <Button size="sm" variant="ghost" @click="remove(member)">Remove</Button>
                        </li>
                        <li v-if="!advisors?.length" class="text-sm text-ink-muted">No advisor yet.</li>
                    </ul>
                    <form class="grid gap-3" @submit.prevent="submitAdvisor">
                        <Select v-model="advisor.user_id" :options="(staff ?? []).map((s) => ({ value: s.user_id, label: s.label }))" placeholder="Choose staff" :error="advisor.errors.user_id" />
                        <Button type="submit" variant="secondary" :loading="advisor.processing">Add advisor</Button>
                    </form>
                </Card>

                <Card>
                    <template #title>Ownership</template>
                    <p class="mb-4 text-sm text-ink-muted">
                        <template v-if="club.managed_by === 'committee'">Managed by the committee and advisor since {{ club.transferred_at }}. SLD keeps oversight.</template>
                        <template v-else>SLD is setting this club up. Transfer it once the committee and advisor are in place.</template>
                    </p>
                    <Button v-if="club.managed_by === 'committee'" variant="secondary" block @click="takeBack">Take back</Button>
                    <Button v-else block :disabled="(missingCompulsory?.length ?? 0) > 0 || !advisors?.length" @click="transfer">Transfer to committee</Button>
                </Card>
            </aside>
        </div>
    </PortalLayout>
</template>
