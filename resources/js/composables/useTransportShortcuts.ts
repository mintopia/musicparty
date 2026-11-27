import {onBeforeUnmount, onMounted, toValue, type MaybeRefOrGetter} from 'vue';

export type TransportShortcuts = {
    toggle: () => void;
    next: () => void;
    previous: () => void;
};

function isTypingTarget(target: EventTarget | null): boolean {
    return (
        target instanceof HTMLElement && (target.isContentEditable || /^(INPUT|TEXTAREA|SELECT)$/.test(target.tagName))
    );
}

export function useTransportShortcuts(actions: TransportShortcuts, enabled: MaybeRefOrGetter<boolean> = true): void {
    let swallowSpaceUp = false;

    function onKeyDown(event: KeyboardEvent): void {
        if (!toValue(enabled) || event.ctrlKey || event.metaKey || event.altKey || isTypingTarget(event.target)) {
            return;
        }
        if (event.code === 'Space' || event.key === ' ') {
            event.preventDefault();
            swallowSpaceUp = true;
            if (!event.repeat) {
                actions.toggle();
            }
            return;
        }
        if (event.repeat) {
            return;
        }
        const key = event.key.toLowerCase();
        if (key === 'n') {
            actions.next();
        } else if (key === 'p') {
            actions.previous();
        }
    }

    function onKeyUp(event: KeyboardEvent): void {
        if (swallowSpaceUp && (event.code === 'Space' || event.key === ' ')) {
            swallowSpaceUp = false;
            event.preventDefault();
        }
    }

    onMounted(() => {
        window.addEventListener('keydown', onKeyDown);
        window.addEventListener('keyup', onKeyUp);
    });
    onBeforeUnmount(() => {
        window.removeEventListener('keydown', onKeyDown);
        window.removeEventListener('keyup', onKeyUp);
    });
}
