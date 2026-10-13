import '~tabler/dist/js/tabler.min.js';
import '~tabler/dist/css/tabler.css';
import './bootstrap';
import '../sass/app.scss';
import '@tabler/icons-webfont/dist/tabler-icons.css';
import '../sass/tabler-icons-filled.scss';
import {createApp} from 'vue'
import Player from './components/Player.vue'
import Next from './components/Next.vue'
import Upcoming from './components/Upcoming.vue'
import SearchResult from './components/SearchResult.vue'
import TvPlayer from './components/TvPlayer.vue'
import YouTubePlayer from './components/YouTubePlayer.vue'

import.meta.glob([
    '../img/**',
    '../fonts/**',
], {eager: true, query: '?url', import: 'default'});

const app = createApp()

app.component('player', Player)
app.component('next', Next)
app.component('upcoming', Upcoming)
app.component('search-result', SearchResult)
app.component('tv-player', TvPlayer)
app.component('youtube-player', YouTubePlayer)

app.mount('#app')
