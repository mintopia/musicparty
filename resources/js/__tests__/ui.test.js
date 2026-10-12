import {beforeEach, describe, expect, it, vi} from 'vitest';
import {mount} from '@vue/test-utils';
import {h} from 'vue';

const page = vi.hoisted(() => ({props: {}, url: '/admin/users?order=nickname&order_direction=asc&page=2'}));
const router = vi.hoisted(() => ({get: vi.fn()}));

vi.mock('@inertiajs/vue3', () => ({
    usePage: () => page,
    router,
    Link: {props: ['href'], render() { return h('a', {href: this.href}, this.$slots.default?.()); }},
}));

import Alert from '../Components/ui/Alert.vue';
import Breadcrumbs from '../Components/ui/Breadcrumbs.vue';
import Button from '../Components/ui/Button.vue';
import Card from '../Components/ui/Card.vue';
import DataTable from '../Components/ui/DataTable.vue';
import FlashAlerts from '../Components/ui/FlashAlerts.vue';
import Pagination from '../Components/ui/Pagination.vue';
import RelativeTime from '../Components/ui/RelativeTime.vue';
import SearchCard from '../Components/ui/SearchCard.vue';
import StatusPill from '../Components/ui/StatusPill.vue';
import Toggle from '../Components/ui/Toggle.vue';
import {relativeTime} from '../lib/format.js';

beforeEach(() => {
    router.get.mockReset();
    page.props = {};
    page.url = '/admin/users?order=nickname&order_direction=asc&page=2';
});

describe('relativeTime', () => {
    const now = Date.parse('2026-10-11T12:00:00Z');
    it.each([
        ['2026-10-11T11:18:00Z', '42 minutes ago'],
        ['2026-10-11T11:59:59Z', 'just now'],
        ['2026-10-10T12:00:00Z', '1 day ago'],
        ['2026-09-20T12:00:00Z', '3 weeks ago'],
        ['2024-10-11T12:00:00Z', '2 years ago'],
        [null, ''],
    ])('%s -> %s', (iso, expected) => {
        expect(relativeTime(iso, now)).toBe(expected);
    });
});

describe('Card', () => {
    it('renders header, actions, body and footer slots', () => {
        const wrapper = mount(Card, {slots: {header: 'Title', actions: '<b>act</b>', default: 'Body', footer: 'Foot'}});
        expect(wrapper.find('h2').text()).toBe('Title');
        expect(wrapper.text()).toContain('act');
        expect(wrapper.find('footer').text()).toBe('Foot');
    });

    it('omits header and footer when no slots and flush drops padding', () => {
        const wrapper = mount(Card, {props: {flush: true}, slots: {default: 'Body'}});
        expect(wrapper.find('header').exists()).toBe(false);
        expect(wrapper.find('footer').exists()).toBe(false);
        expect(wrapper.find('.p-5').exists()).toBe(false);
    });

    it('shows a danger stripe', () => {
        expect(mount(Card, {props: {status: 'danger'}}).classes()).toContain('border-t-danger');
    });
});

describe('Button and StatusPill', () => {
    it('applies variant classes and disabled', () => {
        const wrapper = mount(Button, {props: {variant: 'outline-danger', disabled: true}, slots: {default: 'Delete'}});
        expect(wrapper.classes()).toContain('text-danger');
        expect(wrapper.attributes('disabled')).toBeDefined();
    });

    it('maps pill variants', () => {
        expect(mount(StatusPill, {props: {variant: 'green'}}).classes()).toContain('text-accent');
        expect(mount(StatusPill, {props: {variant: 'red'}}).classes()).toContain('text-danger');
        expect(mount(StatusPill, {props: {variant: 'nope'}}).classes()).toContain('text-muted');
    });
});

describe('Toggle', () => {
    it('is a switch that emits the inverted value', async () => {
        const wrapper = mount(Toggle, {props: {modelValue: false, label: 'Enabled'}});
        const control = wrapper.get('[role="switch"]');
        expect(control.attributes('aria-checked')).toBe('false');
        await control.trigger('click');
        expect(wrapper.emitted('update:modelValue')[0]).toEqual([true]);
    });
});

describe('Alerts', () => {
    it('uses alert role for danger and warning only', () => {
        expect(mount(Alert, {props: {variant: 'danger'}}).attributes('role')).toBe('alert');
        expect(mount(Alert, {props: {variant: 'success'}}).attributes('role')).toBe('status');
    });

    it('renders only the flash messages that exist', () => {
        page.props = {flash: {success: 'Saved', error: null, info: 'FYI', warning: undefined}};
        const wrapper = mount(FlashAlerts);
        expect(wrapper.findAll('[data-variant]').map((a) => a.attributes('data-variant'))).toEqual(['success', 'info']);
    });

    it('renders each of the four kinds once', () => {
        page.props = {flash: {success: 'a', error: 'b', info: 'c', warning: 'd'}};
        const wrapper = mount(FlashAlerts);
        expect(wrapper.findAll('[data-variant]').map((a) => a.attributes('data-variant')).sort()).toEqual(['danger', 'info', 'success', 'warning']);
        expect(wrapper.findAll('[data-variant]')).toHaveLength(4);
    });

    it('renders nothing without flash', () => {
        page.props = {flash: {}};
        expect(mount(FlashAlerts).find('[data-testid="flash-alerts"]').exists()).toBe(false);
    });
});

describe('Breadcrumbs', () => {
    it('links all but the last crumb', () => {
        const wrapper = mount(Breadcrumbs, {props: {crumbs: [{label: 'Home', href: '/'}, {label: 'Admin', href: '/admin'}, {label: 'Users', href: '/admin/users'}]}});
        expect(wrapper.findAll('a')).toHaveLength(2);
        expect(wrapper.find('[aria-current="page"]').text()).toBe('Users');
    });
});

describe('RelativeTime', () => {
    it('shows relative text with an absolute title, or the empty label', () => {
        const wrapper = mount(RelativeTime, {props: {value: '2020-01-01T00:00:00Z'}});
        expect(wrapper.text()).toMatch(/years? ago/);
        expect(wrapper.get('time').attributes('title')).not.toBe('');
        expect(mount(RelativeTime, {props: {value: null}}).text()).toBe('Never');
    });
});

describe('DataTable', () => {
    const columns = [{key: 'id', label: 'ID', sortable: true}, {key: 'nickname', label: 'Nickname', sortable: true}, {key: 'x', label: 'Plain'}];

    it('builds sort links toggling direction and dropping page', () => {
        const wrapper = mount(DataTable, {props: {columns, rows: []}});
        expect(wrapper.get('[data-sort="nickname"]').attributes('href')).toBe('/admin/users?order=nickname&order_direction=desc');
        expect(wrapper.get('[data-sort="id"]').attributes('href')).toBe('/admin/users?order=id&order_direction=asc');
        expect(wrapper.find('[data-sort="x"]').exists()).toBe(false);
    });

    it('renders rows through the row slot and an empty state', () => {
        const rows = [{id: 1, nickname: 'Ann'}];
        const wrapper = mount(DataTable, {
            props: {columns, rows, emptyText: 'Nothing'},
            slots: {row: '<template #row="{row}"><td>{{ row.nickname }}</td></template>'},
        });
        expect(wrapper.text()).toContain('Ann');
        expect(mount(DataTable, {props: {columns, rows: [], emptyText: 'Nothing'}}).text()).toContain('Nothing');
    });
});

describe('Pagination', () => {
    const laravelMeta = {
        data: [{}, {}],
        meta: {
            from: 21,
            to: 40,
            total: 59,
            last_page: 3,
            links: [
                {url: '/p?page=1', label: '&laquo; Previous', active: false},
                {url: '/p?page=1', label: '1', active: false},
                {url: null, label: '2', active: true},
                {url: '/p?page=3', label: 'Next &raquo;', active: false},
            ],
        },
    };

    it('shows the summary and marks the current page', () => {
        const wrapper = mount(Pagination, {props: {paginator: laravelMeta}});
        expect(wrapper.get('[data-testid="pagination-summary"]').text()).toBe('Showing 21 to 40 of 59 entries');
        expect(wrapper.findAll('nav a')).toHaveLength(3);
        expect(wrapper.find('nav a[aria-label="Previous"]').text()).toBe('\u2039');
        expect(wrapper.find('nav a[aria-label="Next"]').text()).toBe('\u203A');
        expect(wrapper.find('nav [aria-current="page"]').text()).toBe('2');
    });

    it('accepts the flat paginator shape and hides nav for one page', () => {
        const wrapper = mount(Pagination, {props: {paginator: {data: [{}], total: 1, last_page: 1, links: []}}});
        expect(wrapper.text()).toContain('Showing 1 to 1 of 1 entries');
        expect(wrapper.find('nav').exists()).toBe(false);
    });
});

describe('SearchCard', () => {
    it('submits non-empty fields preserving sort', async () => {
        const wrapper = mount(SearchCard, {props: {fields: [{name: 'id', label: 'ID'}, {name: 'nickname', label: 'Nickname'}], filters: {nickname: 'an'}}});
        await wrapper.get('#search-id').setValue('7');
        await wrapper.get('form').trigger('submit');
        expect(router.get).toHaveBeenCalledWith('/admin/users', {order: 'nickname', order_direction: 'asc', id: '7', nickname: 'an'}, {preserveState: true});
    });

    it('omits empty fields', async () => {
        page.url = '/admin/users';
        const wrapper = mount(SearchCard, {props: {fields: [{name: 'id', label: 'ID'}]}});
        await wrapper.get('form').trigger('submit');
        expect(router.get).toHaveBeenCalledWith('/admin/users', {}, {preserveState: true});
    });
});
