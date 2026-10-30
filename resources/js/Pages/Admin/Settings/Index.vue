<script setup lang="ts">
import { date } from '@/lib/format';
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
        <div class="grid items-start gap-6 lg:grid-cols-2">
            <Card>
                <template #title>Programme short forms</template>
                <p class="mb-4 text-sm text-ink-muted">Short names shown on profiles and forms (for example BCS). The university system has no short form, so set one for each programme code. New codes appear here with the short form blank.</p>
                <form class="grid gap-2" @submit.prevent="save.programmes">
                    <div class="grid grid-cols-[1fr_1fr_2rem] gap-2 px-1 text-xs font-medium uppercase tracking-wide text-ink-muted">
                        <span>Programme code</span><span>Short form</span><span />
                    </div>
                    <div v-for="(item, index) in programmes.items" :key="index" class="grid grid-cols-[1fr_1fr_2rem] items-start gap-2">
                        <Input v-model="item.programme_code" placeholder="CS101" :error="programmes.errors[`items.${index}.programme_code`]" />
                        <Input v-model="item.abbreviation" placeholder="BCS" :error="programmes.errors[`items.${index}.abbreviation`]" />
                        <button type="button" class="grid h-10 place-items-center rounded-control text-ink-muted hover:bg-surface-muted hover:text-danger" :aria-label="`Remove ${item.programme_code}`" @click="programmes.items.splice(index, 1)">✕</button>
                    </div>
                    <div class="mt-2 flex gap-2">
                        <Button variant="secondary" @click="programmes.items.push({ programme_code: '', abbreviation: '' })">Add programme</Button>
                        <Button type="submit" :loading="programmes.processing">Save</Button>
                    </div>
                </form>
            </Card>

            <Card>
                <template #title>Student status</template>
                <p class="mb-4 text-sm text-ink-muted">How each status from the university system is treated. Write one status per line, exactly as the university system spells it.</p>
                <form class="grid gap-4" @submit.prevent="save.statuses">
                    <Textarea v-model="statuses.active" label="Counts as an active student" :rows="4" :error="statuses.errors.active" />
                    <Textarea v-model="statuses.alumni" label="Counts as alumni / no longer studying" :rows="4" :error="statuses.errors.alumni" />
                    <div><Button type="submit" :loading="statuses.processing">Save</Button></div>
                </form>
            </Card>

            <Card>
                <template #title>Intake months</template>
                <p class="mb-4 text-sm text-ink-muted">Real student IDs (QIU-YYYYMM-NNNN) already include the intake month. This table is only used for older IDs without it: intake 1, 2, 3 of the year map to a month.</p>
                <form class="grid gap-2" @submit.prevent="save.intakes">
                    <div class="grid grid-cols-[1fr_1fr_2rem] gap-2 px-1 text-xs font-medium uppercase tracking-wide text-ink-muted">
                        <span>Intake no.</span><span>Month (1–12)</span><span />
                    </div>
                    <div v-for="(item, index) in intakes.items" :key="index" class="grid grid-cols-[1fr_1fr_2rem] items-start gap-2">
                        <Input v-model="item.intake_number" type="number" placeholder="1" />
                        <Input v-model="item.month" type="number" placeholder="3" />
                        <button type="button" class="grid h-10 place-items-center rounded-control text-ink-muted hover:bg-surface-muted hover:text-danger" :aria-label="`Remove intake ${item.intake_number}`" @click="intakes.items.splice(index, 1)">✕</button>
                    </div>
                    <div class="mt-2 flex gap-2">
                        <Button variant="secondary" @click="intakes.items.push({ intake_number: intakes.items.length + 1, month: 1 })">Add intake</Button>
                        <Button type="submit" :loading="intakes.processing">Save</Button>
                    </div>
                </form>
            </Card>

            <Card>
                <template #title>Academic sessions</template>
                <p class="mb-4 text-sm text-ink-muted">A session runs 1 July – 30 June and starts automatically each July. Current session: <strong class="text-ink">{{ currentSession }}</strong>.</p>
                <ul class="mb-4 divide-y divide-line rounded-control border border-line text-sm">
                    <li v-for="s in sessions" :key="s.name" class="flex items-center justify-between gap-3 px-3 py-2">
                        <span class="font-medium">{{ s.name }}<span v-if="s.name === currentSession" class="ml-2 rounded-full bg-success/10 px-2 py-0.5 text-xs text-success">current</span></span>
                        <span class="text-ink-muted">{{ date(s.starts_on) }} – {{ date(s.ends_on) }}</span>
                    </li>
                </ul>
                <form class="grid gap-2" @submit.prevent="save.session">
                    <p class="text-sm font-medium">Add a future session</p>
                    <div class="flex items-start gap-2">
                        <div class="flex-1"><Input v-model="session.name" placeholder="2027/2028" :error="session.errors.name" /></div>
                        <Button type="submit" :loading="session.processing">Add</Button>
                    </div>
                </form>
            </Card>
        </div>
    </PortalLayout>
</template>
