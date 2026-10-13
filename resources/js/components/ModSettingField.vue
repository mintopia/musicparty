<script setup lang="ts">
import {computed} from 'vue';
import type {ModSetting} from '@/types';

const props = defineProps<{
    setting: ModSetting;
    modelValue: string | number | boolean | null;
    error?: string;
    id: string;
}>();

const emit = defineEmits<{'update:modelValue': [value: string | number | boolean | null]}>();

const inputType = computed(() => {
    switch (props.setting.type) {
        case 'secret':
            return 'password';
        case 'time_of_day':
            return 'time';
        case 'datetime':
            return 'datetime-local';
        case 'integer':
        case 'float':
            return 'number';
        default:
            return 'text';
    }
});

function onInput(event: Event): void {
    const raw = (event.target as HTMLInputElement).value;
    if (props.setting.type === 'integer' || props.setting.type === 'float') {
        emit('update:modelValue', raw === '' ? null : Number(raw));
        return;
    }
    emit('update:modelValue', raw);
}
</script>

<template>
    <div class="mb-3" data-test="mod-setting" :data-setting="setting.code">
        <div v-if="setting.type === 'boolean'" class="form-check form-switch">
            <input
                :id="id"
                type="checkbox"
                class="form-check-input"
                :class="{'is-invalid': error}"
                :checked="modelValue === true"
                data-test="setting-input"
                @change="emit('update:modelValue', ($event.target as HTMLInputElement).checked)"
            />
            <label :for="id" class="form-check-label">{{ setting.name }}</label>
        </div>
        <template v-else>
            <label :for="id" class="form-label">
                {{ setting.name }}<span v-if="setting.required" class="text-danger"> *</span>
            </label>
            <input
                :id="id"
                :type="inputType"
                class="form-control"
                :class="{'is-invalid': error}"
                :value="modelValue ?? ''"
                :step="setting.type === 'float' ? 'any' : setting.type === 'integer' ? '1' : undefined"
                :autocomplete="setting.type === 'secret' ? 'new-password' : undefined"
                :placeholder="setting.type === 'track' ? 'Track ID' : undefined"
                data-test="setting-input"
                @input="onInput"
            />
            <div v-if="setting.type === 'secret'" class="form-hint" data-test="secret-hint">
                {{ setting.has_value ? 'Currently set. Leave blank to keep it.' : 'Not set.' }}
            </div>
        </template>
        <div v-if="setting.description" class="form-hint">{{ setting.description }}</div>
        <div v-if="error" class="invalid-feedback d-block" data-test="setting-error">{{ error }}</div>
    </div>
</template>
