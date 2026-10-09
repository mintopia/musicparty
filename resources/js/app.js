import './bootstrap';
import '../css/app.css';
import {createApp, h} from 'vue';
import {createInertiaApp} from '@inertiajs/vue3';
import AppShell from './Layouts/AppShell.vue';

createInertiaApp({
    title: (title) => (title ? `${title} - Music Party` : 'Music Party'),
    resolve: (name) => {
        const pages = import.meta.glob('./pages/**/*.vue', {eager: true});
        const page = pages[`./pages/${name}.vue`];
        page.default.layout ??= AppShell;
        return page;
    },
    setup({el, App, props, plugin}) {
        createApp({render: () => h(App, props)}).use(plugin).mount(el);
    },
});
