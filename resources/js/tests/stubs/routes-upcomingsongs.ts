export const store = (party: string | {party: string}) => ({
    url: `/api/v1/parties/${typeof party === 'string' ? party : party.party}/upcomingsongs`,
    method: 'post' as const,
});
