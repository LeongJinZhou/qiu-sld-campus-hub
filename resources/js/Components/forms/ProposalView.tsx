import { hoursBetween, rm } from '@/lib/format';
import type { PresentedDocument } from '@/types';
import { Fragment } from 'react';

const ticked = (selected: (string | number)[] | undefined, map: Record<string | number, string> | undefined): string =>
    (selected ?? []).map((key) => map?.[key] ?? String(key)).join(', ') || '—';
const at = (v: string): string => (v ? v.replace('T', ' ') : '-');

/** Read-only rendering of the proposal in the order of the official 06 template. */
export default function ProposalView({ document }: { document: PresentedDocument }) {
    const d = document.data;
    const opts = document.options;
    const img = (path: string): string => document.image_base.replace(/\/?$/, '/') + path;
    const head = 'w-48 bg-surface-muted px-4 py-2 text-left align-top font-medium';
    const rows: [string, React.ReactNode, string?][] = [
        ['To', document.locked.to],
        ['From', document.locked.from],
        ['Date of Submission', document.locked.submitted_on ?? 'On submission'],
        ['Event Date & Time', `${d.starts_at.replace('T', ' ')} – ${d.ends_at.replace('T', ' ')}`],
        ['Event Name', d.name],
        ['Event Summary', d.summary, 'whitespace-pre-line'],
        ['Nature of event', `${ticked(d.nature, opts.natures)}${d.nature_other ? ` (${d.nature_other})` : ''}`],
        ['CCA Hours', `Participants: ${d.cca_participant} · Event Committee: ${d.cca_committee}`],
        ['Project Duration', `${hoursBetween(d.starts_at, d.ends_at)} hours`],
        ['Event Venue', d.venue],
        ['Mode', d.mode, 'capitalize'],
        ['SDG Goals', ticked(d.sdgs, opts.sdgs)],
        ['Collaboration', (d.collaboration ?? []).join(', ') || '—'],
        ['Total Budget', document.totals ? rm(document.totals.expenses_sen) : '—'],
        ['Source of fund', ticked(d.source_of_fund, opts.fundSources)],
    ];

    return (
        <article className="grid gap-6 text-sm">
            <section className="overflow-x-auto rounded-card border border-line bg-surface">
                <h2 className="border-b border-line bg-ink px-4 py-2 text-center text-sm font-semibold text-white">Student Organization Concept Paper</h2>
                <table className="w-full">
                    <tbody>
                        {rows.map(([label, value, cls]) => (
                            <tr key={label} className="border-t border-line">
                                <th className={head}>{label}</th>
                                <td className={`px-4 py-2 ${cls ?? ''}`}>{value}</td>
                            </tr>
                        ))}
                    </tbody>
                </table>
            </section>

            <section className="grid gap-4">
                <h2 className="text-lg font-bold uppercase">{d.name} Proposal</h2>
                <div>
                    <h3 className="font-semibold">Introduction</h3>
                    <p className="whitespace-pre-line">{d.introduction}</p>
                </div>
                <div>
                    <h3 className="font-semibold">Objectives</h3>
                    <ol className="list-decimal pl-5">
                        {d.objectives.map((o, i) => (
                            <li key={i}>{o}</li>
                        ))}
                    </ol>
                </div>
                <div>
                    <h3 className="font-semibold">Target Participants</h3>
                    <p>Expected number of participants: {d.expected_participants}</p>
                    <p>Eligibility: {d.eligibility}</p>
                </div>
                {!!d.speakers?.length && (
                    <div>
                        <h3 className="font-semibold">Speaker / External Profile</h3>
                        <table className="mt-1 w-full border border-line">
                            <tbody>
                                {d.speakers.map((p, i) => (
                                    <tr key={i} className="border-t border-line">
                                        <td className="w-20 px-3 py-2">{p.photo && <img src={img(p.photo)} alt={p.name} className="size-14 rounded-control object-cover" />}</td>
                                        <td className="px-3 py-2 font-medium">{p.name}</td>
                                        <td className="px-3 py-2">{p.designation}</td>
                                        <td className="px-3 py-2">{p.phone}</td>
                                        <td className="break-all px-3 py-2">{p.linkedin || '—'}</td>
                                    </tr>
                                ))}
                            </tbody>
                        </table>
                    </div>
                )}
                <div>
                    <h3 className="font-semibold">Proposed Activities</h3>
                    <table className="mt-1 w-full border border-line">
                        <tbody>
                            {d.activities.map((a, i) => (
                                <tr key={i} className="border-t border-line">
                                    <td className="w-48 px-3 py-2 font-medium">{a.name}</td>
                                    <td className="px-3 py-2">{a.description}</td>
                                </tr>
                            ))}
                        </tbody>
                    </table>
                </div>
                <div>
                    <h3 className="font-semibold">Tentative / Event Itinerary</h3>
                    <table className="mt-1 w-full border border-line">
                        <tbody>
                            {d.itinerary.map((r, i) => (
                                <tr key={i} className="border-t border-line">
                                    <td className="w-24 px-3 py-2">{r.start}</td>
                                    <td className="w-24 px-3 py-2">{r.end}</td>
                                    <td className="px-3 py-2">{r.agenda}</td>
                                </tr>
                            ))}
                        </tbody>
                    </table>
                </div>
                <div>
                    <h3 className="font-semibold">ICT &amp; Maintenance Requirement</h3>
                    <p className="whitespace-pre-line">{d.ict_required === false || !d.ict_requirements ? 'Not required' : d.ict_requirements}</p>
                </div>
                <div>
                    <h3 className="font-semibold">Logistic Arrangements</h3>
                    <p>Transportation {d.transport_required ? 'required' : 'not required'}</p>
                    {d.transport_required && (
                        <ol className="mt-1 grid gap-1">
                            {d.trips.map((t, i) => (
                                <li key={i} className="rounded-control border border-line px-3 py-2">
                                    <strong>
                                        Trip {i + 1}
                                        {t.arrangement && `: ${t.arrangement}`}
                                    </strong>
                                    <p>
                                        Departure {at(t.departure_at)} from {t.departure_pickup || '-'}
                                    </p>
                                    {t.stops.map((s, j) => (
                                        <p key={j}>
                                            {j === t.stops.length - 1 ? 'Drop-off' : `Stop ${j + 1}`}: {s.name || '-'} · {at(s.at)}
                                            {s.point && ` · ${s.point}`}
                                        </p>
                                    ))}
                                    <p>
                                        Return {at(t.return_at)} from {t.return_pickup || '-'}
                                    </p>
                                </li>
                            ))}
                        </ol>
                    )}
                </div>
                <div>
                    <h3 className="font-semibold">Event Committee &amp; Responsibilities</h3>
                    <table className="mt-1 w-full border border-line">
                        <tbody>
                            {document.committee.map((c, i) => (
                                <tr key={i} className="border-t border-line">
                                    <td className="px-3 py-2">{c.name}</td>
                                    <td className="px-3 py-2">{c.position}</td>
                                    <td className="px-3 py-2">{c.responsibility}</td>
                                </tr>
                            ))}
                        </tbody>
                    </table>
                </div>
                {document.totals && (
                    <div>
                        <h3 className="font-semibold">Estimated Budget</h3>
                        <p>
                            Revenue {rm(document.totals.revenue_sen)} · Expenses {rm(document.totals.expenses_sen)} · Profit / Loss{' '}
                            <strong className={document.totals.covers_expenses ? '' : 'text-danger'}>{rm(document.totals.profit_sen)}</strong>
                        </p>
                        {!document.totals.covers_expenses && <p className="text-danger">Revenue does not cover expenses.</p>}
                    </div>
                )}
                <div>
                    <h3 className="font-semibold">Conclusion</h3>
                    <p className="whitespace-pre-line">{d.conclusion}</p>
                </div>
                {(d.extra_sections ?? []).map((b, i) => (
                    <Fragment key={`x${i}`}>
                        {b.kind === 'heading' && <h3 className="text-base font-bold">{b.text}</h3>}
                        {b.kind === 'subheading' && <h4 className="font-semibold">{b.text}</h4>}
                        {b.kind === 'paragraph' && <p className="whitespace-pre-line">{b.text}</p>}
                        {b.kind === 'bullets' && (
                            <ul className="list-disc pl-5">
                                {b.text
                                    .split('\n')
                                    .filter((l) => l.trim())
                                    .map((line, j) => (
                                        <li key={j}>{line}</li>
                                    ))}
                            </ul>
                        )}
                        {b.kind === 'image' && b.image && (
                            <figure className="grid justify-items-center gap-1">
                                <img src={img(b.image)} alt={b.text || 'Picture'} className="max-h-80 max-w-full rounded-control object-contain" />
                                {b.text && <figcaption className="text-xs italic text-ink-muted">{b.text}</figcaption>}
                            </figure>
                        )}
                        {b.kind === 'table' && (
                            <table className="w-full border border-line">
                                <tbody>
                                    {b.rows.map((row, r) => (
                                        <tr key={r} className={`border-t border-line ${r === 0 ? 'bg-surface-muted font-medium' : ''}`}>
                                            {row.map((cell, c) => (
                                                <td key={c} className="border-l border-line px-3 py-2">
                                                    {cell}
                                                </td>
                                            ))}
                                        </tr>
                                    ))}
                                </tbody>
                            </table>
                        )}
                    </Fragment>
                ))}
            </section>
        </article>
    );
}
