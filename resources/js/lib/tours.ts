/**
 * Guided tours. Each step points at a real element marked data-tour="…" on the page. Steps whose element is not on
 * screen (e.g. the calendar link on a phone, a button only committees see) are skipped, so what is highlighted is
 * always something the person can actually tap.
 */
export interface TourStep {
    target: string;
    title: string;
    body: string;
}

export type TourName = 'student' | 'committee';

export const TOURS: Record<TourName, { title: string; page: string; steps: TourStep[] }> = {
    student: {
        title: 'Welcome to Campus Hub',
        page: '/feed',
        steps: [
            { target: 'nav-feed', title: 'Your feed', body: 'Posts and events from QIU clubs. The last 7 days show in full; older posts fold by fortnight, month and year.' },
            { target: 'feed-create', title: 'Share with your club', body: 'Committee members post photos, event recaps and 24-hour stories here.' },
            { target: 'nav-search', title: 'Find anything', body: 'Search clubs, events and people. Results appear as you type.' },
            { target: 'nav-activities', title: 'Your activities', body: 'Your event tickets, the clubs you joined and the clubs you help run.' },
            { target: 'nav-calendar', title: 'Campus calendar', body: 'Every approved event by month. Add one to Google Calendar, or subscribe to them all.' },
            {
                target: 'nav-notifications',
                title: 'Notifications',
                body: '"Action needed" lists what you still have to do (pay, sign, review). Updates can be filtered and cleared.',
            },
            {
                target: 'nav-profile',
                title: 'Your profile & settings',
                body: 'Your QR, CCA and certificates. Switch dark mode or replay this guide from Settings, just above Sign out.',
            },
        ],
    },
    committee: {
        title: 'Running your club',
        page: '',
        steps: [
            { target: 'club-header', title: 'Your club page', body: 'This is what students see: follow, join, and the club QR for posters and booths.' },
            {
                target: 'club-new-event',
                title: 'Start an event proposal',
                body: 'Fill the 06 proposal together with your committee; it saves itself and shows who is editing which part.',
            },
            { target: 'club-post', title: 'Post and stories', body: 'Share photos and recaps. Followers are notified; posting limits keep the feed fresh.' },
            { target: 'club-members', title: 'Members', body: 'Everyone who joined, with filters and export. Event participants shows who comes to events but has not joined.' },
            { target: 'club-finance', title: 'Finance', body: 'The ledger, fund withdrawals (07), the finance workbook export, and purchase claims for events.' },
            { target: 'club-payments', title: 'Payment review', body: 'Receipts the system could not confirm by itself wait here for a committee check.' },
            { target: 'club-meetings', title: 'Meetings', body: 'Schedule meetings with Google Meet and keep the minutes (13).' },
            { target: 'club-agm', title: 'AGM & elections', body: 'Your AGM report, and the committee election with voting, QR promotion and the 7-day handover.' },
            { target: 'committee-year', title: 'Committee years', body: 'Past committees stay on record; switch years here.' },
        ],
    },
};
