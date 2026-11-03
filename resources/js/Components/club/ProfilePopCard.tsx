import Avatar from '@/Components/ui/Avatar';
import Modal from '@/Components/ui/Modal';
import type { MemberCard } from '@/types';

export default function ProfilePopCard({ member, open, onClose }: { member: MemberCard | null; open: boolean; onClose: () => void }) {
    return (
        <Modal open={open} onClose={onClose}>
            {member && (
                <div className="grid justify-items-center gap-3 text-center">
                    <Avatar src={member.photo_url} name={member.name} size="xl" ring />
                    <div>
                        <p className="text-lg font-semibold">{member.name}</p>
                        <p className="text-gradient-warm font-medium">{member.position}</p>
                        <p className="text-sm text-ink-muted">{[member.programme_short, member.year_group, member.title].filter(Boolean).join(' · ')}</p>
                    </div>
                    <a href={member.profile_url} className="mt-2 rounded-control bg-ink px-4 py-2 text-sm font-medium text-white">
                        View full profile
                    </a>
                </div>
            )}
        </Modal>
    );
}
