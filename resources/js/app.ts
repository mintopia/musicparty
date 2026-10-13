import '~tabler/dist/js/tabler.min.js';
import '~tabler/dist/css/tabler.css';
import './bootstrap';
import '../sass/app.scss';
import '@tabler/icons-webfont/dist/tabler-icons.css';
import '../sass/tabler-icons-filled.scss';
import {createApp} from 'vue';
import Player from './components/Player.vue';

import.meta.glob(['../img/**', '../fonts/**'], {eager: true, query: '?url', import: 'default'});

const app = createApp({});

app.component('player', Player);

app.mount('#app');
