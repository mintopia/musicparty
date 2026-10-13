declare module 'lodash';
declare module '~tabler/dist/js/tabler.min.js';
declare module '@ts-pro/vue-eternal-loading' {
    import type {DefineComponent} from 'vue';

    export interface LoadAction {
        loaded: (loadedCount?: number, pageSize?: number) => void;
        noMore: () => void;
        error: () => void;
    }

    export const VueEternalLoading: DefineComponent<Record<string, unknown>>;
}
