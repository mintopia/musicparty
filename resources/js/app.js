/**
 * First we will load all of this project's JavaScript dependencies which
 * includes Vue and other libraries. It is a great starting point when
 * building robust, powerful web applications using Vue and Laravel.
 */

require('./bootstrap');

import { createApp } from 'vue'
const app = createApp({})

const files = require.context('./', true, /\.vue$/i)
files.keys().map(key => app.component(key.split('/').pop().split('.')[0], files(key).default))

app.mount('#app')
