<script setup lang="ts">
import type {ModSettingValue} from '@/lib/mods';

defineProps<{
    id: string;
    errors: Record<string, string | undefined>;
}>();

const values = defineModel<Record<string, ModSettingValue>>({required: true});

function set(code: string, value: ModSettingValue): void {
    values.value[code] = value;
}
</script>

<template>
    <section class="ai-setup" data-test="ai-setup">
        <i class="ti ti-key ai-setup-icon" aria-hidden="true"></i>
        <div class="ai-setup-body" data-setting="api_key">
            <strong>Add an API key to start reviewing</strong>
            <p class="mb-2">Until a key is saved, every Request is allowed without a review.</p>
            <ol class="ai-setup-steps">
                <li>Create a key with OpenRouter, or a TypeSafe account for direct access.</li>
                <li>Paste it here. It is stored encrypted and never shown again.</li>
            </ol>
            <div class="ai-key-row">
                <input
                    :id="`${id}-api_key`"
                    type="password"
                    class="form-control"
                    :class="{'is-invalid': errors.api_key}"
                    autocomplete="new-password"
                    placeholder="Paste your API key"
                    aria-label="API key"
                    :value="String(values.api_key ?? '')"
                    data-test="setting-input"
                    @input="set('api_key', ($event.target as HTMLInputElement).value)"
                />
                <a class="btn btn-link" href="https://openrouter.ai/keys" target="_blank" rel="noopener noreferrer">
                    Get a key <i class="ti ti-external-link" aria-hidden="true"></i>
                </a>
            </div>
            <div v-if="errors.api_key" class="invalid-feedback d-block" data-test="setting-error">
                {{ errors.api_key }}
            </div>
        </div>
    </section>
</template>
