import FormSection from '@/Components/forms/FormSection';
import Button from '@/Components/ui/Button';
import Input from '@/Components/ui/Input';
import Modal from '@/Components/ui/Modal';
import Select from '@/Components/ui/Select';
import SignaturePad from '@/Components/ui/SignaturePad';
import Textarea from '@/Components/ui/Textarea';
import { useLocalDraft } from '@/hooks/useAutosave';
import AppLayout from '@/Layouts/AppLayout';
import { date, rm } from '@/lib/format';
import { Head, useForm } from '@inertiajs/react';
import { useState } from 'react';

type Person = { id: number; name: string };
type Item = { description: string; unit_sen: number; quantity: number | string; method: 'vendor' | 'self' };
interface EventOption {
    id: number;
    name: string;
    date: string;
    lines: { description: string; unit_sen: number; quantity: number; amount_sen: number }[];
}
interface FundRequestProps {
    club: { slug: string; name: string };
    events: EventOption[];
    selectedEvent: number | null;
    earliestWeekly: string;
    clubTreasurer: Person | null;
    eventTreasurers: Record<number, Person>;
    me: number;
}
// Event: items come from the approved budget (all pre-filled). Club: type the items.
const itemsFor = (scope: 'event' | 'club', event: EventOption | null): Item[] =>
    event ? event.lines.map((l) => ({ description: l.description, unit_sen: l.unit_sen, quantity: l.quantity, method: 'vendor' as const })) : scope === 'club' ? [{ description: '', unit_sen: 0, quantity: 1, method: 'self' }] : [];

/** 07 Fund Withdrawal: for an event (its approved budget) or the club, with bank details checked twice before sending. */
export default function FundRequest({ club, events, selectedEvent, earliestWeekly, clubTreasurer, eventTreasurers, me }: FundRequestProps) {
    const initialScope: 'event' | 'club' = selectedEvent || events.length ? 'event' : 'club';
    const form = useForm({
        scope: initialScope,
        event_id: selectedEvent,
        purpose: '',
        needed_by: '',
        items: itemsFor(initialScope, events.find((e) => e.id === selectedEvent) ?? null),
        bank: { bank_name: '', account_name: '', account_number: '' },
        bank_confirmed: false,
        signature: null as string | null,
        signature_strokes: null as string | null,
    });
    const { data, setData, errors } = form;
    // Prices are typed as text and only turned into sen; the box shows what the person typed.
    const [priceText, setPriceText] = useState<Record<number, string>>({});
    const chooseScope = (scope: 'event' | 'club'): void => {
        setPriceText({});
        setData((d) => ({ ...d, scope, items: itemsFor(scope, scope === 'event' ? (events.find((e) => e.id === d.event_id) ?? null) : null) }));
    };
    const chooseEvent = (id: number): void => {
        setPriceText({});
        setData((d) => ({ ...d, event_id: id, items: itemsFor('event', events.find((e) => e.id === id) ?? null) }));
    };
    // The unfinished request stays on this device (bank details excluded: they are always typed and confirmed afresh).
    const draft = useLocalDraft(`fund-request:${club.slug}:${selectedEvent ?? 'any'}`, { scope: data.scope, event_id: data.event_id, purpose: data.purpose, needed_by: data.needed_by, items: data.items }, (saved) => {
        // An event that is no longer open for withdrawals is not brought back.
        if (saved.scope === 'event' && !events.some((e) => e.id === saved.event_id)) return;
        setData((d) => ({
            ...d,
            scope: saved.scope ?? d.scope,
            event_id: saved.event_id ?? d.event_id,
            purpose: saved.purpose ?? '',
            needed_by: saved.needed_by ?? '',
            items: saved.items?.length ? saved.items : d.items,
        }));
    });
    const setItem = (index: number, patch: Partial<Item>): void => setData('items', data.items.map((item, i) => (i === index ? { ...item, ...patch } : item)));
    const setUnit = (index: number, value: string): void => {
        const clean = value.replace(/[^\d.]/g, '');
        setPriceText({ ...priceText, [index]: clean });
        setItem(index, { unit_sen: Math.round(Number(clean || 0) * 100) });
    };
    const total = data.items.reduce((s, i) => s + i.unit_sen * Number(i.quantity || 0), 0);
    const treasurer: Person | null = data.scope === 'event' ? (data.event_id ? (eventTreasurers[data.event_id] ?? null) : null) : clubTreasurer;
    const iAmTreasurer = treasurer?.id === me;
    const ready =
        total > 0 &&
        data.bank.bank_name.trim() !== '' &&
        data.bank.account_name.trim() !== '' &&
        /^\d[\d\s-]{5,28}\d$/.test(data.bank.account_number.trim()) &&
        (data.scope === 'event' ? !!data.event_id : data.purpose.trim() !== '' && !!data.needed_by);
    // Bank details are checked twice: once on the form, then on a confirmation screen before anything is sent.
    const [confirming, setConfirming] = useState(false);
    const send = (): void => {
        form.transform((d) => ({ ...d, bank_confirmed: true }));
        form.post(`/clubs/${club.slug}/fund-requests`, { onSuccess: () => draft.clear(), onFinish: () => setConfirming(false) });
    };
    const setBank = (key: keyof typeof data.bank) => (v: string): void => setData('bank', { ...data.bank, [key]: v });
    const err = errors as Record<string, string>;
    const errorList = Object.values(errors);
    const SCOPES = [
        { k: 'event' as const, l: 'An event', d: 'Filed under the event; items from its approved budget' },
        { k: 'club' as const, l: 'The club', d: 'Weekly activities and other club costs; state the purpose' },
    ];

    return (
        <AppLayout>
            <Head title="Fund withdrawal" />
            <div className="grid gap-6">
                <header>
                    <h1 className="text-xl font-bold">Fund Withdrawal / Transfer</h1>
                    {draft.restored && <p className="text-xs text-ink-muted">Your unfinished request was restored from this device. Bank details are typed afresh.</p>}
                    <p className="text-sm text-ink-muted">07 template · {club.name} · Treasurer signs → Advisor → SLD → Finance signs → bank-in → Treasurer uploads proof → SLD confirms. You are told at every step.</p>
                </header>
                <FormSection title="What is it for?" required>
                    <div className="grid grid-cols-2 gap-2" role="radiogroup" aria-label="Withdrawal for">
                        {SCOPES.map((o) => (
                            <button
                                key={o.k}
                                type="button"
                                role="radio"
                                aria-checked={data.scope === o.k}
                                className={`grid gap-1 rounded-card border p-3 text-left text-sm transition ${data.scope === o.k ? 'border-primary bg-primary/10 ring-2 ring-primary/30' : 'border-line hover:bg-surface-muted'}`}
                                onClick={() => chooseScope(o.k)}
                            >
                                <strong>{o.l}</strong>
                                <span className="text-xs text-ink-muted">{o.d}</span>
                            </button>
                        ))}
                    </div>
                    {data.scope === 'event' ? (
                        <>
                            <Select
                                value={data.event_id}
                                onChange={(v) => chooseEvent(Number(v))}
                                label="Event"
                                required
                                placeholder="Choose an approved event"
                                options={events.map((e) => ({ value: e.id, label: `${e.name} · ${e.date}` }))}
                                error={errors.event_id}
                            />
                            {!events.length && <p className="text-sm text-warning">No approved events yet. A withdrawal for an event opens once its proposal is approved.</p>}
                        </>
                    ) : (
                        <>
                            <Textarea value={data.purpose} onChange={(v) => setData('purpose', v)} label="Purpose" required rows={2} placeholder="e.g. Weekly training balls and cones, November" error={errors.purpose} />
                            <Input value={data.needed_by} onChange={(v) => setData('needed_by', v)} type="date" label="Money needed by" required hint={`At least 2 weeks ahead: ${date(earliestWeekly)} or later`} error={errors.needed_by} />
                        </>
                    )}
                </FormSection>
                <FormSection title="Items" required hint={data.scope === 'event' ? 'From the approved budget. Lower the amount if you need less.' : undefined}>
                    {data.items.map((item, i) => (
                        <div key={i} className="grid grid-cols-[1fr_7rem_5rem_auto] items-end gap-2">
                            <Input value={item.description} onChange={(v) => setItem(i, { description: v })} label={i === 0 ? 'Item' : undefined} readOnly={data.scope === 'event'} />
                            <Input value={priceText[i] ?? (item.unit_sen ? (item.unit_sen / 100).toFixed(2) : '')} onChange={(v) => setUnit(i, v)} label={i === 0 ? 'Price (RM)' : undefined} />
                            <Input value={item.quantity} onChange={(v) => setItem(i, { quantity: v })} type="number" label={i === 0 ? 'Qty' : undefined} />
                            {data.scope === 'club' && data.items.length > 1 ? (
                                <button type="button" className="h-10 px-1 text-ink-muted hover:text-danger" aria-label="Remove item" onClick={() => setData('items', data.items.filter((_, x) => x !== i))}>
                                    ✕
                                </button>
                            ) : (
                                <span />
                            )}
                        </div>
                    ))}
                    {data.scope === 'club' && (
                        <button type="button" className="justify-self-start text-sm font-medium text-primary" onClick={() => setData('items', [...data.items, { description: '', unit_sen: 0, quantity: 1, method: 'self' }])}>
                            ＋ Add item
                        </button>
                    )}
                    <p className="flex justify-between border-t border-line pt-2 font-semibold">
                        <span>Total</span>
                        <span className="tabular-nums">{rm(total)}</span>
                    </p>
                    {errors.items && <p className="text-xs text-danger">{errors.items}</p>}
                </FormSection>
                <FormSection title="Bank account the money goes to" required hint="Finance banks the money into this account. Check every digit.">
                    <div className="grid gap-3 sm:grid-cols-3">
                        <Input value={data.bank.bank_name} onChange={setBank('bank_name')} label="Bank" required placeholder="e.g. Maybank" error={err['bank.bank_name']} />
                        <Input value={data.bank.account_name} onChange={setBank('account_name')} label="Account name" required placeholder="As printed by the bank" error={err['bank.account_name']} />
                        <Input value={data.bank.account_number} onChange={setBank('account_number')} label="Account number" required placeholder="Digits only" error={err['bank.account_number']} />
                    </div>
                </FormSection>
                <FormSection title="Treasurer's signature">
                    {!treasurer ? (
                        <p className="text-sm text-warning">{data.scope === 'event' ? 'This event has no Treasurer on its committee yet. Add one in the proposal first.' : 'The club has no Treasurer yet.'}</p>
                    ) : iAmTreasurer ? (
                        <p className="text-sm">You are the {data.scope === 'event' ? 'event' : 'club'} Treasurer: sign below and it goes straight to your Advisor.</p>
                    ) : (
                        <p className="text-sm">
                            Only the {data.scope === 'event' ? 'event' : 'club'} Treasurer, <strong>{treasurer.name}</strong>, can sign. When you send it, they are asked to check and sign.
                        </p>
                    )}
                    {iAmTreasurer && <SignaturePad value={data.signature} onChange={(png, strokes) => setData((d) => ({ ...d, signature: png, signature_strokes: strokes }))} label="Your signature" />}
                </FormSection>
                <div className="flex justify-end">
                    <Button size="lg" disabled={!ready || !treasurer || (iAmTreasurer && !data.signature)} onClick={() => setConfirming(true)}>
                        {iAmTreasurer ? 'Check bank details & sign' : `Check bank details & send to ${treasurer?.name ?? 'the Treasurer'}`}
                    </Button>
                </div>
            </div>
            <Modal open={confirming} onClose={() => setConfirming(false)} title="Are these bank details correct?">
                <div className="grid gap-4 text-sm">
                    <p className="text-ink-muted">Money sent to an incorrect account is very difficult to recover. Please review each line again.</p>
                    <dl className="grid gap-2 rounded-card border-2 border-warning/50 bg-warning/5 p-4">
                        <div className="flex justify-between gap-3">
                            <dt className="text-ink-muted">Bank</dt>
                            <dd className="text-right font-semibold">{data.bank.bank_name}</dd>
                        </div>
                        <div className="flex justify-between gap-3">
                            <dt className="text-ink-muted">Account name</dt>
                            <dd className="text-right font-semibold">{data.bank.account_name}</dd>
                        </div>
                        <div className="flex justify-between gap-3">
                            <dt className="text-ink-muted">Account number</dt>
                            <dd className="text-right font-mono text-base font-bold tracking-wider">{data.bank.account_number}</dd>
                        </div>
                        <div className="flex justify-between gap-3 border-t border-line pt-2">
                            <dt className="text-ink-muted">Amount</dt>
                            <dd className="text-right font-semibold">{rm(total)}</dd>
                        </div>
                    </dl>
                    {errorList.length > 0 && <p className="text-danger">{errorList[0]}</p>}
                    <div className="grid grid-cols-2 gap-2">
                        <Button variant="secondary" onClick={() => setConfirming(false)}>
                            No, let me fix it
                        </Button>
                        <Button loading={form.processing} onClick={send}>
                            Yes, correct · {iAmTreasurer ? 'sign' : 'send'}
                        </Button>
                    </div>
                </div>
            </Modal>
        </AppLayout>
    );
}
