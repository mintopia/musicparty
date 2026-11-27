import {mount} from '@vue/test-utils';
import {afterEach, beforeEach, describe, expect, it, vi} from 'vitest';
import {aiReviewUrl, answerRows, formatLatency, requestBlocks} from '@/lib/aiReviews';
import Index from '@/pages/Parties/AiReviews/Index.vue';
import Show from '@/pages/Parties/AiReviews/Show.vue';
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
        criterion: null,
        criterionLabel: null,
        criterionConfidence: null,
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
    filters: {outcome: string; criterion: string; q: string} = {outcome: '', criterion: '', q: ''},
    extra: Record<string, unknown> = {},
    props: Record<string, unknown> = {},
) {
    return mount(Index, {
        props: {
            party,
            playerState,
            moderationTabs: {flaggedCount: 0, aiReviewEnabled: false},
            filters,
            outcomes,
            criteria: [{code: 'troll', name: 'Troll'}],
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

        expect(rows[1]?.find('[data-test="review-user"]').text()).toContain('Unknown');
        expect(rows[1]?.find('[data-test="review-reason"]').text()).toBe('None');
        expect(wrapper.text()).toContain('1-2 of 2');
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

        expect(wrapper.get('[data-test="not-held-notice"]').text()).toContain("Flagged Requests aren't held");
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
        const wrapper = mountIndex([], {outcome: 'flag', criterion: '', q: 'zzz'});

        expect(wrapper.find('[data-test="empty-state"]').text()).toContain('No matching reviews');
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
        const wrapper = mountIndex([row(1)], {outcome: 'reject', criterion: '', q: 'brightside'});

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

function mountShow(review: AiReviewDetail, extra: Record<string, unknown> = {}) {
    return mount(Show, {
        props: {
            party,
            playerState,
            moderationTabs: {flaggedCount: 0, aiReviewEnabled: true},
            backUrl: '/parties/ABCD/ai-reviews?outcome=flag',
            criterionLabels: {troll: 'Troll', none: 'None'},
            review,
            ...extra,
        },
    });
}

describe('AI review list links', () => {
    it('links each row to its own page keeping the filters and page', () => {
        const wrapper = mountIndex([row(7)], {outcome: 'flag', criterion: '', q: 'wham'}, {current_page: 2});

        expect(wrapper.get('[data-test="review-link"]').attributes('href')).toBe(
            '/parties/ABCD/ai-reviews/7?q=wham&outcome=flag&page=2',
        );
        expect(wrapper.find('[data-test="detail-row"]').exists()).toBe(false);
        expect(wrapper.get('[data-test="open-review"]').attributes('href')).toContain('/ai-reviews/7');
    });

    it('shows the criterion column and a dash when there is none', () => {
        const wrapper = mountIndex([row(1, {criterion: 'troll', criterionLabel: 'Troll'}), row(2)]);
        const cells = wrapper.findAll('[data-test="review-criterion"]').map((cell) => cell.text());

        expect(cells).toEqual(['Troll', '-']);
    });

    it('builds a plain show url without empty filters', () => {
        expect(aiReviewUrl('ABCD', 3, {q: '', outcome: '', page: 1})).toBe('/parties/ABCD/ai-reviews/3');
    });
});

describe('AI review page', () => {
    it('shows the verdict, track, metadata, answers and collapsible raw payloads', () => {
        const wrapper = mountShow(
            detail(1, {
                outcome: 'reject',
                outcomeLabel: 'Rejected',
                reason: 'Not for this party',
                response: {answers: {suitability: {score: 4, confidence: 0.8}, energy: {value: 'low'}}},
            }),
        );

        expect(wrapper.get('[data-test="review-outcome"]').text()).toBe('Rejected');
        expect(wrapper.get('[data-test="detail-reason"]').text()).toBe('Not for this party');
        expect(wrapper.get('[data-test="review-title"]').text()).toContain('A very long track title');
        expect(wrapper.get('[data-test="review-user"]').text()).toContain('Dana');
        expect(wrapper.get('[data-test="detail-endpoint"]').text()).toBe('api.typesafe.ai');
        expect(wrapper.get('[data-test="detail-latency"]').text()).toBe('812 ms');
        expect(wrapper.get('[data-test="detail-tokens"]').text()).toContain('128 total');
        expect(wrapper.get('[data-test="back-link"]').attributes('href')).toBe('/parties/ABCD/ai-reviews?outcome=flag');

        const answers = wrapper.findAll('[data-test="answer-row"]').map((answerRow) => answerRow.text());
        expect(answers).toHaveLength(2);
        expect(answers[0]).toContain('suitability');
        expect(answers[0]).toContain('4');
        expect(answers[1]).toContain('low');

        expect(wrapper.get('[data-test="raw-request"]').text()).toContain('"party_context"');
        expect(wrapper.get('[data-test="raw-response"]').text()).toContain('"suitability"');
        expect(wrapper.find('details[data-test="raw-request"]').exists()).toBe(true);
    });

    it('shows the failing criterion and its probability breakdown', () => {
        const wrapper = mountShow(
            detail(1, {
                criterion: 'troll',
                criterionLabel: 'Troll',
                criterionConfidence: 0.91,
                response: {
                    answers: {criterion: {type: 'choice', choice: 'troll', probabilities: {none: 0.09, troll: 0.91}}},
                },
            }),
        );

        expect(wrapper.get('[data-test="failing-criterion"]').text()).toContain('Troll');
        expect(wrapper.get('[data-test="failing-criterion"]').text()).toContain('91%');
        const bars = wrapper.findAll('[data-test="criterion-probability"]').map((bar) => bar.text());
        expect(bars).toEqual(['Troll91%', 'None9%']);
        expect(mountShow(detail(1)).find('[data-test="failing-criterion"]').exists()).toBe(false);
    });

    it('shows the error class and message and notes a missing response', () => {
        const wrapper = mountShow(
            detail(1, {
                outcome: 'error',
                outcomeLabel: 'Error, allowed',
                response: null,
                score: null,
                errorClass: 'App\\Mods\\AiRequestReview\\DecisionModelException',
                errorMessage: 'Decision model responded with HTTP 500',
            }),
        );

        expect(wrapper.get('[data-test="detail-error"]').text()).toContain('Decision model responded with HTTP 500');
        expect(wrapper.find('[data-test="raw-response"]').exists()).toBe(false);
        expect(wrapper.get('[data-test="detail-no-answers"]').text()).toContain('No response was received');
    });

    it('reads answers from objects, arrays and scalars generically', () => {
        expect(answerRows(null)).toEqual([]);
        expect(answerRows({answers: {a: {score: 3, confidence: 0.5}, b: 'yes', c: {foo: 1}}})).toEqual([
            {id: 'a', value: '3', confidence: '50%', probabilities: []},
            {id: 'b', value: 'yes', confidence: null, probabilities: []},
            {id: 'c', value: '{\n  "foo": 1\n}', confidence: null, probabilities: []},
        ]);
        expect(
            answerRows(
                {
                    answers: {
                        criterion: {
                            type: 'choice',
                            choice: 'troll',
                            probabilities: {none: 0.1, troll: 0.9},
                            confidence: 0.9,
                        },
                        suitability: {type: 'score', score: 1.2, probabilities: {'1': 0.7, '2': 0.2}},
                        other: {type: 'refusal'},
                    },
                },
                {troll: 'Troll', none: 'None'},
            ),
        ).toEqual([
            {
                id: 'criterion',
                value: 'Troll',
                confidence: '90%',
                probabilities: [
                    {key: 'troll', label: 'Troll', percent: 90},
                    {key: 'none', label: 'None', percent: 10},
                ],
            },
            {
                id: 'suitability',
                value: '1.2',
                confidence: null,
                probabilities: [
                    {key: '1', label: 'Level 1', percent: 70},
                    {key: '2', label: 'Level 2', percent: 20},
                ],
            },
            {id: 'other', value: 'Refused', confidence: null, probabilities: []},
        ]);
    });
});

describe('AI review helpers', () => {
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
