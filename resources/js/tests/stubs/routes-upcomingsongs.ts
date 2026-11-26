export const store = (party: string | {party: string}) => ({
    url: `/api/v1/parties/${typeof party === 'string' ? party : party.party}/upcomingsongs`,
    method: 'post' as const,
});
export const index = (party: string | {code: string}) => ({
    url: `/api/v1/parties/${typeof party === 'string' ? party : party.code}/upcomingsongs`,
    method: 'get' as const,
});
export const destroy = (args: {party: string; upcomingsong: number}) => ({
    url: `/api/v1/parties/${args.party}/upcomingsongs/${args.upcomingsong}`,
    method: 'delete' as const,
});
export const play = (args: {party: string; upcomingsong: number}) => ({
    url: `/api/v1/parties/${args.party}/upcomingsongs/${args.upcomingsong}/play`,
    method: 'post' as const,
});
