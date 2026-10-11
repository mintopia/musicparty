<script setup>
import {router, usePage} from '@inertiajs/vue3';
import {computed} from 'vue';
import Alert from './ui/Alert.vue';
import Button from './ui/Button.vue';

const impersonating = computed(() => usePage().props.impersonating ?? null);
const stop = () => router.delete(impersonating.value.stopUrl);
</script>

<template>
    <div v-if="impersonating" data-testid="impersonation-banner" class="px-6 pt-4">
        <Alert variant="warning">
            <div class="flex items-center justify-between gap-3">
                <span>You are impersonating <strong>{{ impersonating.name }}</strong></span>
                <Button variant="outline-danger" data-action="stop-impersonating" @click="stop">Remove</Button>
            </div>
        </Alert>
    </div>
</template>
