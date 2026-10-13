export const index = (party: string | {code: string}) => ({
    url: `/parties/${typeof party === 'string' ? party : party.code}/songs`,
    method: 'get' as const,
});
