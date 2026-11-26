const code = (party: string | {code: string}): string => (typeof party === 'string' ? party : party.code);

export const show = (party: string | {code: string}) => ({url: `/parties/${code(party)}`, method: 'get' as const});
export const search = (party: string | {code: string}) => ({
    url: `/parties/${code(party)}/search`,
    method: 'get' as const,
});
export const dashboard = (party: string | {code: string}) => ({
    url: `/parties/${code(party)}/dashboard`,
    method: 'get' as const,
});
export const edit = (party: string | {code: string}) => ({url: `/parties/${code(party)}/edit`, method: 'get' as const});
export const tv = (party: string | {code: string}) => ({url: `/parties/${code(party)}/tv`, method: 'get' as const});
export const player = (party: string | {code: string}) => ({
    url: `/parties/${code(party)}/player`,
    method: 'get' as const,
});
