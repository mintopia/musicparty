const code = (party: string | {party: string}): string => (typeof party === 'string' ? party : party.party);

export const control = (party: string | {party: string}) => ({
    url: `/api/v1/parties/${code(party)}/control`,
    method: 'post' as const,
});
export const devices = (party: string | {party: string}) => ({
    url: `/api/v1/parties/${code(party)}/devices`,
    method: 'get' as const,
});
export const activate = (party: string | {party: string}) => ({
    url: `/api/v1/parties/${code(party)}/activate`,
    method: 'post' as const,
});
