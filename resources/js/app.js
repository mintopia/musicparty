import '~tabler/dist/js/tabler.min.js';
import './bootstrap';
import '../sass/app.scss';
import '../sass/tabler-icons.scss';
import.meta.glob([
    '../img/**',
    '../fonts/**',
]);
import { createApp } from 'vue'
import Player from './components/Player.vue'

const app = createApp()

app.component('player', Player)

app.mount('#app')
