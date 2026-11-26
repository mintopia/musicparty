import {flushPromises, mount} from '@vue/test-utils';
import {afterEach, beforeEach, describe, expect, it, vi} from 'vitest';
import UserAutocompleteField from '@/components/UserAutocompleteField.vue';

const axiosGet = vi.hoisted(() => vi.fn());

vi.mock('axios', () => ({default: {get: axiosGet}}));

const users = [
    {id: 1, nickname: 'Ada'},
    {id: 2, nickname: 'Bob'},
];

function mountField(props: Record<string, unknown> = {}) {
    const wrapper = mount(UserAutocompleteField, {
        attachTo: document.body,
        props: {
            id: 'u',
            searchUrl: '/users',
            modelValue: null,
            'onUpdate:modelValue': (value: number | null) => void wrapper.setProps({modelValue: value}),
            ...props,
        },
    });
    return wrapper;
}

let w: ReturnType<typeof mountField>;

beforeEach(() => {
    vi.useFakeTimers();
    axiosGet.mockReset();
    axiosGet.mockResolvedValue({data: {data: users}});
});

afterEach(() => {
    w.unmount();
    vi.useRealTimers();
});

describe('UserAutocompleteField', () => {
    it('shows the chosen user when closed and a placeholder otherwise', () => {
        w = mountField({modelValue: 7, initialLabel: 'Zed'});
        expect(w.find('[data-test="user-selected"]').text()).toBe('Zed');
        expect(w.find('[data-test="user-menu"]').exists()).toBe(false);
        w.unmount();
        w = mountField();
        expect(w.find('[data-test="user-trigger"]').text()).toBe('Select a user');
        expect(w.find('[data-test="user-clear"]').exists()).toBe(false);
    });

    it('opens with a search input and the combobox/listbox roles', async () => {
        w = mountField();
        await w.find('[data-test="user-trigger"]').trigger('click');
        await flushPromises();
        const search = w.find('[data-test="user-search"]');
        expect(search.attributes('role')).toBe('combobox');
        expect(search.attributes('aria-controls')).toBe('u-listbox');
        expect(w.find('[role="listbox"]').exists()).toBe(true);
        expect(w.findAll('[role="option"]')).toHaveLength(2);
        expect(w.find('.dropdown-menu').exists()).toBe(true);
    });

    it('debounces typed searches by 250ms and sends the latest term', async () => {
        w = mountField();
        await w.find('[data-test="user-trigger"]').trigger('click');
        await flushPromises();
        axiosGet.mockClear();
        const search = w.find('[data-test="user-search"]');
        await search.setValue('a');
        vi.advanceTimersByTime(200);
        await search.setValue('ad');
        vi.advanceTimersByTime(200);
        expect(axiosGet).not.toHaveBeenCalled();
        vi.advanceTimersByTime(60);
        await flushPromises();
        expect(axiosGet).toHaveBeenCalledTimes(1);
        expect(axiosGet).toHaveBeenCalledWith('/users', {params: {q: 'ad'}});
    });

    it('shows loading, no-results and error states', async () => {
        w = mountField();
        axiosGet.mockReturnValueOnce(new Promise(() => undefined));
        await w.find('[data-test="user-trigger"]').trigger('click');
        expect(w.find('[data-test="user-loading"]').exists()).toBe(true);

        const search = w.find('[data-test="user-search"]');
        axiosGet.mockResolvedValueOnce({data: {data: []}});
        await search.setValue('zzz');
        vi.advanceTimersByTime(250);
        await flushPromises();
        expect(w.find('[data-test="user-empty"]').exists()).toBe(true);

        axiosGet.mockRejectedValueOnce(new Error('boom'));
        await search.setValue('err');
        vi.advanceTimersByTime(250);
        await flushPromises();
        expect(w.find('[data-test="user-error"]').exists()).toBe(true);
    });

    it('navigates with arrow keys, selects with Enter and closes with Escape', async () => {
        w = mountField();
        await w.find('[data-test="user-trigger"]').trigger('click');
        await flushPromises();
        const search = w.find('[data-test="user-search"]');
        expect(search.attributes('aria-activedescendant')).toBe('u-option-0');
        await search.trigger('keydown', {key: 'ArrowDown'});
        expect(search.attributes('aria-activedescendant')).toBe('u-option-1');
        await search.trigger('keydown', {key: 'ArrowDown'});
        expect(search.attributes('aria-activedescendant')).toBe('u-option-0');
        await search.trigger('keydown', {key: 'ArrowUp'});
        await search.trigger('keydown', {key: 'Enter'});
        expect(w.emitted('update:modelValue')?.[0]).toEqual([2]);
        await flushPromises();
        expect(w.find('[data-test="user-menu"]').exists()).toBe(false);
        expect(w.find('[data-test="user-selected"]').text()).toBe('Bob');

        await w.find('[data-test="user-trigger"]').trigger('click');
        await flushPromises();
        await w.find('[data-test="user-search"]').trigger('keydown', {key: 'Escape'});
        expect(w.find('[data-test="user-menu"]').exists()).toBe(false);
    });

    it('selects by click and clears the selection', async () => {
        w = mountField();
        await w.find('[data-test="user-trigger"]').trigger('click');
        await flushPromises();
        await w.findAll('[data-test="user-option"]')[0].trigger('mousedown');
        expect(w.emitted('update:modelValue')?.[0]).toEqual([1]);
        await flushPromises();
        await w.find('[data-test="user-clear"]').trigger('click');
        expect(w.emitted('update:modelValue')?.[1]).toEqual([null]);
        await flushPromises();
        expect(w.find('[data-test="user-trigger"]').text()).toBe('Select a user');
    });

    it('hides the clear button when the setting is required', () => {
        w = mountField({modelValue: 1, initialLabel: 'Ada', required: true});
        expect(w.find('[data-test="user-clear"]').exists()).toBe(false);
    });
});
