<script setup lang="ts">
const props = withDefaults(
    defineProps<{
        modelValue: boolean;
        title: string;
        message: string;
        confirmLabel?: string;
        cancelLabel?: string;
    }>(),
    {confirmLabel: 'Confirm', cancelLabel: 'Cancel'},
);

const emit = defineEmits<{
    confirm: [];
    cancel: [];
    'update:modelValue': [value: boolean];
}>();

function confirm(): void {
    emit('confirm');
    emit('update:modelValue', false);
}

function cancel(): void {
    emit('cancel');
    emit('update:modelValue', false);
}
</script>

<template>
    <template v-if="props.modelValue">
        <div
            class="modal modal-blur fade show d-block"
            tabindex="-1"
            role="dialog"
            aria-modal="true"
            @click.self="cancel"
        >
            <div class="modal-dialog modal-sm modal-dialog-centered" role="document">
                <div class="modal-content">
                    <div class="modal-body">
                        <div class="modal-title">{{ props.title }}</div>
                        <div>{{ props.message }}</div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-link link-secondary me-auto" @click="cancel">
                            {{ props.cancelLabel }}
                        </button>
                        <button type="button" class="btn btn-danger" @click="confirm">{{ props.confirmLabel }}</button>
                    </div>
                </div>
            </div>
        </div>
        <div class="modal-backdrop fade show"></div>
    </template>
</template>
