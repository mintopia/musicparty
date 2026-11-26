<script setup lang="ts">
import {usePage} from '@inertiajs/vue3';
import {computed} from 'vue';
import markDark from '@/../img/mark-small-reversed.svg';
import markLight from '@/../img/mark-small.svg';
import type {SharedProps} from '@/types';

const site = computed(() => usePage<SharedProps>().props.site);
</script>

<template>
    <footer class="ae-footer d-print-none">
        <div class="ae-footer__row">
            <a class="ae-footer__credit" href="https://appliedentropy.dev">
                <img class="ae-footer__mark ae-footer__mark--light" :src="markLight" alt="" width="20" height="24" />
                <img class="ae-footer__mark ae-footer__mark--dark" :src="markDark" alt="" width="20" height="24" />
                An Applied Entropy project
            </a>
            <ul class="ae-footer__links">
                <li v-if="site.terms">
                    <a :href="site.terms" target="_blank" rel="noopener">Terms and Conditions</a>
                </li>
                <li v-if="site.privacyPolicy">
                    <a :href="site.privacyPolicy" target="_blank" rel="noopener">Privacy Policy</a>
                </li>
                <li><a href="https://github.com/mintopia/musicparty">Source</a></li>
            </ul>
            <span class="ae-footer__legal">&copy; 2026 &middot; MIT</span>
        </div>
    </footer>
</template>
