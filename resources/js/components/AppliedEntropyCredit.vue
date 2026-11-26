<script setup lang="ts">
import markDark from '@/../img/mark-small-reversed.svg';
import markLight from '@/../img/mark-small.svg';
</script>

<template>
    <a class="ae-credit" href="https://appliedentropy.dev">
        <img class="ae-credit__mark ae-credit__mark--light" :src="markLight" alt="" width="20" height="24" />
        <img class="ae-credit__mark ae-credit__mark--dark" :src="markDark" alt="" width="20" height="24" />
        An Applied Entropy project
    </a>
</template>
