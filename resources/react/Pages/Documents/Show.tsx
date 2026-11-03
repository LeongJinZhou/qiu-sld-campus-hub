import ApprovalTracker from '@react/Components/forms/ApprovalTracker';
import ProposalView from '@react/Components/forms/ProposalView';
import Badge from '@react/Components/ui/Badge';
import Button from '@react/Components/ui/Button';
import Card from '@react/Components/ui/Card';
import AppLayout from '@react/Layouts/AppLayout';
import type { PresentedDocument } from '@/types';
import { Head, Link } from '@inertiajs/react';

interface WithdrawalData {
    items: { description: string; unit_sen: number; quantity: number; amount_sen: number; method: string }[];
    total_sen: number;
}
const money = (sen: number): string => `RM ${(sen / 100).toFixed(2)}`;
const statusTone = (s: string): 'success' | 'warning' | 'danger' | 'neutral' => (s === 'approved' ? 'success' : s === 'returned' ? 'warning' : s === 'rejected' ? 'danger' : 'neutral');

export default function Show({ document, canEdit, downloads }: { document: PresentedDocument; canEdit: boolean; downloads: string[] }) {
    const raw = document.data as unknown as Record<string, unknown>;
    const withdrawal = raw as unknown as WithdrawalData;
    const counts = raw as Record<string, number>;
    const objectives = ((raw.objectives as string[] | undefined) ?? []).filter((o) => o).length;

    return (
        <AppLayout>
            <Head title={document.type_label} />
            <div className="grid gap-6 lg:grid-cols-[1fr_24rem]">
                <div className="grid content-start gap-4">
                    <header className="flex flex-wrap items-center justify-between gap-3">
                        <div>
                            <h1 className="text-xl font-bold">{document.type_label}</h1>
                            <p className="text-sm text-ink-muted">
                                {document.club.name}
                                {document.event && ` · ${document.event.name}`}
                                {document.version && ` · v${document.version}`}
                            </p>
                        </div>
                        <Badge tone={statusTone(document.status)}>{document.status.replace('_', ' ')}</Badge>
                    </header>
                    {downloads.length > 0 && (
                        <div className="flex flex-wrap gap-2">
                            {downloads.map((format) => (
                                <a key={format} href={`/documents/${document.id}/download/${format}`}>
                                    <Button variant="secondary" size="sm">
                                        Download official {format.toUpperCase()}
                                    </Button>
                                </a>
                            ))}
                        </div>
                    )}
                    {canEdit && document.event && (
                        <Link href={`/events/${document.event.slug}/proposal`}>
                            <Button>Edit and resubmit</Button>
                        </Link>
                    )}
                    {document.type === 'agm_report' && document.agm_id && (
                        <Card title="01 AGM Report">
                            <p className="text-sm">{objectives} objectives · attendance, committee and election sections are frozen from records.</p>
                            <Link href={`/agms/${document.agm_id}/report`} className="mt-3 inline-block">
                                <Button variant="secondary" size="sm">
                                    Open the full report
                                </Button>
                            </Link>
                        </Card>
                    )}
                    {document.type === 'proposal' && document.version ? (
                        <ProposalView document={document} />
                    ) : document.type === 'fund_withdrawal' ? (
                        <Card title="07 Fund Withdrawal / Transfer">
                            <ul className="grid gap-1 text-sm">
                                {(withdrawal.items ?? []).map((item, i) => (
                                    <li key={i} className="flex justify-between gap-3">
                                        <span>
                                            {i + 1}. {item.description} · {money(item.unit_sen)} × {item.quantity}{' '}
                                            <span className="text-ink-muted">({item.method === 'vendor' ? 'QIU to vendor' : 'Student self purchase'})</span>
                                        </span>
                                        <span className="tabular-nums">{money(item.amount_sen)}</span>
                                    </li>
                                ))}
                            </ul>
                            <p className="mt-3 text-right font-semibold">Total {money(withdrawal.total_sen ?? 0)}</p>
                            <p className="mt-2 text-xs text-ink-muted">Bank details appear only on the official form download.</p>
                        </Card>
                    ) : (
                        document.type === 'attendance_list' && (
                            <Card title="03 Event Attendance List">
                                <dl className="grid grid-cols-3 gap-3 text-center">
                                    {(['participants', 'committee', 'registered'] as const).map((k) => (
                                        <div key={k} className="rounded-control bg-surface-muted p-3">
                                            <dd className="text-2xl font-bold">{counts[k] ?? 0}</dd>
                                            <dt className="text-xs capitalize text-ink-muted">{k}</dt>
                                        </div>
                                    ))}
                                </dl>
                                <p className="mt-3 text-sm text-ink-muted">Download the official form above. Signatures appear on it as each person signs.</p>
                            </Card>
                        )
                    )}
                </div>
                <aside className="order-first lg:order-none">
                    <Card title="Approval progress">
                        <ApprovalTracker progress={document.progress} steps={document.steps} />
                    </Card>
                </aside>
            </div>
        </AppLayout>
    );
}
