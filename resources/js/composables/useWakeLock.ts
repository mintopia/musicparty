import {onBeforeUnmount, onMounted} from 'vue';

export function useWakeLock(): void {
    let sentinel: WakeLockSentinel | null = null;
    let active = false;

    async function request(): Promise<void> {
        if (!active || !('wakeLock' in navigator) || document.visibilityState !== 'visible' || sentinel !== null) {
            return;
        }
        try {
            const acquired = await navigator.wakeLock.request('screen');
            if (!active) {
                void acquired.release();

                return;
            }
            sentinel = acquired;
            acquired.addEventListener('release', () => {
                if (sentinel === acquired) {
                    sentinel = null;
                }
            });
        } catch {
            sentinel = null;
        }
    }

    function onVisibility(): void {
        if (document.visibilityState === 'visible') {
            void request();
        }
    }

    onMounted(() => {
        active = true;
        document.addEventListener('visibilitychange', onVisibility);
        void request();
    });

    onBeforeUnmount(() => {
        active = false;
        document.removeEventListener('visibilitychange', onVisibility);
        void sentinel?.release();
        sentinel = null;
    });
}
