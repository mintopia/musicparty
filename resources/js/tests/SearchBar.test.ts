import {mount} from '@vue/test-utils';
import {afterEach, beforeEach, describe, expect, it, vi} from 'vitest';
import SearchBar from '../components/SearchBar.vue';

describe('SearchBar', () => {
    beforeEach(() => {
        vi.useFakeTimers();
    });
    afterEach(() => {
        vi.useRealTimers();
    });

    it('debounces model updates and submits immediately', async () => {
        const bar = mount(SearchBar, {props: {modelValue: ''}});
        const input = bar.find('input');
        await input.setValue('abba');
        await input.setValue('abba x');
        expect(bar.emitted('update:modelValue')).toBeUndefined();
        vi.advanceTimersByTime(300);
        expect(bar.emitted('update:modelValue')).toEqual([['abba x']]);

        await input.setValue('queen');
        await bar.find('form').trigger('submit');
        expect(bar.emitted('update:modelValue')?.at(-1)).toEqual(['queen']);
        expect(bar.emitted('submit')).toEqual([['queen']]);
        vi.advanceTimersByTime(300);
        expect(bar.emitted('update:modelValue')).toHaveLength(2);
    });

    it('renders count, chips with aria-pressed, hint and action slot', async () => {
        const bar = mount(SearchBar, {
            props: {
                modelValue: '',
                resultCount: 2,
                total: 42,
                noun: 'Requests',
                chips: [
                    {key: 'flagged', label: 'Flagged', count: 3, pressed: true},
                    {key: 'mine', label: 'Mine', pressed: false},
                ],
                hint: 'Try sent:no',
            },
            slots: {default: '<button class="act">Add rule</button>'},
        });
        expect(bar.find('[data-test="search-count"]').text()).toBe('2 of 42 Requests');
        const chips = bar.findAll('[data-test="search-chip"]');
        expect(chips.map((c) => c.attributes('aria-pressed'))).toEqual(['true', 'false']);
        await chips[1].trigger('click');
        expect(bar.emitted('toggle-chip')).toEqual([['mine']]);
        expect(bar.find('.mp-hint').text()).toBe('Try sent:no');
        expect(bar.find('.act').exists()).toBe(true);
    });
});
