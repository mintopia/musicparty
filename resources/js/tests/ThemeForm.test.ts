import {mount} from '@vue/test-utils';
import {reactive} from 'vue';
import {describe, expect, it} from 'vitest';
import ThemeForm from '@/components/ThemeForm.vue';

function mountForm(css: string, readonly = false) {
    const form = reactive({name: 'T', css, active: false, dark_mode: false, tokens: {}, errors: {} as Record<string, string>});
    const wrapper = mount(ThemeForm, {props: {form, readonly, colourTokens: []}, attachTo: document.body});
    return {form, wrapper};
}

describe('ThemeForm custom css editor', () => {
    it('renders a labelled, highlighted editor bound to the form value', async () => {
        const {form, wrapper} = mountForm('a { color: red; }');
        const textarea = wrapper.get('textarea.pce-textarea');

        expect(textarea.attributes('id')).toBe('css');
        expect(textarea.attributes('name')).toBe('css');
        expect(textarea.attributes('aria-describedby')).toBe('css-hint');
        expect(wrapper.find('label[for=css]').exists()).toBe(true);
        expect(wrapper.find('.token.property').exists()).toBe(true);

        (textarea.element as HTMLTextAreaElement).value = 'b { margin: 0; }';
        await textarea.trigger('input');
        expect(form.css).toBe('b { margin: 0; }');

        form.css = 'c {}';
        await wrapper.vm.$nextTick();
        expect((textarea.element as HTMLTextAreaElement).value).toBe('c {}');
        wrapper.unmount();
    });

    it('is read only when the theme is read only', () => {
        const {wrapper} = mountForm('a{}', true);
        expect(wrapper.get('textarea.pce-textarea').attributes('aria-readonly')).toBe('true');
        wrapper.unmount();
    });
});
