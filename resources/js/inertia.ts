import '~tabler/dist/css/tabler.css';
import '../css/app.css';
import '../sass/app.scss';
import '@tabler/icons-webfont/dist/tabler-icons.css';
import '../sass/tabler-icons-filled.scss';
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
