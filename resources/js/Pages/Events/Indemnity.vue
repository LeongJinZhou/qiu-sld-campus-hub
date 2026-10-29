<script setup lang="ts">
import FormSection from '@/Components/forms/FormSection.vue';
import LockedField from '@/Components/forms/LockedField.vue';
import Button from '@/Components/ui/Button.vue';
import Checkbox from '@/Components/ui/Checkbox.vue';
import Input from '@/Components/ui/Input.vue';
import SignaturePad from '@/Components/ui/SignaturePad.vue';
import Textarea from '@/Components/ui/Textarea.vue';
import AppLayout from '@/Layouts/AppLayout.vue';
import { Head, useForm } from '@inertiajs/vue3';

const CONSENT = [
    'I am signing this indemnity form so that I can participate in the activity described above. This indemnity statement covers all events, and occurrences associated with the activities, including any associated travel, meals, and loading. I understand that if I have concerns about my health or ability to participate, it is my responsibility to discuss my concerns with a doctor before deciding to participate.',
    'I understand and acknowledge that participating in events/activities organised externally by Quest International University may carry certain risks such as physical injury, personal injury, property damage or illness which may occur through known and unknown causes which is not the fault of QIU staff/coach/event committee or other QIU employee/students who were involved with the activity. I fully understand that the program may be strenuous and choose to participate voluntarily. I accept full responsibility for my own safety and will not act in a manner that will bring harm to me and to others.',
];

const props = defineProps<{
    event: { slug: string; name: string; venue: string | null; dates: string; organizer: string; staff_in_charge: string | null; closes_at: string; closed: boolean };
    student: { name: string; student_number: string | null; programme: string | null; contact_number: string | null };
    saved: Record<string, string | null> | null;
    signedAt: string | null;
    mustSign: boolean;
}>();

const form = useForm({
    emergency_name: props.saved?.emergency_name ?? '',
    emergency_mobile_phone: props.saved?.emergency_mobile_phone ?? '',
    emergency_work_phone: props.saved?.emergency_work_phone ?? '',
    emergency_relationship: props.saved?.emergency_relationship ?? '',
    alternate_name: props.saved?.alternate_name ?? '',
    alternate_phone: props.saved?.alternate_phone ?? '',
    address: '',
    consent: false,
    signature: null as string | null,
    signature_strokes: null as string | null,
});
</script>

<template>
    <Head title="Indemnity form" />
    <AppLayout>
        <div class="mx-auto grid max-w-3xl gap-6">
            <header class="grid justify-items-center gap-3 text-center">
                <img src="/brand/QIU_Wordmark.jpg" alt="Quest International University" class="h-20 w-auto mix-blend-multiply" />
                <h1 class="w-full bg-ink px-3 py-1.5 text-left font-semibold tracking-wide text-white">STUDENT LIFE DIVISION – INDEMNITY FORM</h1>
            </header>

            <p v-if="signedAt" class="rounded-card bg-success/10 px-4 py-3 text-sm text-success">You signed this on {{ signedAt }}.</p>
            <p v-else-if="event.closed" class="rounded-card bg-danger/10 px-4 py-3 text-sm text-danger">Signing closed on {{ event.closes_at }} (3 days before the event).</p>
            <p v-else-if="!mustSign" class="rounded-card bg-warning/10 px-4 py-3 text-sm text-warning">Register for the event first, then sign here.</p>

            <form class="grid gap-6" @submit.prevent="form.post(`/events/${event.slug}/indemnity`)">
                <FormSection title="Activity Details">
                    <div class="grid gap-3 sm:grid-cols-2">
                        <LockedField label="Name of Activity" :value="event.name" />
                        <LockedField label="Venue" :value="event.venue" />
                        <LockedField label="Date (s) of travel" :value="event.dates" />
                        <LockedField label="Organizer" :value="event.organizer" />
                        <LockedField label="Staff In charge" :value="event.staff_in_charge" />
                    </div>
                </FormSection>

                <FormSection title="Emergency Contact Person" :hint="saved ? 'Filled from your last indemnity. Update if anything changed.' : undefined">
                    <div class="grid gap-3 sm:grid-cols-2">
                        <Input v-model="form.emergency_name" label="Name of the emergency contact person" required :error="form.errors.emergency_name" />
                        <Input v-model="form.emergency_relationship" label="Relationship" required :error="form.errors.emergency_relationship" />
                        <Input v-model="form.emergency_mobile_phone" type="tel" label="Home/Mobile Phone Number" required :error="form.errors.emergency_mobile_phone" />
                        <Input v-model="form.emergency_work_phone" type="tel" label="Work Phone Number" :error="form.errors.emergency_work_phone" />
                    </div>
                    <p class="text-sm">If the emergency contact person is not available, please contact:</p>
                    <div class="grid gap-3 sm:grid-cols-2">
                        <Input v-model="form.alternate_name" label="Name" :error="form.errors.alternate_name" />
                        <Input v-model="form.alternate_phone" type="tel" label="Phone Number" :error="form.errors.alternate_phone" />
                    </div>
                </FormSection>

                <FormSection title="Participant Consent">
                    <p v-for="(p, i) in CONSENT" :key="i" class="text-justify text-sm">{{ p }}</p>
                    <div class="grid gap-3 sm:grid-cols-2">
                        <LockedField label="Name" :value="student.name" />
                        <LockedField label="Student ID" :value="student.student_number" />
                        <LockedField label="Programme" :value="student.programme" />
                        <LockedField label="Contact Number" :value="student.contact_number" />
                    </div>
                    <Textarea v-model="form.address" label="Address" :rows="2" :error="form.errors.address" />
                    <LockedField label="Date" value="Server date when you sign" />
                    <Checkbox v-model="form.consent" label="I have read and agree to the participant consent above." />
                    <p v-if="form.errors.consent" class="text-xs text-danger">{{ form.errors.consent }}</p>
                    <SignaturePad v-model="form.signature" v-model:strokes="form.signature_strokes" label="Signature" :signer="student.name" />
                </FormSection>

                <Button v-if="!signedAt && !event.closed && mustSign" type="submit" size="lg" :disabled="!form.consent || !form.signature" :loading="form.processing">Sign indemnity</Button>
            </form>
        </div>
    </AppLayout>
</template>
