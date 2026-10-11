import {beforeEach, describe, expect, it, vi} from 'vitest';
import {mount} from '@vue/test-utils';
import {h, reactive} from 'vue';

const page = vi.hoisted(() => ({props: {}, url: '/'}));
const router = vi.hoisted(() => ({get: vi.fn(), post: vi.fn(), delete: vi.fn()}));
const forms = vi.hoisted(() => []);

vi.mock('@inertiajs/vue3', () => ({
    usePage: () => page,
    router,
    useForm: (data) => {
        const form = reactive({...data, errors: {}, processing: false, patch: vi.fn(), delete: vi.fn()});
        forms.push(form);
        return form;
    },
    Head: {render: () => null},
    Link: {props: ['href'], render() { return h('a', {href: this.href}, this.$slots.default?.()); }},
}));

import UsersShow from '../pages/Admin/Users/Show.vue';
import UsersEdit from '../pages/Admin/Users/Edit.vue';
import UsersDelete from '../pages/Admin/Users/Delete.vue';
import AccountDelete from '../pages/Admin/LinkedAccounts/Delete.vue';

const user = (extra = {}) => ({
    id: 5,
    nickname: 'Target',
    email: 'target@example.com',
    suspended: false,
    completed_signup: true,
    agreed_terms: true,
    last_login: null,
    created_at: '2024-01-01T00:00:00Z',
    roles: [{code: 'create-party', label: 'Party creator'}],
    accounts: [
        {id: 1, provider: 'Discord', name: 'tgt', email: 'a@b.c', avatar_url: null, external_id: '123', can_delete: true},
        {id: 2, provider: 'Spotify', name: 'tgt2', email: null, avatar_url: null, external_id: '456', can_delete: false},
    ],
    ...extra,
});

beforeEach(() => {
    router.get.mockReset();
    router.post.mockReset();
    router.delete.mockReset();
    forms.length = 0;
    page.props = {auth: {user: {id: 1, is_admin: true}}, errors: {}};
    page.url = '/admin/users/5';
});

describe('Admin user show', () => {
    it('renders details, pills and linked accounts', () => {
        const wrapper = mount(UsersShow, {props: {user: user()}});

        expect(wrapper.text()).toContain('Target');
        expect(wrapper.find('a[href="mailto:target@example.com"]').exists()).toBe(true);
        expect(wrapper.text()).toContain('Party creator');
        expect(wrapper.find('[data-pill="Suspended"]').text()).toBe('No');
        expect(wrapper.find('[data-pill="Completed signup?"]').text()).toBe('Yes');
        expect(wrapper.find('[data-action="edit"]').attributes('href')).toBe('/admin/users/5/edit');
        expect(wrapper.find('[data-action="delete"]').attributes('href')).toBe('/admin/users/5/delete');
        expect(wrapper.find('[data-testid="account-1"] a[data-action="delete-account"]').attributes('href')).toBe('/admin/users/5/accounts/1/delete');
        expect(wrapper.find('[data-testid="account-2"] button[data-action="delete-account"]').attributes('disabled')).toBeDefined();
    });

    it('impersonates', async () => {
        const wrapper = mount(UsersShow, {props: {user: user()}});
        await wrapper.find('[data-action="impersonate"]').trigger('click');

        expect(router.post).toHaveBeenCalledWith('/admin/users/5/impersonate');
    });

    it.each([
        ['suspended', {suspended: true}],
        ['admin', {roles: [{code: 'admin', label: 'Administrator'}]}],
        ['self', {id: 1}],
    ])('disables impersonation for a %s user', (_name, extra) => {
        const wrapper = mount(UsersShow, {props: {user: user(extra)}});

        expect(wrapper.find('[data-action="impersonate"]').attributes('disabled')).toBeDefined();
    });

    it('warns when the user is suspended', () => {
        const wrapper = mount(UsersShow, {props: {user: user({suspended: true})}});

        expect(wrapper.text()).toContain('This user is suspended.');
        expect(wrapper.find('[data-pill="Suspended"]').text()).toBe('Yes');
    });
});

describe('Admin user edit', () => {
    const roleOptions = [{code: 'admin', label: 'Administrator'}, {code: 'create-party', label: 'Party creator'}];

    it('prefills and submits via patch', async () => {
        const wrapper = mount(UsersEdit, {props: {user: user(), roleOptions}});
        expect(wrapper.find('#nickname').element.value).toBe('Target');
        expect(wrapper.find('[data-role="create-party"]').element.checked).toBe(true);

        await wrapper.find('[data-role="admin"]').setValue(true);
        await wrapper.find('form').trigger('submit');

        expect(forms[0].roles).toEqual(['create-party', 'admin']);
        expect(forms[0].patch).toHaveBeenCalledWith('/admin/users/5');
    });

    it('does not let an admin suspend themselves', () => {
        const wrapper = mount(UsersEdit, {props: {user: user({id: 1}), roleOptions}});

        expect(wrapper.find('button[aria-label="Suspended"]').attributes('disabled')).toBeDefined();
    });
});

describe('Admin user delete', () => {
    it('lists cascading parties and requires typing delete', async () => {
        const wrapper = mount(UsersDelete, {props: {user: {id: 5, nickname: 'Target'}, parties: [{id: 9, name: 'Doomed', code: 'ABCD'}]}});

        expect(wrapper.find('[data-testid="cascade-parties"]').text()).toContain('Doomed');
        const button = wrapper.find('[data-action="confirm-delete"]');
        expect(button.attributes('disabled')).toBeDefined();

        await wrapper.find('#confirm').setValue('delete');
        expect(wrapper.find('[data-action="confirm-delete"]').attributes('disabled')).toBeUndefined();

        await wrapper.find('form').trigger('submit');
        expect(forms[0].delete).toHaveBeenCalledWith('/admin/users/5');
    });

    it('hides the party list when there are none', () => {
        const wrapper = mount(UsersDelete, {props: {user: {id: 5, nickname: 'Target'}, parties: []}});

        expect(wrapper.find('[data-testid="cascade-parties"]').exists()).toBe(false);
    });
});

describe('Admin linked account delete', () => {
    it('confirms and deletes', async () => {
        const wrapper = mount(AccountDelete, {props: {
            user: {id: 5, nickname: 'Target'},
            account: {id: 1, provider: 'Discord', name: 'tgt', email: null, external_id: '123'},
        }});

        expect(wrapper.text()).toContain('Discord');
        await wrapper.find('form').trigger('submit');

        expect(forms[0].delete).toHaveBeenCalledWith('/admin/users/5/accounts/1');
    });
});
