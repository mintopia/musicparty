import {inject, provide, ref, toValue, watchEffect} from 'vue';

const KEY = 'pageMeta';

export const providePageMeta = () => {
    const meta = ref({title: null, crumbs: []});
    provide(KEY, meta);
    return meta;
};

export const usePageMeta = (input) => {
    const meta = inject(KEY, null);
    if (!meta) {
        return;
    }
    watchEffect(() => {
        const value = toValue(input) ?? {};
        meta.value = {title: value.title ?? null, crumbs: value.crumbs ?? []};
    });
};
