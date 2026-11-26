import {flushPromises, mount} from '@vue/test-utils';
import axios from 'axios';
import {afterEach, beforeEach, describe, expect, it, vi} from 'vitest';
import {formatLatency, requestBlocks} from '@/lib/aiReviews';
import Index from '@/pages/Parties/AiReviews/Index.vue';
import type {AiReviewDetail, AiReviewRow} from '@/types';

const inertia = vi.hoisted(() => ({get: vi.fn()}));

vi.mock('@inertiajs/vue3', () => ({
    Head: {render: () => null},
    Link: {props: ['href'], template: '<a :href="href"><slot /></a>'},
    router: inertia,
}));

vi.mock('@/layouts/AppLayout.vue', () => ({
    default: {template: '<div><slot name="breadcrumbs" /><slot name="header" /><slot /></div>'},
}));

vi.mock('@/components/PartyPlayer.vue', () => ({default: {template: '<div data-test="party-player" />'}}));

vi.mock('@/routes', () => ({home: () => ({url: '/'})}));

const party = {code: 'ABCD', name: 'Party'};
const playerState = {now: null, next: null, current: null, status: null};
const outcomes = [
    {code: 'allow', name: 'Allowed'},
    {code: 'flag', name: 'Flagged'},
    {code: 'reject', name: 'Rejected'},
    {code: 'error', name: 'Error, allowed'},
];

function row(id: number, extra: Partial<AiReviewRow> = {}): AiReviewRow {
    return {
        id,
        createdAt: {iso: '2026-11-26T10:00:00+00:00', human: '2 minutes ago'},
        user: 'Dana',
        track: {title: `A very long track title that must stay fully visible ${id}`, artists: 'Artist One, Artist Two'},
        outcome: 'allow',
        outcomeLabel: 'Allowed',
        reason: null,
        driver: 'decision-model',
        model: 'jev-latest',
        latencyMs: 812,
        promptTokens: 120,
        completionTokens: 8,
        totalTokens: 128,
        requested: true,
        ...extra,
    };
}

function detail(id: number, extra: Partial<AiReviewDetail> = {}): AiReviewDetail {
    return {
        ...row(id),
        endpointHost: 'api.typesafe.ai',
        score: 4,
        confidence: 0.9,
        request: {
            model: 'jev-latest',
            state: JSON.stringify({title: 'x', party_context: 'Tracks played: 3'}),
            questions: {},
        },
        response: {answers: {suitability: {score: 4}}},
        partyContext: 'Tracks played: 3',
        errorClass: null,
        errorMessage: null,
        trackId: 't1',
        trackProvider: 'fake',
        ...extra,
    };
}

function paginator(data: AiReviewRow[], extra: Record<string, unknown> = {}) {
    return {
        data,
        current_page: 1,
        last_page: 1,
        prev_page_url: null,
        next_page_url: null,
        links: [],
        from: data.length === 0 ? null : 1,
        to: data.length === 0 ? null : data.length,
        total: data.length,
        ...extra,
    };
}

function mountIndex(
    data: AiReviewRow[],
    filters = {outcome: '', q: ''},
    extra: Record<string, unknown> = {},
    props: Record<string, unknown> = {},
) {
    return mount(Index, {
        props: {
            party,
            playerState,
            filters,
            outcomes,
            retentionDays: 30,
            holdsFlagged: true,
            settingsUrl: '/parties/ABCD/edit#flag_requests',
            reviews: paginator(data, extra),
            ...props,
        },
    });
}

beforeEach(() => {
    inertia.get.mockReset();
});

afterEach(() => {
    vi.restoreAllMocks();
});

describe('AI reviews list', () => {
    it('renders the player, a full-width table and the columns with whole track names', () => {
        const wrapper = mountIndex([
            row(1, {outcome: 'reject', outcomeLabel: 'Rejected', reason: 'Not for this party'}),
            row(2, {user: null, totalTokens: null}),
        ]);

        expect(wrapper.find('[data-test="party-player"]').exists()).toBe(true);
        expect(wrapper.find('.col-xl-8').exists()).toBe(false);
        const rows = wrapper.findAll('[data-test="review-row"]');
        expect(rows).toHaveLength(2);
        expect(rows[0]?.text()).toContain('A very long track title that must stay fully visible 1');
        expect(rows[0]?.find('[data-test="review-outcome"]').text()).toBe('Rejected');
        expect(rows[0]?.find('[data-test="review-reason"]').text()).toBe('Not for this party');

        expect(rows[0]?.text()).toContain('812 ms');
        expect(rows[0]?.text()).toContain('128');
        expect(rows[1]?.find('[data-test="review-user"]').text()).toBe('Unknown');
        expect(rows[1]?.find('[data-test="review-reason"]').text()).toBe('None');
        expect(wrapper.text()).toContain('1-2 of 2');
        expect(wrapper.text()).toContain('Kept for 30 days');
    });

    it('hides the not-held notice when flagged requests are held', () => {
        const wrapper = mountIndex([row(1, {outcome: 'flag', outcomeLabel: 'Flagged'})]);

        expect(wrapper.find('[data-test="not-held-notice"]').exists()).toBe(false);
        expect(wrapper.find('[data-test="not-held"]').exists()).toBe(false);
    });

    it('warns that flagged requests are not held and links to the settings', () => {
        const wrapper = mountIndex(
            [row(1, {outcome: 'flag', outcomeLabel: 'Flagged'}), row(2)],
            undefined,
            {},
            {holdsFlagged: false},
        );

        expect(wrapper.get('[data-test="not-held-notice"]').text()).toContain("Flagged requests aren't held");
        expect(wrapper.get('[data-test="turn-on-approval"]').attributes('href')).toBe(
            '/parties/ABCD/edit#flag_requests',
        );
        const rows = wrapper.findAll('[data-test="review-row"]');
        expect(rows[0]?.find('[data-test="not-held"]').exists()).toBe(true);
        expect(rows[1]?.find('[data-test="not-held"]').exists()).toBe(false);
    });

    it('shows the first-run empty state with no filters', () => {
        const wrapper = mountIndex([]);

        expect(wrapper.find('[data-test="empty-state"]').text()).toContain('No AI reviews yet');
        expect(wrapper.find('table').exists()).toBe(false);
        expect(wrapper.find('[data-test="clear-filters"]').exists()).toBe(false);
    });

    it('shows a no-match empty state with a way back when filters are active', () => {
        const wrapper = mountIndex([], {outcome: 'flag', q: 'zzz'});

        expect(wrapper.find('[data-test="empty-state"]').text()).toContain('No reviews match these filters');
        expect(wrapper.find('[data-test="clear-filters"]').attributes('href')).toBe('/parties/ABCD/ai-reviews');
    });

    it('submits the search and outcome as query parameters and omits empty ones', async () => {
        const wrapper = mountIndex([row(1)]);

        await wrapper.get('input[name="q"]').setValue('  wham ');
        await wrapper.get('select[name="outcome"]').setValue('flag');
        await wrapper.get('form').trigger('submit');
        expect(inertia.get).toHaveBeenLastCalledWith('/parties/ABCD/ai-reviews', {q: 'wham', outcome: 'flag'});

        await wrapper.get('input[name="q"]').setValue('');
        await wrapper.get('select[name="outcome"]').setValue('');
        await wrapper.get('form').trigger('submit');
        expect(inertia.get).toHaveBeenLastCalledWith('/parties/ABCD/ai-reviews', {});
    });

    it('starts the form from the active filters', () => {
        const wrapper = mountIndex([row(1)], {outcome: 'reject', q: 'brightside'});

        expect((wrapper.get('input[name="q"]').element as HTMLInputElement).value).toBe('brightside');
        expect((wrapper.get('select[name="outcome"]').element as HTMLSelectElement).value).toBe('reject');
    });

    it('renders the pagination when there are several pages', () => {
        const wrapper = mountIndex([row(1)], undefined, {
            last_page: 2,
            links: [
                {url: null, label: '&laquo; Previous', active: false},
                {url: '/x?page=1', label: '1', active: true},
                {url: '/x?page=2', label: '2', active: false},
                {url: '/x?page=2', label: 'Next &raquo;', active: false},
            ],
        });

        expect(wrapper.find('nav[aria-label="Pagination"]').exists()).toBe(true);
    });
});

describe('AI review detail', () => {
    it('loads the detail on demand, shows the request, party context, response and tokens, and collapses', async () => {
        const get = vi.spyOn(axios, 'get').mockResolvedValue({data: {data: detail(1)}});
        const wrapper = mountIndex([row(1)]);

        expect(wrapper.find('[data-test="detail-row"]').exists()).toBe(false);
        await wrapper.get('[data-test="toggle-detail"]').trigger('click');
        await flushPromises();

        expect(get).toHaveBeenCalledWith('/parties/ABCD/ai-reviews/1');
        const panel = wrapper.get('[data-test="ai-review-detail"]');
        const blocks = panel.findAll('[data-test="code-block"]').map((block) => block.text());
        expect(blocks.some((text) => text.includes('Party context sent') && text.includes('Tracks played: 3'))).toBe(
            true,
        );
        expect(blocks.some((text) => text.includes('Request body') && text.includes('"party_context"'))).toBe(true);
        expect(blocks.some((text) => text.includes('Raw response') && text.includes('"suitability"'))).toBe(true);
        expect(panel.findAll('[data-test="copy-button"]').length).toBe(blocks.length);
        expect(panel.text()).toContain('api.typesafe.ai');
        expect(panel.text()).toContain('812 ms');
        expect(panel.text()).toContain('128');

        await wrapper.get('[data-test="toggle-detail"]').trigger('click');
        expect(wrapper.find('[data-test="detail-row"]').exists()).toBe(false);
        await wrapper.get('[data-test="toggle-detail"]').trigger('click');
        await flushPromises();
        expect(get).toHaveBeenCalledTimes(1);
    });

    it('shows a loading state while the detail is fetched', async () => {
        vi.spyOn(axios, 'get').mockReturnValue(new Promise(() => undefined));
        const wrapper = mountIndex([row(1)]);

        await wrapper.get('[data-test="toggle-detail"]').trigger('click');

        expect(wrapper.find('[data-test="detail-loading"]').exists()).toBe(true);
    });

    it('shows an error with a retry when the detail cannot be loaded', async () => {
        const get = vi
            .spyOn(axios, 'get')
            .mockRejectedValueOnce(new Error('boom'))
            .mockResolvedValueOnce({data: {data: detail(1)}});
        const wrapper = mountIndex([row(1)]);

        await wrapper.get('[data-test="toggle-detail"]').trigger('click');
        await flushPromises();
        expect(wrapper.find('[data-test="detail-failed"]').exists()).toBe(true);

        await wrapper.get('[data-test="detail-failed"] button').trigger('click');
        await flushPromises();
        expect(get).toHaveBeenCalledTimes(2);
        expect(wrapper.find('[data-test="ai-review-detail"]').exists()).toBe(true);
    });

    it('shows the error class and message and notes a missing response', async () => {
        vi.spyOn(axios, 'get').mockResolvedValue({
            data: {
                data: detail(1, {
                    outcome: 'error',
                    outcomeLabel: 'Error, allowed',
                    response: null,
                    score: null,
                    errorClass: 'App\\Mods\\AiRequestReview\\DecisionModelException',
                    errorMessage: 'Decision model responded with HTTP 500',
                }),
            },
        });
        const wrapper = mountIndex([row(1, {outcome: 'error', outcomeLabel: 'Error, allowed'})]);

        await wrapper.get('[data-test="toggle-detail"]').trigger('click');
        await flushPromises();

        expect(wrapper.get('[data-test="detail-error"]').text()).toContain('Decision model responded with HTTP 500');
        expect(wrapper.text()).not.toContain('Raw response');
        expect(wrapper.find('[data-test="detail-no-response"]').exists()).toBe(true);
    });

    it('splits a chat request into system and user message blocks', () => {
        const blocks = requestBlocks({
            model: 'review-1',
            messages: [
                {role: 'system', content: 'You review song requests'},
                {role: 'user', content: JSON.stringify({title: 'x', party_context: 'ctx'})},
            ],
        });

        expect(blocks.map((block) => block.title)).toEqual(['System message', 'User message']);
        expect(blocks[0]?.text).toBe('You review song requests');
        expect(blocks[1]?.text).toContain('"party_context": "ctx"');
    });

    it('formats latency in ms and seconds', () => {
        expect(formatLatency(420)).toBe('420 ms');
        expect(formatLatency(2500)).toBe('2.5 s');
    });
});
