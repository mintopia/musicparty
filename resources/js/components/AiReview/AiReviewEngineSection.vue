<script setup lang="ts">
import {ref} from 'vue';
import type {ModSettingValue} from '@/lib/mods';
import type {ModSetting} from '@/types';

defineProps<{
    id: string;
    driver: ModSetting | undefined;
    keySetting: ModSetting | undefined;
    keySaved: boolean;
    errors: Record<string, string | undefined>;
}>();

const values = defineModel<Record<string, ModSettingValue>>({required: true});

const DRIVER_HINTS: Record<string, string> = {
    'decision-model': 'Scores the Request through a decisions endpoint such as OpenRouter or direct TypeSafe.',
    'openai-compatible': 'Asks a chat model. Adds Endpoint and Model under Advanced.',
};

const replacing = ref(false);

function set(code: string, value: ModSettingValue): void {
    values.value[code] = value;
}
</script>

<template>
    <section class="ai-section" data-section="engine">
        <div class="ai-section-intro">
            <h3 class="h4">Engine &amp; key</h3>
            <p>Which model reads each Request, and the key it is billed to.</p>
        </div>
        <div class="ai-section-body">
            <fieldset v-if="driver" class="ai-field" data-setting="driver">
                <legend class="form-label">Review engine</legend>
                <div class="ai-engines">
                    <label
                        v-for="[key, label] in Object.entries(driver.options ?? {})"
                        :key="key"
                        class="ai-engine"
                        :class="{'ai-engine-selected': values.driver === key}"
                    >
                        <input
                            type="radio"
                            class="form-check-input"
                            :name="`${id}-driver`"
                            :value="key"
                            :checked="values.driver === key"
                            data-test="setting-input"
                            @change="set('driver', key)"
                        />
                        <span>
                            <span class="ai-engine-name">{{ label }}</span>
                            <span v-if="DRIVER_HINTS[key]" class="ai-engine-hint">{{ DRIVER_HINTS[key] }}</span>
                        </span>
                    </label>
                </div>
                <div v-if="errors.driver" class="invalid-feedback d-block" data-test="setting-error">
                    {{ errors.driver }}
                </div>
            </fieldset>
            <div v-if="keySetting && keySaved" class="ai-field" data-setting="api_key">
                <label class="form-label" :for="`${id}-api_key`">API key</label>
                <div class="ai-key-row">
                    <input
                        v-if="replacing"
                        :id="`${id}-api_key`"
                        type="password"
                        class="form-control"
                        :class="{'is-invalid': errors.api_key}"
                        autocomplete="new-password"
                        placeholder="Paste the new key"
                        :value="String(values.api_key ?? '')"
                        data-test="setting-input"
                        @input="set('api_key', ($event.target as HTMLInputElement).value)"
                    />
                    <input
                        v-else
                        :id="`${id}-api_key`"
                        type="password"
                        class="form-control"
                        value="••••••••••••••"
                        readonly
                        :aria-describedby="`${id}-api_key-saved`"
                    />
                    <span :id="`${id}-api_key-saved`" class="ai-saved" data-test="key-saved">
                        <i class="ti ti-check" aria-hidden="true"></i> Saved
                    </span>
                    <button
                        type="button"
                        class="btn"
                        data-test="replace-key"
                        @click="
                            replacing = !replacing;
                            if (!replacing) set('api_key', '');
                        "
                    >
                        {{ replacing ? 'Keep saved key' : 'Replace key' }}
                    </button>
                </div>
                <div class="form-hint">Used by every engine. Stored encrypted and never shown again.</div>
                <div v-if="errors.api_key" class="invalid-feedback d-block" data-test="setting-error">
                    {{ errors.api_key }}
                </div>
            </div>
        </div>
    </section>
</template>
