export const store = (args: {party: string; playedsong: number}) => ({
    url: `/api/v1/parties/${args.party}/playedsongs/${args.playedsong}/rate`,
    method: 'post' as const,
});
