export const index = (party: string | {code: string}) => ({
    url: `/parties/${typeof party === 'string' ? party : party.code}/songs`,
    method: 'get' as const,
});

export const update = (args: {party: string | {code: string}; song: number | {id: number}}) => ({
    url: `/parties/${typeof args.party === 'string' ? args.party : args.party.code}/songs/${typeof args.song === 'number' ? args.song : args.song.id}`,
    method: 'put' as const,
});

export const show = (args: {party: string | {code: string}; song: number | {id: number}}) => ({
    url: `/parties/${typeof args.party === 'string' ? args.party : args.party.code}/songs/${typeof args.song === 'number' ? args.song : args.song.id}`,
    method: 'get' as const,
});
