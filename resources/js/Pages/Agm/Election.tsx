import Avatar from '@/Components/ui/Avatar';
import Badge from '@/Components/ui/Badge';
import Button from '@/Components/ui/Button';
import Card from '@/Components/ui/Card';
import Checkbox from '@/Components/ui/Checkbox';
import EmptyState from '@/Components/ui/EmptyState';
import FileUpload from '@/Components/ui/FileUpload';
import Input from '@/Components/ui/Input';
import Modal from '@/Components/ui/Modal';
import PersonPicker from '@/Components/ui/PersonPicker';
import Textarea from '@/Components/ui/Textarea';
import AppLayout from '@/Layouts/AppLayout';
import { dateTime } from '@/lib/format';
import { Head, Link, router, useForm } from '@inertiajs/react';
import { useState, type FormEvent, type KeyboardEvent } from 'react';

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
type Phase = 'upcoming' | 'nominating' | 'between' | 'voting' | 'closed' | 'published';
interface ElectionProps {
    agm: { id: number; club: { slug: string; name: string }; starts_at: string };
    manages: boolean;
    election: {
        id: number;
        phase: Phase;
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
}
const PHASE_LABEL: Record<Phase, string> = {
    upcoming: 'Nominations open soon',
    nominating: 'Nominations open',
    between: 'Voting opens soon',
    voting: 'Voting open',
    closed: 'Voting closed',
    published: 'Results published',
};
const FLAG_TEXT = { no_candidate: 'compulsory position without a candidate', uncontested: 'single candidate (flagged for SLD)', tie: 'tied — hold a run-off' };
const onEnter = (fn: () => void) => (e: KeyboardEvent<HTMLDivElement>): void => {
    if (e.key === 'Enter') {
        e.preventDefault();
        fn();
    }
};

/** The committee election: setup, nominations, the ballot (one vote per position, changeable a few times) and results. */
export default function Election({ agm, manages, election, highlight }: ElectionProps) {
    // Committee: add or remove positions before voting, and name candidates by name or student ID.
    const [newPosition, setNewPosition] = useState('');
    const addPosition = (): void => {
        if (election && newPosition.trim()) router.post(`/elections/${election.id}/positions`, { position: newPosition.trim() }, { preserveScroll: true, onSuccess: () => setNewPosition('') });
    };
    const removePosition = (p: Position): void => {
        if (confirm(`Remove ${p.position} from the ballot?`)) router.delete(`/election-positions/${p.id}`, { preserveScroll: true });
    };
    const [naming, setNaming] = useState<Position | null>(null);
    const pick = useForm({ student_number: '', statement: '' });
    const [typed, setTyped] = useState(false);
    const nameCandidate = (e: FormEvent): void => {
        e.preventDefault();
        if (!naming) return;
        pick.post(`/election-positions/${naming.id}/nominate-member`, {
            preserveScroll: true,
            onSuccess: () => {
                setNaming(null);
                pick.reset();
                setTyped(false);
            },
        });
    };
    const [sharing, setSharing] = useState<CandidateCard | null>(null);
    const [copied, setCopied] = useState(false);
    const editable = ['upcoming', 'nominating', 'between'].includes(election?.phase ?? '');

    const setup = useForm({ nominations_open_at: '', nominations_close_at: '', voting_open_at: '', voting_close_at: '', allow_alumni_vote: false, extra_positions: [] as string[] });
    const [extra, setExtra] = useState('');
    const addExtra = (): void => {
        if (extra.trim()) setup.setData('extra_positions', [...setup.data.extra_positions, extra.trim()]);
        setExtra('');
    };
    const [standing, setStanding] = useState<Position | null>(null);
    const nomination = useForm({ statement: '', photo: [] as File[] });
    const nominate = (e: FormEvent): void => {
        e.preventDefault();
        if (!standing) return;
        nomination.transform((d) => ({ statement: d.statement, photo: d.photo[0] ?? null }));
        nomination.post(`/election-positions/${standing.id}/nominate`, {
            preserveScroll: true,
            forceFormData: true,
            onSuccess: () => {
                setStanding(null);
                nomination.reset();
            },
        });
    };
    const vote = (p: Position, c: CandidateCard): void => {
        if (!election) return;
        const msg =
            p.voted_for !== null
                ? `Change your ${p.position} vote to ${c.name}? You can change it ${p.changes_left} more time${p.changes_left === 1 ? '' : 's'}${p.changes_left === 1 ? ' (this is the last)' : ''}.`
                : `Vote for ${c.name} as ${p.position}? You can change your mind up to ${election.vote_changes} times before voting closes.`;
        if (confirm(msg)) router.post(`/election-positions/${p.id}/vote`, { candidate_id: c.id }, { preserveScroll: true });
    };
    const setupField = (key: 'nominations_open_at' | 'nominations_close_at' | 'voting_open_at' | 'voting_close_at', label: string) => (
        <Input value={setup.data[key]} onChange={(v) => setup.setData(key, v)} type="datetime-local" label={label} error={setup.errors[key]} />
    );

    return (
        <AppLayout>
            <Head title={`Election · ${agm.club.name}`} />
            <div className="grid gap-6">
                <header>
                    <Link href={manages ? `/clubs/${agm.club.slug}/agm` : `/clubs/${agm.club.slug}`} className="text-sm text-ink-muted hover:underline">
                        ← {agm.club.name}
                    </Link>
                    <h1 className="text-2xl font-bold">Committee election{election && ` ${election.term}`}</h1>
                </header>
                {!election ? (
                    manages ? (
                        <Card title="Set up the election">
                            <form
                                className="grid gap-4"
                                onSubmit={(e) => {
                                    e.preventDefault();
                                    setup.post(`/agms/${agm.id}/election`, { preserveScroll: true });
                                }}
                            >
                                <p className="text-sm text-ink-muted">President, Vice President, Secretary and Treasurer are always on the ballot. Add other positions if needed.</p>
                                <div className="grid gap-4 sm:grid-cols-2">
                                    {setupField('nominations_open_at', 'Nominations open')}
                                    {setupField('nominations_close_at', 'Nominations close')}
                                    {setupField('voting_open_at', 'Voting opens')}
                                    {setupField('voting_close_at', 'Voting closes')}
                                </div>
                                <div className="flex items-end gap-2">
                                    <div className="flex-1" onKeyDown={onEnter(addExtra)}>
                                        <Input value={extra} onChange={setExtra} label="Extra position" placeholder="e.g. Publicity Director" />
                                    </div>
                                    <Button type="button" variant="secondary" onClick={addExtra}>
                                        Add
                                    </Button>
                                </div>
                                {setup.data.extra_positions.length > 0 && (
                                    <div className="flex flex-wrap gap-2">
                                        {setup.data.extra_positions.map((p, i) => (
                                            <Badge key={p} tone="info">
                                                {p}{' '}
                                                <button type="button" className="ml-1" aria-label={`Remove ${p}`} onClick={() => setup.setData('extra_positions', setup.data.extra_positions.filter((_, x) => x !== i))}>
                                                    ×
                                                </button>
                                            </Badge>
                                        ))}
                                    </div>
                                )}
                                <Checkbox checked={setup.data.allow_alumni_vote} onChange={(v) => setup.setData('allow_alumni_vote', v)} label="Allow alumni members to vote" />
                                <div>
                                    <Button type="submit" loading={setup.processing}>
                                        Create election
                                    </Button>
                                </div>
                            </form>
                        </Card>
                    ) : (
                        <EmptyState title="No election yet" description="The committee will announce the nomination and voting dates." />
                    )
                ) : (
                    <>
                        <section className="grid gap-2 rounded-card bg-gradient-to-r from-brand-yellow/25 to-brand-orange/15 p-5 text-sm sm:grid-cols-3">
                            <p className="text-lg font-bold sm:col-span-3">{PHASE_LABEL[election.phase]}</p>
                            <p>
                                Nominations: {dateTime(election.nominations[0])} → {dateTime(election.nominations[1])}
                            </p>
                            <p>
                                Voting: {dateTime(election.voting[0])} → {dateTime(election.voting[1])}
                            </p>
                            <p>
                                {election.allow_alumni_vote ? 'Alumni may vote' : 'Active members vote'} · one ticket per position · change your mind up to {election.vote_changes} times
                            </p>
                            {election.phase === 'published' && (
                                <p className="font-medium sm:col-span-3">
                                    {election.handed_over
                                        ? 'The new committee has taken over.'
                                        : `The current committee hands over until ${new Date(election.handover_on).toLocaleDateString('en-MY', { dateStyle: 'long' })}, when the new committee takes the titles and access.`}
                                </p>
                            )}
                        </section>
                        {manages && editable && (
                            <section className="flex flex-wrap items-end gap-2 rounded-card border border-line bg-surface p-4">
                                <div className="min-w-48 flex-1" onKeyDown={onEnter(addPosition)}>
                                    <Input value={newPosition} onChange={setNewPosition} label="Add a position to the ballot" placeholder="e.g. Publicity Director" />
                                </div>
                                <Button variant="secondary" onClick={addPosition}>
                                    Add position
                                </Button>
                            </section>
                        )}
                        {election.flags.length > 0 && (
                            <ul className="grid gap-1 rounded-card border border-warning/40 bg-warning/5 p-4 text-sm">
                                {election.flags.map((f) => (
                                    <li key={f.position + f.issue}>
                                        <strong>{f.position}</strong>: {FLAG_TEXT[f.issue]}
                                    </li>
                                ))}
                            </ul>
                        )}
                        {election.positions.map((p) => (
                            <Card
                                key={p.id}
                                title={
                                    <span className="flex flex-wrap items-center gap-2">
                                        {p.position} {p.is_compulsory && <Badge>compulsory</Badge>}
                                        {manages && editable && !p.is_compulsory && (
                                            <button type="button" className="ml-auto text-xs font-normal text-ink-muted hover:text-danger" onClick={() => removePosition(p)}>
                                                Remove
                                            </button>
                                        )}
                                    </span>
                                }
                            >
                                <ul className="grid gap-3 sm:grid-cols-2">
                                    {p.candidates.map((c) => (
                                        <li key={c.id} className={`grid gap-2 rounded-card border p-4 transition ${p.winner_id === c.id ? 'border-success bg-success/5' : 'border-line'} ${highlight === c.id ? 'ring-4 ring-brand-magenta/30' : ''}`}>
                                            <div className="flex items-center gap-3">
                                                <Avatar src={c.photo_url} name={c.name} size="md" />
                                                <div className="min-w-0 flex-1">
                                                    <p className="font-semibold">
                                                        {c.name} {p.winner_id === c.id && <Badge tone="success">Elected</Badge>}
                                                    </p>
                                                    <p className="text-xs text-ink-muted">
                                                        {c.programme} · {c.year_group}
                                                    </p>
                                                </div>
                                                {c.votes !== null && <span className="text-lg font-bold tabular-nums">{c.votes}</span>}
                                            </div>
                                            <p className="whitespace-pre-line text-sm">{c.statement}</p>
                                            {c.is_me ? (
                                                <p className="text-xs text-ink-muted">You cannot vote for yourself.</p>
                                            ) : p.voted_for === c.id ? (
                                                <p className="text-xs font-medium text-success">✓ Your vote</p>
                                            ) : (
                                                election.phase === 'voting' &&
                                                (!p.voted_for || p.changes_left > 0) && (
                                                    <Button size="sm" variant={p.voted_for ? 'secondary' : 'primary'} onClick={() => vote(p, c)}>
                                                        {p.voted_for ? `Change vote to ${c.name.split(' ')[0]}` : 'Vote'}
                                                    </Button>
                                                )
                                            )}
                                            {c.qr_svg && (
                                                <button type="button" className="justify-self-start text-xs font-medium text-brand-blue" onClick={() => setSharing(c)}>
                                                    Share QR to promote
                                                </button>
                                            )}
                                        </li>
                                    ))}
                                    {!p.candidates.length && <li className="text-sm text-ink-muted">No candidates yet.</li>}
                                </ul>
                                {election.phase === 'voting' && p.voted_for && (
                                    <p className="mt-2 text-xs text-ink-muted">{p.changes_left > 0 ? `You can change this vote ${p.changes_left} more time${p.changes_left === 1 ? '' : 's'}.` : 'Your vote is final.'}</p>
                                )}
                                {(election.phase === 'nominating' || (manages && editable)) && (
                                    <div className="mt-3 flex flex-wrap gap-2">
                                        {election.phase === 'nominating' && (
                                            <Button variant="secondary" size="sm" onClick={() => setStanding(p)}>
                                                Stand for {p.position}
                                            </Button>
                                        )}
                                        {manages && editable && (
                                            <Button variant="secondary" size="sm" onClick={() => setNaming(p)}>
                                                ＋ Name a candidate
                                            </Button>
                                        )}
                                    </div>
                                )}
                            </Card>
                        ))}
                        {manages && election.phase === 'closed' && (
                            <Button className="justify-self-start" onClick={() => router.post(`/elections/${election.id}/publish`, {}, { preserveScroll: true })}>
                                Publish results
                            </Button>
                        )}
                    </>
                )}
            </div>
            <Modal open={naming !== null} onClose={() => setNaming(null)} title={`Name a candidate for ${naming?.position ?? ''}`}>
                <form className="grid gap-4" onSubmit={nameCandidate}>
                    {!typed ? (
                        <PersonPicker value={pick.data.student_number} onChange={(v) => pick.setData('student_number', v)} label="Name or student ID" placeholder="Type a name (e.g. tan) or student ID" error={pick.errors.student_number} />
                    ) : (
                        <Input value={pick.data.student_number} onChange={(v) => pick.setData('student_number', v)} label="Student ID" placeholder="e.g. QIU-202407-0001" required error={pick.errors.student_number} />
                    )}
                    <button
                        type="button"
                        className="justify-self-start text-xs text-brand-blue"
                        onClick={() => {
                            setTyped(!typed);
                            pick.setData('student_number', '');
                        }}
                    >
                        {typed ? 'Search by name instead' : "Can't find them? Type the student ID"}
                    </button>
                    <Textarea value={pick.data.statement} onChange={(v) => pick.setData('statement', v)} label="Why they are standing (optional)" rows={3} />
                    <p className="text-xs text-ink-muted">They must be an active member who can serve the full {election?.term} term.</p>
                    <Button type="submit" disabled={!pick.data.student_number} loading={pick.processing}>
                        Add candidate
                    </Button>
                </form>
            </Modal>
            <Modal
                open={sharing !== null}
                onClose={() => {
                    setSharing(null);
                    setCopied(false);
                }}
                title={`Vote ${sharing?.name ?? ''}`}
            >
                {sharing && (
                    <div className="grid justify-items-center gap-3 text-center text-sm">
                        {/* Server-generated SVG of the candidate's own ballot link. */}
                        <div className="size-56 rounded-card bg-white p-3 [&_svg]:size-full" dangerouslySetInnerHTML={{ __html: sharing.qr_svg ?? '' }} />
                        <p className="text-ink-muted">Members scan this to open the ballot with {sharing.name} highlighted. Print it or share it in your club chats.</p>
                        <Button variant="secondary" onClick={() => void navigator.clipboard.writeText(sharing.share_url).then(() => setCopied(true))}>
                            {copied ? 'Link copied' : 'Copy link'}
                        </Button>
                    </div>
                )}
            </Modal>
            <Modal open={standing !== null} onClose={() => setStanding(null)} title={`Stand for ${standing?.position ?? ''}`}>
                <form className="grid gap-4" onSubmit={nominate}>
                    <p className="text-sm text-ink-muted">You must be an active member and able to serve the full {election?.term} term (July–June).</p>
                    <Textarea value={nomination.data.statement} onChange={(v) => nomination.setData('statement', v)} label="Your statement" rows={5} error={nomination.errors.statement} />
                    <FileUpload files={nomination.data.photo} onChange={(f) => nomination.setData('photo', f)} label="Candidate photo (optional)" accept="image/*" />
                    <Button type="submit" loading={nomination.processing}>
                        Submit nomination
                    </Button>
                </form>
            </Modal>
        </AppLayout>
    );
}
