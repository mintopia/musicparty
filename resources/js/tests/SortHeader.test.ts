import {mount} from '@vue/test-utils';
import {beforeEach, describe, expect, it} from 'vitest';
import {h} from 'vue';
import SortHeader from '@/components/SortHeader.vue';

const Link = {props: ['href'], setup: (p: {href: string}, {slots}: any) => () => h('a', {href: p.href}, slots.default?.())};

const render = (props: Record<string, unknown>) => mount(SortHeader, {props: {title: 'Name', field: 'name', ...props}, global: {stubs: {Link}}});

describe('SortHeader', () => {
    beforeEach(() => window.history.replaceState({}, '', '/admin/users?q=bob&page=3'));

    it('uses the default direction for an inactive field and keeps other params', () => {
        const w = render({order: 'id', orderDirection: 'asc'});
        const url = new URL(w.get('a').attributes('href')!, 'http://x');
        expect(url.pathname).toBe('/admin/users');
        expect(url.searchParams.get('q')).toBe('bob');
        expect(url.searchParams.get('order')).toBe('name');
        expect(url.searchParams.get('order_direction')).toBe('asc');
        expect(url.searchParams.has('page')).toBe(false);
        expect(w.find('i').exists()).toBe(false);
    });

    it('honours a custom default direction', () => {
        const w = render({direction: 'desc'});
        expect(w.get('a').attributes('href')).toContain('order_direction=desc');
    });

    it('toggles and shows caret on the active field', () => {
        const asc = render({order: 'name', orderDirection: 'asc'});
        expect(asc.get('a').attributes('href')).toContain('order_direction=desc');
        expect(asc.get('i').classes()).toEqual(expect.arrayContaining(['icon', 'ti', 'ti-caret-down-filled']));
        const desc = render({order: 'name', orderDirection: 'desc'});
        expect(desc.get('a').attributes('href')).toContain('order_direction=asc');
        expect(desc.get('i').classes()).toContain('ti-caret-up-filled');
    });
});
