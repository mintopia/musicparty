import {mount} from '@vue/test-utils';
import {beforeEach, describe, expect, it, vi} from 'vitest';
import {reactive} from 'vue';
import PartyJoinForm from '@/components/PartyJoinForm.vue';

const inertia = vi.hoisted(() => ({posted: [] as {url: string; code: string}[]}));

vi.mock('@inertiajs/vue3', () => ({
    useForm: (initial: {code: string}) => {
        const form = reactive({
            ...initial,
            errors: {} as Record<string, string>,
            processing: false,
            clearErrors: () => undefined,
            post: (url: string) => inertia.posted.push({url, code: form.code}),
        });
        return form;
    },
}));
vi.mock('@/routes/parties', () => ({join: () => ({url: '/parties/join'})}));

describe('party join form', () => {
    beforeEach(() => {
        inertia.posted.length = 0;
    });

    it('uppercases what is typed', async () => {
        const wrapper = mount(PartyJoinForm, {props: {inputId: 'code'}});
        await wrapper.find('input').setValue('kxqb');
        expect((wrapper.find('input').element as HTMLInputElement).value).toBe('KXQB');
    });

    it('rejects digits with a plain message and does not submit', async () => {
        const wrapper = mount(PartyJoinForm, {props: {inputId: 'code'}});
        await wrapper.find('input').setValue('kx9b');
        await wrapper.find('form').trigger('submit');
        expect(wrapper.text()).toContain('Party codes are four letters, no digits.');
        expect(wrapper.find('input').classes()).toContain('is-invalid');
        expect(inertia.posted).toEqual([]);
    });

    it('rejects a short code', async () => {
        const wrapper = mount(PartyJoinForm, {props: {inputId: 'code'}});
        await wrapper.find('input').setValue('kxq');
        await wrapper.find('form').trigger('submit');
        expect(inertia.posted).toEqual([]);
    });

    it('posts four letters uppercased', async () => {
        const wrapper = mount(PartyJoinForm, {props: {inputId: 'code'}});
        await wrapper.find('input').setValue('kxqb');
        await wrapper.find('form').trigger('submit');
        expect(inertia.posted).toEqual([{url: '/parties/join', code: 'KXQB'}]);
    });
});
