<script setup lang="ts">
import {useForm} from '@inertiajs/vue3';
import {computed, ref} from 'vue';
import {join} from '@/routes/parties';

const props = defineProps<{inputId: string}>();

const form = useForm({code: ''});
const localError = ref('');
const error = computed(() => localError.value || form.errors.code || '');
const hintId = computed(() => `${props.inputId}-hint`);

function normalise(): void {
    form.code = form.code.toUpperCase();
    localError.value = '';
    form.clearErrors('code');
}

function submit(): void {
    if (!/^[A-Z]{4}$/.test(form.code)) {
        localError.value = 'Party codes are four letters, no digits.';
        return;
    }
    form.post(join().url);
}
</script>

<template>
    <form novalidate data-test="join-form" @submit.prevent="submit">
        <div class="mp-join-form">
            <input
                :id="inputId"
                v-model="form.code"
                class="mp-code-input"
                :class="{'is-invalid': error}"
                maxlength="4"
                placeholder="KXQB"
                autocapitalize="characters"
                autocomplete="off"
                spellcheck="false"
                :aria-invalid="error ? 'true' : undefined"
                :aria-describedby="hintId"
                @input="normalise"
            />
            <button type="submit" class="btn btn-primary" :disabled="form.processing">Join</button>
        </div>
        <div v-if="error" :id="hintId" class="invalid-feedback d-block" role="alert">{{ error }}</div>
        <div v-else :id="hintId" class="form-hint mt-2">
            <slot name="hint">Four letters. It's on the TV, or ask the host.</slot>
        </div>
    </form>
</template>
