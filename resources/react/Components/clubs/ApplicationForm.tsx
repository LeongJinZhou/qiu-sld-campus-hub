import FormSection from '@react/Components/forms/FormSection';
import Button from '@react/Components/ui/Button';
import Input from '@react/Components/ui/Input';
import Modal from '@react/Components/ui/Modal';
import PersonPicker from '@react/Components/ui/PersonPicker';
import Select from '@react/Components/ui/Select';
import SignaturePad from '@react/Components/ui/SignaturePad';
import Textarea from '@react/Components/ui/Textarea';
import { useLocalDraft } from '@react/hooks/useAutosave';
import { useForm } from '@inertiajs/react';
import { useState } from 'react';

interface ApplicationFormProps {
    action: string;
    categories: { id: number; name: string }[];
    advisors: { id: number; name: string }[];
    positions: string[];
    me?: { student_number: string; name: string } | null;
    initial?: { name: string; category_id: number | null; bio: string; objectives: string; officers: Record<string, string> } | null;
    advisorId?: number | null;
}

/** The new-club application: name, category, aims, the four officers (applicant is President), advisor, signature. */
export default function ApplicationForm({ action, categories, advisors, positions, me, initial, advisorId }: ApplicationFormProps) {
    const form = useForm({
        name: initial?.name ?? '',
        category_id: initial?.category_id ?? (null as number | null),
        bio: initial?.bio ?? '',
        objectives: initial?.objectives ?? '',
        officers: Object.fromEntries(positions.map((p) => [p, initial?.officers[p] ?? (p === 'President' ? (me?.student_number ?? '') : '')])) as Record<string, string>,
        advisor_id: advisorId ?? (null as number | null),
        signature: null as string | null,
        signature_strokes: null as string | null,
    });
    const { data, setData, errors } = form;
    // A new application keeps its unfinished draft on this device; a returned one starts from what was sent.
    const draft = useLocalDraft(
        'club-application',
        { name: data.name, category_id: data.category_id, bio: data.bio, objectives: data.objectives, officers: data.officers, advisor_id: data.advisor_id },
        (saved) => setData((d) => ({ ...d, ...saved, officers: { ...d.officers, ...saved.officers, President: d.officers.President ?? '' } })),
        !initial,
    );
    const [signing, setSigning] = useState(false);
    const submit = (): void => form.post(action, { preserveScroll: true, onSuccess: () => draft.clear(), onFinish: () => setSigning(false) });
    const errorList = Object.values(errors);

    return (
        <div className="grid gap-5">
            {draft.restored && <p className="text-xs text-ink-muted">Your unfinished application was restored. It is kept on this device until you send it.</p>}
            <FormSection title="The club" required>
                <Input value={data.name} onChange={(v) => setData('name', v)} label="Club name" required error={errors.name} />
                <Select value={data.category_id} onChange={(v) => setData('category_id', v)} label="Category" options={categories.map((c) => ({ value: c.id, label: c.name }))} placeholder="Choose a category" />
                <Textarea value={data.bio} onChange={(v) => setData('bio', v)} label="Short description (shown on the club page)" required rows={2} error={errors.bio} />
                <Textarea value={data.objectives} onChange={(v) => setData('objectives', v)} label="Aims and planned activities" required rows={4} error={errors.objectives} />
            </FormSection>
            <FormSection title="Founding committee" required hint="You are the proposed President. Pick the other three officers by name or student ID.">
                {positions.map((p) => (
                    <div key={p} className="grid gap-1">
                        <p className="text-sm font-medium">
                            {p}
                            <span className="text-danger"> *</span>
                        </p>
                        {p === 'President' && me ? (
                            <p className="text-sm text-ink-muted">
                                {me.name} · {me.student_number} (you)
                            </p>
                        ) : (
                            <PersonPicker
                                value={data.officers[p] ?? ''}
                                onChange={(v) => setData('officers', { ...data.officers, [p]: v })}
                                label="Name or student ID"
                                error={(errors as Record<string, string>)[`officers.${p}`]}
                            />
                        )}
                    </div>
                ))}
            </FormSection>
            <FormSection title="Proposed advisor" required hint="A QIU staff member who agrees to advise the club; they are asked to sign next.">
                <Select
                    value={data.advisor_id}
                    onChange={(v) => setData('advisor_id', v)}
                    label="Advisor"
                    required
                    options={advisors.map((a) => ({ value: a.id, label: a.name }))}
                    placeholder="Choose a staff member"
                    error={errors.advisor_id}
                />
            </FormSection>
            <div className="flex justify-end">
                <Button size="lg" disabled={!data.name || !data.bio || !data.objectives || !data.advisor_id || positions.some((p) => !data.officers[p])} onClick={() => setSigning(true)}>
                    Sign &amp; send to the advisor
                </Button>
            </div>
            <Modal open={signing} onClose={() => setSigning(false)} title="Sign as the proposed President">
                <div className="grid gap-4">
                    <p className="text-sm text-ink-muted">After you sign, the advisor signs, then SLD reviews. The club opens only when SLD approves.</p>
                    <SignaturePad value={data.signature} onChange={(png, strokes) => setData((d) => ({ ...d, signature: png, signature_strokes: strokes }))} label="Your signature" />
                    {errorList.length > 0 && <p className="text-sm text-danger">{errorList[0]}</p>}
                    <Button disabled={!data.signature} loading={form.processing} onClick={submit}>
                        Submit application
                    </Button>
                </div>
            </Modal>
        </div>
    );
}
