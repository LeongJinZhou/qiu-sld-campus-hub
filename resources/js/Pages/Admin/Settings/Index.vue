<script setup lang="ts">
import Button from '@/Components/ui/Button.vue';
import Card from '@/Components/ui/Card.vue';
import Input from '@/Components/ui/Input.vue';
import Textarea from '@/Components/ui/Textarea.vue';
import PortalLayout from '@/Layouts/PortalLayout.vue';
import { Head, useForm } from '@inertiajs/vue3';

const props = defineProps<{
    programmes: { programme_code: string; abbreviation: string }[];
    unmappedCodes: string[];
    intakes: { intake_number: number; month: number }[];
    statuses: { active: string[]; alumni: string[] };
    sessions: { name: string; starts_on: string; ends_on: string }[];
    currentSession: string;
}>();

const programmes = useForm({
    items: [...props.programmes, ...props.unmappedCodes.map((code) => ({ programme_code: code, abbreviation: '' }))],
});
const intakes = useForm({ items: props.intakes.map((i) => ({ ...i })) });
const statuses = useForm({ active: props.statuses.active.join('\n'), alumni: props.statuses.alumni.join('\n') });
const session = useForm({ name: '' });

const lines = (text: string) => text.split('\n').map((s) => s.trim()).filter(Boolean);
const options = { preserveScroll: true };
const save = {
    programmes: () => programmes.put('/admin/settings/programmes', options),
    intakes: () => intakes.put('/admin/settings/intakes', options),
    statuses: () => statuses.transform((d) => ({ active: lines(d.active), alumni: lines(d.alumni) })).put('/admin/settings/statuses', options),
    session: () => session.put('/admin/settings/sessions', { ...options, onSuccess: () => session.reset() }),
};
</script>

<template>
    <Head title="Settings" />
    <PortalLayout title="Settings">
        <div class="grid gap-6 lg:grid-cols-2">
            <Card>
                <template #title>Programme short forms</template>
                <p class="mb-3 text-sm text-ink-muted">camsys has no course abbreviation. Codes without one are listed blank.</p>
                <form class="grid gap-2" @submit.prevent="save.programmes">
                    <div v-for="(item, index) in programmes.items" :key="index" class="grid grid-cols-[1fr_1fr_auto] items-center gap-2">
                        <Input v-model="item.programme_code" placeholder="Code" :error="programmes.errors[`items.${index}.programme_code`]" />
                        <Input v-model="item.abbreviation" placeholder="e.g. BCS" :error="programmes.errors[`items.${index}.abbreviation`]" />
                        <button type="button" class="text-ink-muted hover:text-danger" aria-label="Remove" @click="programmes.items.splice(index, 1)">✕</button>
                    </div>
                    <div class="flex gap-2">
                        <Button variant="secondary" size="sm" @click="programmes.items.push({ programme_code: '', abbreviation: '' })">Add</Button>
                        <Button type="submit" size="sm" :loading="programmes.processing">Save</Button>
                    </div>
                </form>
            </Card>

            <Card>
                <template #title>Student status mapping</template>
                <p class="mb-3 text-sm text-ink-muted">camsys PROGRAMME_STATUS values, one per line.</p>
                <form class="grid gap-3" @submit.prevent="save.statuses">
                    <Textarea v-model="statuses.active" label="Active" :rows="4" :error="statuses.errors.active" />
                    <Textarea v-model="statuses.alumni" label="Alumni / inactive" :rows="4" :error="statuses.errors.alumni" />
                    <Button type="submit" size="sm" :loading="statuses.processing" class="justify-self-start">Save</Button>
                </form>
            </Card>

            <Card>
                <template #title>Intake map (dummy IDs only)</template>
                <p class="mb-3 text-sm text-ink-muted">Real QIU-YYYYMM-NNNN IDs carry the month. This maps INTAKE_NUMBER to a month for IDs that don't.</p>
                <form class="grid gap-2" @submit.prevent="save.intakes">
                    <div v-for="(item, index) in intakes.items" :key="index" class="grid grid-cols-[1fr_1fr_auto] items-center gap-2">
                        <Input v-model="item.intake_number" type="number" placeholder="Intake no." />
                        <Input v-model="item.month" type="number" placeholder="Month (1–12)" />
                        <button type="button" class="text-ink-muted hover:text-danger" aria-label="Remove" @click="intakes.items.splice(index, 1)">✕</button>
                    </div>
                    <div class="flex gap-2">
                        <Button variant="secondary" size="sm" @click="intakes.items.push({ intake_number: intakes.items.length + 1, month: 1 })">Add</Button>
                        <Button type="submit" size="sm" :loading="intakes.processing">Save</Button>
                    </div>
                </form>
            </Card>

            <Card>
                <template #title>Academic sessions</template>
                <p class="mb-3 text-sm text-ink-muted">1 July – 30 June. Current: <strong>{{ currentSession }}</strong> (rolls over automatically).</p>
                <ul class="mb-4 grid gap-1 text-sm">
                    <li v-for="s in sessions" :key="s.name" class="flex justify-between">
                        <span>{{ s.name }}</span><span class="text-ink-muted">{{ s.starts_on }} → {{ s.ends_on }}</span>
                    </li>
                </ul>
                <form class="flex items-end gap-2" @submit.prevent="save.session">
                    <Input v-model="session.name" label="Add session" placeholder="2027/2028" :error="session.errors.name" />
                    <Button type="submit" size="sm" :loading="session.processing">Add</Button>
                </form>
            </Card>
        </div>
    </PortalLayout>
</template>
