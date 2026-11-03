/** Parts of the 06 Event Proposal, used to show who is editing what and to point a returned proposal at its fixes. */
export const PROPOSAL_SECTIONS = {
    concept: "Concept paper (name, dates, nature, CCA, venue, SDGs, fund)",
    introduction: "Introduction",
    objectives: "Objectives",
    participants: "Target participants",
    speakers: "Speaker / External Profile",
    activities: "Proposed activities",
    itinerary: "Itinerary",
    ict: "ICT & maintenance",
    logistics: "Logistic arrangements",
    committee: "Event committee & responsibilities",
    budget: "Estimated budget",
    conclusion: "Conclusion",
    extra: "Other parts (after the conclusion)",
} as const;

export type ProposalSection = keyof typeof PROPOSAL_SECTIONS;

/** Which part of the form a server validation error belongs to (e.g. "trips.0.arrangement" → logistics). */
export function sectionOfField(field: string): ProposalSection {
    const top = field.split('.')[0] ?? '';
    const map: Record<string, ProposalSection> = {
        introduction: 'introduction',
        objectives: 'objectives',
        eligibility: 'participants',
        speakers: 'speakers',
        activities: 'activities',
        itinerary: 'itinerary',
        ict_required: 'ict',
        ict_requirements: 'ict',
        transport_required: 'logistics',
        trips: 'logistics',
        budget: 'budget',
        deficit_reason: 'budget',
        conclusion: 'conclusion',
        extra_sections: 'extra',
    };
    return map[top] ?? 'concept';
}
