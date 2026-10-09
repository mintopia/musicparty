<script setup>
const year = new Date().getFullYear();
</script>

<template>
    <footer data-testid="footer" class="px-4 py-6 text-sm text-muted">
        Copyright &copy; {{ year }} Music Party. All rights reserved
        &middot; Made with <span class="text-danger">&hearts;</span> by Mintopia
        &middot; <a href="https://github.com/mintopia/musicparty" class="hover:text-text">GitHub</a>
    </footer>
</template>
