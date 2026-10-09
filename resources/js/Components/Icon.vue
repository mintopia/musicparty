<script setup>
const paths = {
    home: ['M5 12l-2 0l9 -9l9 9l-2 0', 'M5 12v7a2 2 0 0 0 2 2h10a2 2 0 0 0 2 -2v-7', 'M9 21v-6a2 2 0 0 1 2 -2h2a2 2 0 0 1 2 2v6'],
    playlist: ['M14 17a3 3 0 1 0 -6 0a3 3 0 0 0 6 0', 'M14 17v-13h3', 'M3 6h10', 'M3 12h5', 'M3 18h3'],
    user: ['M8 7a4 4 0 1 0 8 0a4 4 0 0 0 -8 0', 'M6 21v-2a4 4 0 0 1 4 -4h4a4 4 0 0 1 4 4v2'],
    logout: ['M14 8v-2a2 2 0 0 0 -2 -2h-7a2 2 0 0 0 -2 2v12a2 2 0 0 0 2 2h7a2 2 0 0 0 2 -2v-2', 'M9 12h12l-3 -3', 'M18 15l3 -3'],
    chevron: ['M6 9l6 6l6 -6'],
};

defineProps({name: {type: String, required: true}});
</script>

<template>
    <svg
        xmlns="http://www.w3.org/2000/svg"
        viewBox="0 0 24 24"
        fill="none"
        stroke="currentColor"
        stroke-width="1.5"
        stroke-linecap="round"
        stroke-linejoin="round"
        aria-hidden="true"
        class="h-5 w-5 shrink-0"
    >
        <path v-for="d in paths[name]" :key="d" :d="d" />
    </svg>
</template>
