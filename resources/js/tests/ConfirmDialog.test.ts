import {mount} from '@vue/test-utils';
import {describe, expect, it} from 'vitest';
import ConfirmDialog from '@/components/ConfirmDialog.vue';

const props = {modelValue: true, title: 'Remove?', message: 'Sure?', confirmLabel: 'Yes', cancelLabel: 'No'};

describe('ConfirmDialog', () => {
    it('renders nothing when closed', () => {
        expect(
            mount(ConfirmDialog, {props: {...props, modelValue: false}})
                .find('.modal')
                .exists(),
        ).toBe(false);
    });

    it('emits confirm and closes', async () => {
        const wrapper = mount(ConfirmDialog, {props});
        expect(wrapper.text()).toContain('Remove?');

        await wrapper.get('.btn-danger').trigger('click');

        expect(wrapper.emitted('confirm')).toHaveLength(1);
        expect(wrapper.emitted('update:modelValue')?.[0]).toEqual([false]);
    });

    it('emits cancel and closes', async () => {
        const wrapper = mount(ConfirmDialog, {props});

        await wrapper.get('.btn-link').trigger('click');

        expect(wrapper.emitted('cancel')).toHaveLength(1);
        expect(wrapper.emitted('confirm')).toBeUndefined();
        expect(wrapper.emitted('update:modelValue')?.[0]).toEqual([false]);
    });
});
