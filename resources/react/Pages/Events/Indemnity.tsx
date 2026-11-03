import FormSection from '@react/Components/forms/FormSection';
import LockedField from '@react/Components/forms/LockedField';
import Button from '@react/Components/ui/Button';
import Checkbox from '@react/Components/ui/Checkbox';
import Input from '@react/Components/ui/Input';
import SignaturePad from '@react/Components/ui/SignaturePad';
import Textarea from '@react/Components/ui/Textarea';
import AppLayout from '@react/Layouts/AppLayout';
import { Head, useForm } from '@inertiajs/react';
import { useState, type FormEvent } from 'react';

const CONSENT = [
    'I am signing this indemnity form so that I can participate in the activity described above. This indemnity statement covers all events, and occurrences associated with the activities, including any associated travel, meals, and loading. I understand that if I have concerns about my health or ability to participate, it is my responsibility to discuss my concerns with a doctor before deciding to participate.',
    'I understand and acknowledge that participating in events/activities organised externally by Quest International University may carry certain risks such as physical injury, personal injury, property damage or illness which may occur through known and unknown causes which is not the fault of QIU staff/coach/event committee or other QIU employee/students who were involved with the activity. I fully understand that the program may be strenuous and choose to participate voluntarily. I accept full responsibility for my own safety and will not act in a manner that will bring harm to me and to others.',
];
interface IndemnityProps {
    event: { slug: string; name: string; venue: string | null; dates: string; organizer: string; staff_in_charge: string | null; closes_at: string; closed: boolean };
    student: { name: string; student_number: string | null; programme: string | null; contact_number: string | null };
    saved: Record<string, string | null> | null;
    signedAt: string | null;
    mustSign: boolean;
}

export default function Indemnity({ event, student, saved, signedAt, mustSign }: IndemnityProps) {
    const form = useForm({
        emergency_name: saved?.emergency_name ?? '',
        emergency_mobile_phone: saved?.emergency_mobile_phone ?? '',
        emergency_work_phone: saved?.emergency_work_phone ?? '',
        emergency_relationship: saved?.emergency_relationship ?? '',
        alternate_name: saved?.alternate_name ?? '',
        alternate_phone: saved?.alternate_phone ?? '',
        address: saved?.address ?? '',
        consent: false,
        signature: null as string | null,
        signature_strokes: null as string | null,
    });
    const { data, setData, errors } = form;
    // Returning signers see their saved details as a summary: check, then just sign. "Edit" opens the fields.
    const complete = !!(saved?.emergency_name && saved.emergency_mobile_phone && saved.emergency_relationship && saved.address);
    const [editing, setEditing] = useState(!complete);
    const hasErrors = Object.keys(errors).length > 0;
    const submit = (e: FormEvent): void => {
        e.preventDefault();
        form.post(`/events/${event.slug}/indemnity`);
    };
    const field = (key: keyof typeof data) => ({ value: data[key] as string, onChange: (v: string) => setData(key, v as never), error: errors[key] });

    return (
        <AppLayout>
            <Head title="Indemnity form" />
            <div className="mx-auto grid max-w-3xl gap-6">
                <header className="grid justify-items-center gap-3 text-center">
                    <img src="/brand/QIU_Wordmark.jpg" alt="Quest International University" className="h-20 w-auto mix-blend-multiply" />
                    <h1 className="w-full bg-ink px-3 py-1.5 text-left font-semibold tracking-wide text-white">STUDENT LIFE DIVISION – INDEMNITY FORM</h1>
                </header>
                {signedAt ? (
                    <p className="rounded-card bg-success/10 px-4 py-3 text-sm text-success">You signed this on {signedAt}.</p>
                ) : event.closed ? (
                    <p className="rounded-card bg-danger/10 px-4 py-3 text-sm text-danger">Signing closed on {event.closes_at} (3 days before the event).</p>
                ) : (
                    !mustSign && <p className="rounded-card bg-warning/10 px-4 py-3 text-sm text-warning">Register for the event first, then sign here.</p>
                )}
                <form className="grid gap-6" onSubmit={submit}>
                    <FormSection title="Activity Details">
                        <div className="grid gap-3 sm:grid-cols-2">
                            <LockedField label="Name of Activity" value={event.name} />
                            <LockedField label="Venue" value={event.venue} />
                            <LockedField label="Date (s) of travel" value={event.dates} />
                            <LockedField label="Organizer" value={event.organizer} />
                            <LockedField label="Staff In charge" value={event.staff_in_charge} />
                        </div>
                    </FormSection>
                    {!editing && !hasErrors ? (
                        <FormSection title="Your details" hint="Filled from your last indemnity form.">
                            <dl className="grid gap-x-4 gap-y-2 text-sm sm:grid-cols-2">
                                <div>
                                    <dt className="text-ink-muted">Emergency contact</dt>
                                    <dd className="font-medium">
                                        {data.emergency_name} ({data.emergency_relationship})
                                    </dd>
                                </div>
                                <div>
                                    <dt className="text-ink-muted">Their phone</dt>
                                    <dd className="font-medium">
                                        {data.emergency_mobile_phone}
                                        {data.emergency_work_phone && ` · ${data.emergency_work_phone}`}
                                    </dd>
                                </div>
                                {data.alternate_name && (
                                    <div>
                                        <dt className="text-ink-muted">If not available</dt>
                                        <dd className="font-medium">
                                            {data.alternate_name} · {data.alternate_phone}
                                        </dd>
                                    </div>
                                )}
                                <div>
                                    <dt className="text-ink-muted">Your address</dt>
                                    <dd className="font-medium">{data.address}</dd>
                                </div>
                            </dl>
                            <button type="button" className="justify-self-start text-sm font-medium text-brand-blue" onClick={() => setEditing(true)}>
                                Something changed? Edit
                            </button>
                        </FormSection>
                    ) : (
                        <div className="fade-up">
                            <FormSection title="Emergency Contact Person" hint={saved ? 'Filled from your last indemnity. Update if anything changed.' : 'Saved for next time, so you only type this once.'}>
                                <div className="grid gap-3 sm:grid-cols-2">
                                    <Input {...field('emergency_name')} label="Name of the emergency contact person" required />
                                    <Input {...field('emergency_relationship')} label="Relationship" required />
                                    <Input {...field('emergency_mobile_phone')} type="tel" label="Home/Mobile Phone Number" required />
                                    <Input {...field('emergency_work_phone')} type="tel" label="Work Phone Number" />
                                </div>
                                <p className="text-sm">If the emergency contact person is not available, please contact:</p>
                                <div className="grid gap-3 sm:grid-cols-2">
                                    <Input {...field('alternate_name')} label="Name" />
                                    <Input {...field('alternate_phone')} type="tel" label="Phone Number" />
                                </div>
                                <Textarea {...field('address')} label="Your address" rows={2} />
                            </FormSection>
                        </div>
                    )}
                    <FormSection title="Participant Consent">
                        {CONSENT.map((p, i) => (
                            <p key={i} className="text-justify text-sm">
                                {p}
                            </p>
                        ))}
                        <div className="grid gap-3 sm:grid-cols-2">
                            <LockedField label="Name" value={student.name} />
                            <LockedField label="Student ID" value={student.student_number} />
                            <LockedField label="Programme" value={student.programme} />
                            <LockedField label="Contact Number" value={student.contact_number} />
                        </div>
                        <LockedField label="Date" value="Server date when you sign" />
                        <Checkbox checked={data.consent} onChange={(v) => setData('consent', v)} label="I have read and agree to the participant consent above." />
                        {errors.consent && <p className="text-xs text-danger">{errors.consent}</p>}
                        <SignaturePad value={data.signature} onChange={(png, strokes) => setData((d) => ({ ...d, signature: png, signature_strokes: strokes }))} label="Signature" signer={student.name} />
                    </FormSection>
                    {!signedAt && !event.closed && mustSign && (
                        <Button type="submit" size="lg" disabled={!data.consent || !data.signature} loading={form.processing}>
                            Sign indemnity
                        </Button>
                    )}
                </form>
            </div>
        </AppLayout>
    );
}
