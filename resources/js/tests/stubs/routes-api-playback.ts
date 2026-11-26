export const start = (party: string | {party: string}) => ({
    url: `/api/v1/parties/${typeof party === 'string' ? party : party.party}/playback/start`,
    method: 'post' as const,
});
