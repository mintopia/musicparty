const code = (party: string | {code: string}): string => (typeof party === 'string' ? party : party.code);

export const show = (party: string | {code: string}) => ({url: `/parties/${code(party)}`, method: 'get' as const});
export const search = (party: string | {code: string}) => ({
    url: `/parties/${code(party)}/search`,
    method: 'get' as const,
});
