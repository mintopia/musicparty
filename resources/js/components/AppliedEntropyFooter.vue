<script setup lang="ts">
import markDark from '@/../img/mark-small-reversed.svg';
import markLight from '@/../img/mark-small.svg';
</script>

<template>
    <div class="ae-footer__row">
        <a class="ae-footer__credit" href="https://appliedentropy.dev">
            <img class="ae-footer__mark ae-footer__mark--light" :src="markLight" alt="" width="20" height="24" />
            <img class="ae-footer__mark ae-footer__mark--dark" :src="markDark" alt="" width="20" height="24" />
            An Applied Entropy project
        </a>
        <ul class="ae-footer__links">
            <li><a href="https://github.com/mintopia/musicparty">Source</a></li>
        </ul>
        <span class="ae-footer__legal">&copy; 2026 &middot; MIT licence</span>
    </div>
</template>
