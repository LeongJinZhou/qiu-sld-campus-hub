import Avatar from '@/Components/ui/Avatar';
import Icon from '@/Components/ui/Icon';
import { Link } from '@inertiajs/react';

export interface MeetingCardData {
    id: number;
    title: string;
    kind: string;
    kind_label: string;
    audience: string;
    starts_at: string;
    ends_at: string;
    venue: string | null;
    online: boolean;
    meet_link: string | null;
    club: { slug: string; name: string; logo_url: string | null };
    url: string;
    can_open: boolean;
}

const day = (iso: string): { month: string; date: string } => {
    const d = new Date(iso);
    return { month: d.toLocaleDateString('en-MY', { month: 'short' }).toUpperCase(), date: String(d.getDate()) };
};
const when = (start: string, end: string): string => {
    const s = new Date(start);
    const e = new Date(end);
    const time = (d: Date): string => d.toLocaleTimeString('en-MY', { hour: 'numeric', minute: '2-digit' });
    return `${s.toLocaleDateString('en-MY', { weekday: 'short' })}, ${time(s)} – ${time(e)}`;
};

/** A club meeting or weekly activity, as shown in the feed and on a club's profile. */
export default function MeetingCard({ meeting, showClub = true }: { meeting: MeetingCardData; showClub?: boolean }) {
    const d = day(meeting.starts_at);
    const live = new Date(meeting.starts_at) <= new Date() && new Date() <= new Date(meeting.ends_at);
    return (
        <article className="flex gap-4 rounded-card border border-line bg-surface p-4 shadow-card">
            <div className="grid h-16 w-14 shrink-0 place-content-center rounded-control bg-primary/10 text-center text-primary">
                <span className="text-[0.65rem] font-bold tracking-wider">{d.month}</span>
                <span className="text-2xl font-bold leading-none">{d.date}</span>
            </div>
            <div className="grid min-w-0 flex-1 content-start gap-1">
                <div className="flex flex-wrap items-center gap-2 text-xs">
                    <span className="rounded-full bg-surface-muted px-2 py-0.5 font-medium text-ink-muted">{meeting.kind_label}</span>
                    {live && <span className="rounded-full bg-success/15 px-2 py-0.5 font-semibold text-success">Happening now</span>}
                </div>
                <Link href={meeting.url} className="truncate font-semibold hover:underline">
                    {meeting.title}
                </Link>
                <p className="flex flex-wrap gap-x-3 gap-y-0.5 text-xs text-ink-muted">
                    <span className="flex items-center gap-1 whitespace-nowrap">
                        <Icon name="clock" size={13} /> {when(meeting.starts_at, meeting.ends_at)}
                    </span>
                    {(meeting.venue || meeting.online) && (
                        <span className="flex items-center gap-1">
                            <Icon name="pin" size={13} /> {meeting.venue ?? 'Google Meet'}
                        </span>
                    )}
                    <span className="flex items-center gap-1 whitespace-nowrap">
                        <Icon name="users" size={13} /> {meeting.audience}
                    </span>
                </p>
                <div className="mt-1 flex flex-wrap items-center gap-3">
                    {showClub && (
                        <Link href={`/clubs/${meeting.club.slug}`} className="flex items-center gap-2 text-xs font-medium">
                            <Avatar src={meeting.club.logo_url} name={meeting.club.name} size="xs" />
                            {meeting.club.name}
                        </Link>
                    )}
                    {meeting.meet_link && (
                        <a href={meeting.meet_link} target="_blank" rel="noopener" className="text-xs font-semibold text-primary">
                            Join Google Meet
                        </a>
                    )}
                </div>
            </div>
        </article>
    );
}
