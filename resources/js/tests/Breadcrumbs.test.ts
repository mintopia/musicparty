import {mount} from '@vue/test-utils';
import {describe, expect, it} from 'vitest';
import Breadcrumbs from '@/components/Breadcrumbs.vue';
import {adminUserCrumbs, moderationCrumbs, partyCrumbs} from '@/lib/breadcrumbs';

describe('Breadcrumbs', () => {
    it('links every item except the last, which is current-page text', () => {
        const wrapper = mount(Breadcrumbs, {
            props: {items: [{label: 'Home', href: '/'}, {label: 'Party', href: '/parties/ab'}, {label: 'Moderation'}]},
        });

        expect(wrapper.get('nav').attributes('aria-label')).toBe('Breadcrumb');
        const items = wrapper.findAll('.breadcrumb-item');
        expect(items.map((item) => item.text())).toEqual(['Home', 'Party', 'Moderation']);
        expect(items[1].get('a').attributes('href')).toBe('/parties/ab');
        expect(items[2].find('a').exists()).toBe(false);
        expect(items[2].classes()).toContain('active');
        expect(items[2].attributes('aria-current')).toBe('page');
    });

    it('builds crumbs from route helpers', () => {
        const party = {code: 'ab', name: 'Party'};

        expect(partyCrumbs(party).map((c) => c.href)).toEqual(['/', '/parties/ab']);
        expect(moderationCrumbs(party).at(-1)).toEqual({label: 'Moderation', href: '/parties/ab/moderation'});
        expect(adminUserCrumbs({id: 3, nickname: 'Sam'}).map((c) => c.href)).toEqual([
            '/',
            '/admin',
            '/admin/users',
            '/admin/users/3',
        ]);
    });
});
