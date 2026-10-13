import {mount} from '@vue/test-utils';
import {describe, expect, it} from 'vitest';
import ScheduleRequestControl from '../components/ScheduleRequestControl.vue';

describe('ScheduleRequestControl', () => {
    it('emits the chosen local time', async () => {
        const wrapper = mount(ScheduleRequestControl, {props: {modelValue: ''}});

        await wrapper.find('input[type="datetime-local"]').setValue('2030-01-01T20:00');

        expect(wrapper.emitted('update:modelValue')?.[0]).toEqual(['2030-01-01T20:00']);
    });
});
