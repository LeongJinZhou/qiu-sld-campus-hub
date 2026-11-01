/** Parts of the 06 Event Proposal, used to show who is editing what and to point a returned proposal at its fixes. */
export const PROPOSAL_SECTIONS = {
    concept: "Concept paper (name, dates, nature, CCA, venue, SDGs, fund)",
    introduction: "Introduction",
    objectives: "Objectives",
    participants: "Target participants",
    activities: "Proposed activities",
    itinerary: "Itinerary",
    ict: "ICT & maintenance",
    logistics: "Logistic arrangements",
    committee: "Event committee & responsibilities",
    budget: "Estimated budget",
    conclusion: "Conclusion",
} as const;

export type ProposalSection = keyof typeof PROPOSAL_SECTIONS;
