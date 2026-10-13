export const store = (args: {party: string; upcomingsong: number}) => ({
    url: `/api/v1/parties/${args.party}/upcomingsongs/${args.upcomingsong}/vote`,
    method: 'post' as const,
});
