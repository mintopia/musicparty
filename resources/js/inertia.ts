import '../css/app.css';
import {createInertiaApp} from '@inertiajs/vue3';
import type {DefineComponent} from 'vue';

const pages = import.meta.glob<DefineComponent>('./pages/**/*.vue');

createInertiaApp({
    resolve: (name) => {
        const page = pages[`./pages/${name}.vue`];
        if (!page) {
            throw new Error(`Inertia page not found: ${name}`);
        }
        return page();
    },
});
