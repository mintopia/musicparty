import {mount} from '@vue/test-utils';
import {describe, expect, it} from 'vitest';
import SearchSelectField from '@/components/SearchSelectField.vue';
import SearchTextField from '@/components/SearchTextField.vue';

describe('SearchTextField', () => {
    it('renders label, placeholder and v-model', async () => {
        const w = mount(SearchTextField, {props: {name: 'Title', property: 'title', modelValue: 'a'}});
        expect(w.get('label').text()).toBe('Title');
        expect(w.get('label').attributes('for')).toBe('title');
        const input = w.get('input.form-control');
        expect(input.attributes('placeholder')).toBe('Title');
        expect((input.element as HTMLInputElement).value).toBe('a');
        expect(w.find('.is-invalid').exists()).toBe(false);
        await input.setValue('b');
        expect(w.emitted('update:modelValue')![0]).toEqual(['b']);
    });

    it('shows the error', () => {
        const w = mount(SearchTextField, {props: {name: 'Title', property: 'title', error: 'Bad'}});
        expect(w.get('input').classes()).toContain('is-invalid');
        expect(w.get('.invalid-feedback').text()).toBe('Bad');
    });
});

describe('SearchSelectField', () => {
    const options = [
        {id: 1, label: 'One'},
        {id: 2, label: 'Two'},
    ];

    it('renders All plus options and emits selection', async () => {
        const w = mount(SearchSelectField, {
            props: {name: 'Kind', property: 'kind', options, valueKey: 'id', labelKey: 'label', modelValue: 2},
        });
        const opts = w.findAll('option');
        expect(opts.map((o) => o.text())).toEqual(['All', 'One', 'Two']);
        expect(opts[0].attributes('value')).toBe('');
        expect((w.get('select').element as HTMLSelectElement).value).toBe('2');
        await w.get('select').setValue('1');
        expect(w.emitted('update:modelValue')![0]).toEqual([1]);
    });

    it('shows the error', () => {
        const w = mount(SearchSelectField, {
            props: {name: 'Kind', property: 'kind', options, valueKey: 'id', labelKey: 'label', error: 'Bad'},
        });
        expect(w.get('select').classes()).toEqual(expect.arrayContaining(['form-select', 'is-invalid']));
        expect(w.get('.invalid-feedback').text()).toBe('Bad');
    });
});
