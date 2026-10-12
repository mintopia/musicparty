import {describe, expect, it} from 'vitest';
import {mount} from '@vue/test-utils';
import {readdirSync, readFileSync} from 'node:fs';
import {join} from 'node:path';
import SelectField from '../Components/ui/SelectField.vue';

const options = [{value: 'inter', label: 'Inter'}, {value: 'serif', label: 'Serif'}];

describe('SelectField', () => {
    it('shows the selected label and opens a listbox', async () => {
        const wrapper = mount(SelectField, {props: {modelValue: 'serif', options}});
        expect(wrapper.get('button').text()).toContain('Serif');
        expect(wrapper.find('[role=listbox]').exists()).toBe(false);
        await wrapper.get('button').trigger('click');
        expect(wrapper.findAll('[role=option]')).toHaveLength(2);
        expect(wrapper.findAll('[role=option]')[1].attributes('aria-selected')).toBe('true');
    });

    it('emits the chosen value and closes', async () => {
        const wrapper = mount(SelectField, {props: {modelValue: 'inter', options}});
        await wrapper.get('button').trigger('click');
        await wrapper.findAll('[role=option]')[1].trigger('mousedown');
        expect(wrapper.emitted('update:modelValue')[0]).toEqual(['serif']);
        expect(wrapper.find('[role=listbox]').exists()).toBe(false);
    });

    it('supports keyboard selection and escape', async () => {
        const wrapper = mount(SelectField, {props: {modelValue: 'inter', options}});
        const button = wrapper.get('button');
        await button.trigger('keydown', {key: 'ArrowDown'});
        await button.trigger('keydown', {key: 'ArrowDown'});
        await button.trigger('keydown', {key: 'Enter'});
        expect(wrapper.emitted('update:modelValue')[0]).toEqual(['serif']);
        await button.trigger('keydown', {key: 'ArrowDown'});
        await button.trigger('keydown', {key: 'Escape'});
        expect(wrapper.find('[role=listbox]').exists()).toBe(false);
    });

    it('does not open when disabled', async () => {
        const wrapper = mount(SelectField, {props: {modelValue: 'inter', options, disabled: true}});
        await wrapper.get('button').trigger('click');
        expect(wrapper.find('[role=listbox]').exists()).toBe(false);
    });
});

describe('admin pages', () => {
    it('use no native select elements', () => {
        const dir = join(__dirname, '../pages/Admin');
        const offenders = readdirSync(dir, {recursive: true})
            .filter((file) => String(file).endsWith('.vue'))
            .filter((file) => /<select[\s>]/.test(readFileSync(join(dir, String(file)), 'utf8')));
        expect(offenders).toEqual([]);
    });
});
