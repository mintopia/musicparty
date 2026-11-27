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
    {token: 'primary', label: 'Primary', default: '#0054a6'},
    {token: 'surface', label: 'Surface', default: '#ffffff'},
];
const theme = {
    id: 4,
    name: 'Custom',
    css: '',
    tokens: {primary: '#112233'},
    active: false,
    dark_mode: false,
    readonly: false,
    updated_at: '3 days ago',
};

beforeEach(() => {
    vi.clearAllMocks();
});

describe('admin themes', () => {
    it('shows unset colours as Default, never black, and posts the create form', async () => {
        const wrapper = mount(ThemeCreate, {props: {theme: {...theme, name: '', tokens: {}}, colourTokens}});

        expect(wrapper.findAll('input[type=color]')).toHaveLength(2);
        expect(wrapper.findAll('.mp-tag').map((tag) => tag.text())).toEqual(['Default', 'Default']);
        expect(wrapper.find('.mp-swatch').classes()).toContain('is-unset');
        expect((wrapper.find('input[type=color]').element as HTMLInputElement).value).toBe('#0054a6');
        expect(wrapper.find('button[title="Back to the default colour"]').exists()).toBe(false);

        await wrapper.find('input[name="tokens[primary]"]').setValue('#abcdef');
        expect((wrapper.find('input[type=color]').element as HTMLInputElement).value).toBe('#abcdef');
        expect(wrapper.find('.mp-swatch').classes()).not.toContain('is-unset');
        await wrapper.find('form').trigger('submit');
        expect(inertia.post).toHaveBeenCalledWith('/admin/themes');
    });

    it('patches the edit form and disables fields for readonly themes', async () => {
        const wrapper = mount(ThemeEdit, {props: {theme: {...theme, readonly: true}, colourTokens}});

        expect(wrapper.text()).toContain('It is not possible to edit this theme');
        expect(wrapper.find('input[name="name"]').attributes('disabled')).toBeDefined();
        expect(wrapper.find('input[name="active"]').attributes('disabled')).toBeUndefined();
        await wrapper.find('form').trigger('submit');
        expect(inertia.patch).toHaveBeenCalledWith('/admin/themes/4');
    });

    it('says when an active theme went live', () => {
        const wrapper = mount(ThemeEdit, {props: {theme: {...theme, active: true}, colourTokens}});
        expect(wrapper.get('.mp-page-head p').text()).toBe('Active since 3 days ago.');
    });

    it('resets a set colour back to unset', async () => {
        const wrapper = mount(ThemeEdit, {props: {theme, colourTokens}});

        await wrapper.get('button[title="Back to the default colour"]').trigger('click');
        expect((wrapper.get('input[name="tokens[primary]"]').element as HTMLInputElement).value).toBe('');
        expect(wrapper.findAll('.mp-tag')).toHaveLength(2);
    });

    it('shows an inline error and a count for an invalid colour', async () => {
        const wrapper = mount(ThemeEdit, {props: {theme, colourTokens}});

        await wrapper.get('input[name="tokens[primary]"]').setValue('nope');
        expect(wrapper.get('.invalid-feedback').text()).toContain('That is not a colour');
        expect(wrapper.get('[data-test=colour-problems]').text()).toContain('1 colour needs fixing');
    });

    it('previews with default colours and switches between light and dark', async () => {
        const wrapper = mount(ThemeEdit, {props: {theme, colourTokens}});
        const preview = (): string => wrapper.get('.pv').attributes('style') ?? '';

        expect(preview()).toContain('--pv-primary: #112233');
        expect(preview()).toContain('--pv-surf: #ffffff');
        await wrapper.findAll('.mp-chip')[1].trigger('click');
        expect(preview()).not.toContain('--pv-surf: #ffffff');
        expect(wrapper.findAll('.mp-chip')[1].attributes('aria-pressed')).toBe('true');
    });

    it('names the theme on the delete confirmation and submits', async () => {
        const wrapper = mount(ThemeDelete, {
            props: {theme: {id: 4, name: 'Custom', active: true, dark_mode: true, tokens: {primary: '#8a2be2'}}},
        });

        expect(wrapper.get('button.btn-danger').text()).toBe('Delete Custom');
        expect(wrapper.get('.mp-thumb').attributes('style')).toContain('--t-pri: #8a2be2');
        expect(wrapper.get('.mp-thumb').attributes('style')).toContain('--t-bg: #101820');
        expect(wrapper.find('[data-test=delete-active-note]').exists()).toBe(true);
        await wrapper.find('form').trigger('submit');
        expect(inertia.delete).toHaveBeenCalledWith('/admin/themes/4');
    });
});
