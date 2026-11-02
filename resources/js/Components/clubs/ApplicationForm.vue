<script setup lang="ts">
import FormSection from '@/Components/forms/FormSection.vue';
import Button from '@/Components/ui/Button.vue';
import Input from '@/Components/ui/Input.vue';
import Modal from '@/Components/ui/Modal.vue';
import PersonPicker from '@/Components/ui/PersonPicker.vue';
import Select from '@/Components/ui/Select.vue';
import SignaturePad from '@/Components/ui/SignaturePad.vue';
import Textarea from '@/Components/ui/Textarea.vue';
import { useLocalDraft } from '@/composables/useAutosave';
import { useForm } from '@inertiajs/vue3';
import { ref } from 'vue';

/** The new-club application: name, category, aims, the four officers (applicant is President), advisor, signature. */
const props = defineProps<{
    action: string;
    categories: { id: number; name: string }[];
    advisors: { id: number; name: string }[];
    positions: string[];
    me?: { student_number: string; name: string } | null;
    initial?: { name: string; category_id: number | null; bio: string; objectives: string; officers: Record<string, string> } | null;
    advisorId?: number | null;
}>();

const form = useForm({
    name: props.initial?.name ?? '',
    category_id: props.initial?.category_id ?? (null as number | null),
    bio: props.initial?.bio ?? '',
    objectives: props.initial?.objectives ?? '',
    officers: Object.fromEntries(props.positions.map((p) => [p, props.initial?.officers[p] ?? (p === 'President' ? (props.me?.student_number ?? '') : '')])) as Record<
        string,
        string
    >,
    advisor_id: props.advisorId ?? (null as number | null),
    signature: null as string | null,
    signature_strokes: null as string | null,
});
// A new application keeps its unfinished draft on this device; a returned one starts from what was sent.
const draft = props.initial
    ? null
    : useLocalDraft(
          'club-application',
          () => ({ name: form.name, category_id: form.category_id, bio: form.bio, objectives: form.objectives, officers: form.officers, advisor_id: form.advisor_id }),
          (saved) => Object.assign(form, saved, { officers: { ...form.officers, ...saved.officers, President: form.officers.President } }),
      );
const signing = ref(false);
const submit = (): void => form.post(props.action, { preserveScroll: true, onSuccess: () => draft?.clear(), onFinish: () => (signing.value = false) });
</script>

<template>
    <div class="grid gap-5">
        <p v-if="draft?.restored" class="text-xs text-ink-muted">Your unfinished application was restored. It is kept on this device until you send it.</p>
        <FormSection title="The club" required>
            <Input v-model="form.name" label="Club name" required :error="form.errors.name" />
            <Select v-model="form.category_id" label="Category" :options="categories.map((c) => ({ value: c.id, label: c.name }))" placeholder="Choose a category" />
            <Textarea v-model="form.bio" label="Short description (shown on the club page)" required :rows="2" :error="form.errors.bio" />
            <Textarea v-model="form.objectives" label="Aims and planned activities" required :rows="4" :error="form.errors.objectives" />
        </FormSection>
        <FormSection title="Founding committee" required hint="You are the proposed President. Pick the other three officers by name or student ID.">
            <div v-for="p in positions" :key="p" class="grid gap-1">
                <p class="text-sm font-medium">{{ p }}<span class="text-danger"> *</span></p>
                <p v-if="p === 'President' && me" class="text-sm text-ink-muted">{{ me.name }} · {{ me.student_number }} (you)</p>
                <PersonPicker v-else v-model="form.officers[p]!" label="Name or student ID" :error="form.errors[`officers.${p}`]" />
            </div>
        </FormSection>
        <FormSection title="Proposed advisor" required hint="A QIU staff member who agrees to advise the club; they are asked to sign next.">
            <Select
                v-model="form.advisor_id"
                label="Advisor"
                required
                :options="advisors.map((a) => ({ value: a.id, label: a.name }))"
                placeholder="Choose a staff member"
                :error="form.errors.advisor_id"
            />
        </FormSection>
        <div class="flex justify-end">
            <Button size="lg" :disabled="!form.name || !form.bio || !form.objectives || !form.advisor_id || positions.some((p) => !form.officers[p])" @click="signing = true"
                >Sign &amp; send to the advisor</Button
            >
        </div>
        <Modal v-model:open="signing" title="Sign as the proposed President">
            <div class="grid gap-4">
                <p class="text-sm text-ink-muted">After you sign, the advisor signs, then SLD reviews. The club opens only when SLD approves.</p>
                <SignaturePad v-model="form.signature" v-model:strokes="form.signature_strokes" label="Your signature" />
                <p v-if="Object.keys(form.errors).length" class="text-sm text-danger">{{ Object.values(form.errors)[0] }}</p>
                <Button :disabled="!form.signature" :loading="form.processing" @click="submit">Submit application</Button>
            </div>
        </Modal>
    </div>
</template>
