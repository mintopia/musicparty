import {mount} from '@vue/test-utils';
import {afterEach, describe, expect, it, vi} from 'vitest';
import {defineComponent, h, ref} from 'vue';
import {useTransportShortcuts} from '@/composables/useTransportShortcuts';

const actions = {toggle: vi.fn(), next: vi.fn(), previous: vi.fn()};
const enabled = ref(true);
const mounted: Array<{unmount: () => void}> = [];

function mountHost() {
    const wrapper = mount(
        defineComponent({
            setup() {
                useTransportShortcuts(actions, enabled);

                return () => h('div', [h('input'), h('textarea'), h('select'), h('div', {contenteditable: 'true'})]);
            },
        }),
        {attachTo: document.body},
    );
    mounted.push(wrapper);

    return wrapper;
}

function press(init: KeyboardEventInit, target: EventTarget = document.body): KeyboardEvent {
    const event = new KeyboardEvent('keydown', {bubbles: true, cancelable: true, ...init});
    target.dispatchEvent(event);

    return event;
}

describe('useTransportShortcuts', () => {
    afterEach(() => {
        mounted.splice(0).forEach((wrapper) => wrapper.unmount());
        enabled.value = true;
        vi.clearAllMocks();
    });

    it('maps Space, N and P to the actions and prevents the Space scroll', () => {
        mountHost();

        expect(press({key: ' ', code: 'Space'}).defaultPrevented).toBe(true);
        press({key: 'n', code: 'KeyN'});
        press({key: 'P', code: 'KeyP'});

        expect(actions.toggle).toHaveBeenCalledTimes(1);
        expect(actions.next).toHaveBeenCalledTimes(1);
        expect(actions.previous).toHaveBeenCalledTimes(1);
    });

    it('ignores typing targets and modifier combinations', () => {
        const wrapper = mountHost();
        const targets = ['input', 'textarea', 'select', '[contenteditable]'].map((selector) => {
            const element = wrapper.get(selector).element as HTMLElement;
            Object.defineProperty(element, 'isContentEditable', {value: selector === '[contenteditable]'});

            return element;
        });

        for (const element of targets) {
            expect(press({key: ' ', code: 'Space'}, element).defaultPrevented).toBe(false);
            press({key: 'n'}, element);
        }
        for (const modifier of ['ctrlKey', 'metaKey', 'altKey']) {
            press({key: 'n', [modifier]: true});
            expect(press({key: ' ', code: 'Space', [modifier]: true}).defaultPrevented).toBe(false);
        }

        expect(actions.toggle).not.toHaveBeenCalled();
        expect(actions.next).not.toHaveBeenCalled();
    });

    it('does nothing while disabled or after unmount, and ignores key repeat', () => {
        const wrapper = mountHost();
        enabled.value = false;
        press({key: 'n'});
        expect(actions.next).not.toHaveBeenCalled();

        enabled.value = true;
        press({key: 'n', repeat: true});
        press({key: ' ', code: 'Space', repeat: true});
        expect(actions.next).not.toHaveBeenCalled();
        expect(actions.toggle).not.toHaveBeenCalled();

        wrapper.unmount();
        press({key: 'n'});
        expect(actions.next).not.toHaveBeenCalled();
    });
});
