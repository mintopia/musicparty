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
import Next from './components/Next.vue'
import Upcoming from './components/Upcoming.vue'
import SearchResult from './components/SearchResult.vue'

const app = createApp()

app.component('player', Player)
app.component('next', Next)
app.component('upcoming', Upcoming)
app.component('search-result', SearchResult)

app.mount('#app')
