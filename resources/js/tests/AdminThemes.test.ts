import {mount} from '@vue/test-utils';
import {beforeEach, describe, expect, it, vi} from 'vitest';
import ThemeCreate from '@/pages/Admin/Themes/Create.vue';
import ThemeDelete from '@/pages/Admin/Themes/Delete.vue';
import ThemeEdit from '@/pages/Admin/Themes/Edit.vue';

const inertia = vi.hoisted(() => ({post: vi.fn(), patch: vi.fn(), delete: vi.fn()}));

vi.mock('@inertiajs/vue3', async () => {
    const {reactive} = await import('vue');
    return {
        Head: {render: () => null},
        Link: {props: ['href'], template: '<a :href="href"><slot /></a>'},
        useForm: (data: Record<string, unknown>) =>
            reactive({
                ...data,
                errors: {},
                processing: false,
                post: inertia.post,
                patch: inertia.patch,
                delete: inertia.delete,
            }),
    };
});

vi.mock('@/layouts/AppLayout.vue', () => ({
    default: {template: '<div><slot name="breadcrumbs" /><slot /></div>'},
}));

const colourTokens = [
    {token: 'primary', label: 'Primary', required: true},
    {token: 'surface', label: 'Surface', required: false},
];
const theme = {
    id: 4,
    name: 'Custom',
    css: '',
    tokens: {primary: '#112233'},
    active: false,
    dark_mode: false,
    readonly: false,
};

beforeEach(() => {
    vi.clearAllMocks();
});

describe('admin themes', () => {
    it('posts the create form and links colour picker to text input', async () => {
        const wrapper = mount(ThemeCreate, {props: {theme: {...theme, name: '', tokens: {}}, colourTokens}});

        expect(wrapper.findAll('input[type=color]')).toHaveLength(2);
        await wrapper.find('input[name="tokens[primary]"]').setValue('#abcdef');
        expect((wrapper.find('input[type=color]').element as HTMLInputElement).value).toBe('#abcdef');
        await wrapper.find('form').trigger('submit');
        expect(inertia.post).toHaveBeenCalledWith('/admin/settings/themes');
    });

    it('patches the edit form and disables fields for readonly themes', async () => {
        const wrapper = mount(ThemeEdit, {props: {theme: {...theme, readonly: true}, colourTokens}});

        expect(wrapper.text()).toContain('It is not possible to edit this theme');
        expect(wrapper.find('input[name="name"]').attributes('disabled')).toBeDefined();
        expect(wrapper.find('input[name="active"]').attributes('disabled')).toBeUndefined();
        await wrapper.find('form').trigger('submit');
        expect(inertia.patch).toHaveBeenCalledWith('/admin/settings/themes/4');
    });

    it('submits the delete form', async () => {
        const wrapper = mount(ThemeDelete, {props: {theme: {id: 4, name: 'Custom'}}});
        await wrapper.find('form').trigger('submit');
        expect(inertia.delete).toHaveBeenCalledWith('/admin/settings/themes/4');
    });
});
