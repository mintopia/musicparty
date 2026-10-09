import './bootstrap';
import '../css/app.css';
import {createApp, h} from 'vue';
import {createInertiaApp, router} from '@inertiajs/vue3';
import AppShell from './Layouts/AppShell.vue';

router.on('navigate', (event) => {
    window.siteName = event.detail.page.props.appName || window.siteName;
});

createInertiaApp({
    title: (title) => {
        const name = window.siteName || 'Music Party';
        return title ? `${title} - ${name}` : name;
    },
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
