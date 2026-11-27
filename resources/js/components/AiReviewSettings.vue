<script setup lang="ts">
import {computed, ref} from 'vue';
import ModSettingField from '@/components/ModSettingField.vue';
import {hasSavedApiKey, settingByCode} from '@/lib/mods';
import type {ModSettingValue} from '@/lib/mods';
import type {ModSetting} from '@/types';

const props = defineProps<{
    id: string;
    settings: ModSetting[];
    errors: Record<string, string | undefined>;
}>();

const values = defineModel<Record<string, ModSettingValue>>({required: true});

const PRESETS = [
    {label: 'Strict', value: 30},
    {label: 'Balanced', value: 50},
    {label: 'Lenient', value: 80},
];

const EXAMPLES = [
    {
        label: 'LAN-friendly',
        text: 'Keep it danceable and LAN-friendly. No tracks that are mostly spoken word or sound effects.',
    },
    {label: 'Family-friendly', text: 'Family-friendly only: no explicit lyrics or themes.'},
    {label: 'No spoken word', text: 'No podcasts, comedy sets or tracks that are mostly spoken word.'},
    {label: 'Time-of-day rule', text: 'Novelty tracks are fine before 10pm. After that, keep to the main vibe.'},
];

const DRIVER_HINTS: Record<string, string> = {
    'decision-model': 'Scores the Request through a decisions endpoint such as OpenRouter or direct TypeSafe.',
    'openai-compatible': 'Asks a chat model. Adds Endpoint and Model under Advanced.',
};

const ADVANCED = ['timeout', 'decision_model', 'decision_endpoint', 'endpoint', 'model'];

const driver = computed(() => settingByCode(props.settings, 'driver'));
const keySetting = computed(() => settingByCode(props.settings, 'api_key'));
const keySaved = computed(() => hasSavedApiKey(props.settings));
const locked = computed(() => keySetting.value !== undefined && !keySaved.value);
const replacing = ref(false);
const advanced = computed(() =>
    ADVANCED.map((code) => settingByCode(props.settings, code)).filter((s): s is ModSetting => s !== undefined),
);
const advancedHasError = computed(() => advanced.value.some((s) => props.errors[s.code]));

const threshold = computed(() => {
    const raw = Number(values.value.confidence_percentage);
    return Number.isFinite(raw) ? Math.min(100, Math.max(0, raw)) : 0;
});
const sureAbove = computed(() => Math.min(100, threshold.value + 15));
const sureBelow = computed(() => Math.max(0, threshold.value - 15));
const guidelines = computed(() => String(values.value.guidelines ?? ''));
const charCount = computed(() => [...guidelines.value].length);

function set(code: string, value: ModSettingValue): void {
    values.value[code] = value;
}

function setThreshold(raw: string): void {
    set('confidence_percentage', raw === '' ? null : Number(raw));
}

function addExample(text: string): void {
    const current = guidelines.value.trimEnd();
    set('guidelines', current === '' ? text : `${current}\n${text}`);
}
</script>

<template>
    <div class="ai-settings" data-test="ai-review-settings">
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

        <section v-if="keySetting && !keySaved" class="ai-setup" data-test="ai-setup">
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

        <fieldset class="ai-gated" :disabled="locked" :class="{'ai-gated-off': locked}" data-test="ai-gated">
            <section class="ai-section" data-section="decision">
                <div class="ai-section-intro">
                    <h3 class="h4">Decision</h3>
                    <p>
                        How sure the model must be before a Request is flagged or rejected. Below that, the Request is
                        allowed.<template v-if="locked"> Available once a key is saved.</template>
                    </p>
                </div>
                <div class="ai-section-body" data-setting="confidence_percentage">
                    <label class="form-label" :for="`${id}-confidence`">Confidence threshold</label>
                    <div class="ai-threshold">
                        <div class="ai-range">
                            <input
                                type="range"
                                class="form-range"
                                min="0"
                                max="100"
                                step="1"
                                aria-label="Confidence threshold slider"
                                :value="threshold"
                                data-test="threshold-range"
                                @input="setThreshold(($event.target as HTMLInputElement).value)"
                            />
                            <div class="ai-range-ends">
                                <span>0 · acts on every rating</span><span>100 · never acts</span>
                            </div>
                        </div>
                        <div class="input-group ai-number">
                            <input
                                :id="`${id}-confidence`"
                                type="number"
                                class="form-control"
                                :class="{'is-invalid': errors.confidence_percentage}"
                                min="0"
                                max="100"
                                step="1"
                                :value="values.confidence_percentage ?? ''"
                                data-test="setting-input"
                                @input="setThreshold(($event.target as HTMLInputElement).value)"
                            />
                            <span class="input-group-text">%</span>
                        </div>
                    </div>
                    <div class="ai-presets">
                        <button
                            v-for="preset in PRESETS"
                            :key="preset.label"
                            type="button"
                            class="btn ai-chip"
                            :class="{'ai-chip-active': threshold === preset.value}"
                            :aria-pressed="threshold === preset.value"
                            data-test="threshold-preset"
                            @click="set('confidence_percentage', preset.value)"
                        >
                            {{ preset.label }} {{ preset.value }}%
                        </button>
                    </div>
                    <div v-if="errors.confidence_percentage" class="invalid-feedback d-block" data-test="setting-error">
                        {{ errors.confidence_percentage }}
                    </div>
                    <div class="ai-explain" aria-live="polite" data-test="threshold-explanation">
                        <p class="ai-explain-lead">
                            At {{ threshold }}%, the model has to be at least {{ threshold }}% sure before its rating is
                            acted on. When it is less sure, the Request is allowed.
                        </p>
                        <p class="ai-explain-line">
                            <i class="ti ti-flag ai-flag" aria-hidden="true"></i>
                            <span>
                                <strong>{{ sureAbove }}% sure</strong> a Request breaks your guidelines: flagged for
                                managers or rejected with a reason.
                            </span>
                        </p>
                        <p class="ai-explain-line">
                            <i class="ti ti-check ai-ok" aria-hidden="true"></i>
                            <span>
                                <strong>{{ sureBelow }}% sure</strong>: allowed. Raise the threshold for fewer false
                                alarms, lower it to catch more.
                            </span>
                        </p>
                    </div>
                </div>
            </section>

            <section class="ai-section" data-section="guidelines">
                <div class="ai-section-intro">
                    <h3 class="h4">Guidelines</h3>
                    <p>
                        What this party does and does not want to hear, passed to the model with every Request.<template
                            v-if="locked"
                        >
                            Available once a key is saved.</template
                        >
                    </p>
                    <ul>
                        <li>Say what you want as well as what you do not.</li>
                        <li>Be specific: times, genres, how loud.</li>
                        <li>Short and plain beats long and clever.</li>
                    </ul>
                </div>
                <div class="ai-section-body" data-setting="guidelines">
                    <label class="form-label" :for="`${id}-guidelines`">Guidelines</label>
                    <div class="ai-editor" :class="{'ai-editor-invalid': errors.guidelines}">
                        <div class="ai-examples">
                            <span class="ai-examples-label">Add an example</span>
                            <button
                                v-for="example in EXAMPLES"
                                :key="example.label"
                                type="button"
                                class="btn ai-chip"
                                data-test="guideline-example"
                                @click="addExample(example.text)"
                            >
                                <i class="ti ti-plus" aria-hidden="true"></i> {{ example.label }}
                            </button>
                        </div>
                        <textarea
                            :id="`${id}-guidelines`"
                            rows="10"
                            class="ai-textarea"
                            placeholder="Say what this party wants to hear and what it does not. One rule per line works well."
                            :value="guidelines"
                            data-test="setting-input"
                            @input="set('guidelines', ($event.target as HTMLTextAreaElement).value)"
                        ></textarea>
                        <div class="ai-editor-foot">
                            <span data-test="char-count"
                                >{{ charCount }} {{ charCount === 1 ? 'character' : 'characters' }}</span
                            >
                            <span>Applies to this party only</span>
                        </div>
                    </div>
                    <div v-if="errors.guidelines" class="invalid-feedback d-block" data-test="setting-error">
                        {{ errors.guidelines }}
                    </div>
                </div>
                <div class="ai-section-body ai-context-wrap">
                    <label
                        v-if="settingByCode(settings, 'include_party_context')"
                        class="ai-context"
                        data-setting="include_party_context"
                    >
                        <span>
                            <span class="ai-context-name">Include party context</span>
                            <span class="ai-context-hint">
                                Sends the model a short music-only summary of this party: recent, best-rated and most
                                downvoted tracks, top artists and a few stats. No names or user data.
                            </span>
                        </span>
                        <span class="form-check form-switch mb-0 ps-0 min-height-0">
                            <input
                                type="checkbox"
                                role="switch"
                                class="form-check-input m-0"
                                :checked="values.include_party_context === true"
                                data-test="setting-input"
                                @change="set('include_party_context', ($event.target as HTMLInputElement).checked)"
                            />
                        </span>
                    </label>
                </div>
            </section>
        </fieldset>

        <details v-if="advanced.length" class="ai-advanced" :open="advancedHasError" data-test="ai-advanced">
            <summary>
                <i class="ti ti-chevron-down" aria-hidden="true"></i>
                <strong>Advanced</strong>
                <span class="ai-advanced-hint">Timeout, Decision model, Decision endpoint</span>
            </summary>
            <div class="ai-advanced-body">
                <ModSettingField
                    v-for="setting in advanced"
                    :id="`${id}-${setting.code}`"
                    :key="setting.code"
                    v-model="values[setting.code]!"
                    row
                    :setting="setting"
                    :error="errors[setting.code]"
                />
            </div>
        </details>
    </div>
</template>

<style scoped>
.ai-section {
    display: grid;
    grid-template-columns: minmax(0, 15rem) minmax(0, 1fr);
    gap: 1rem 2rem;
    padding: 1.25rem;
    border-top: 1px solid var(--tblr-border-color);
}

.ai-section-intro h4 {
    margin: 0 0 0.25rem;
    font-size: 1rem;
    font-weight: 600;
}

.ai-section-intro p,
.ai-section-intro ul {
    margin: 0 0 0.5rem;
    color: var(--tblr-secondary);
    font-size: 0.8125rem;
}

.ai-section-intro ul {
    padding-inline-start: 1.1rem;
}

.ai-section-body {
    min-width: 0;
}

.ai-context-wrap {
    grid-column: 2;
    margin-top: -0.5rem;
}

.ai-field + .ai-field {
    margin-top: 1rem;
}

fieldset.ai-field {
    min-width: 0;
    margin: 0;
    padding: 0;
    border: 0;
}

.ai-gated {
    min-width: 0;
    margin: 0;
    padding: 0;
    border: 0;
}

.ai-gated-off {
    opacity: 0.5;
}

.ai-engines {
    display: grid;
    grid-template-columns: repeat(2, minmax(0, 1fr));
    gap: 0.75rem;
}

.ai-engine {
    display: flex;
    align-items: flex-start;
    gap: 0.625rem;
    min-block-size: 44px;
    padding: 0.75rem;
    border: 1px solid var(--tblr-border-color);
    border-radius: var(--tblr-border-radius);
    cursor: pointer;
}

.ai-engine-selected {
    border-color: var(--tblr-primary);
    background: color-mix(in oklab, var(--tblr-primary) 8%, transparent);
    box-shadow: 0 0 0 1px var(--tblr-primary);
}

.ai-engine .form-check-input {
    flex: none;
    margin-top: 0.2rem;
}

.ai-engine-name {
    display: block;
    font-weight: 600;
}

.ai-engine-hint {
    display: block;
    color: var(--tblr-secondary);
    font-size: 0.8125rem;
}

.ai-key-row {
    display: flex;
    flex-wrap: wrap;
    align-items: center;
    gap: 0.5rem;
}

.ai-key-row .form-control {
    flex: 1 1 14rem;
    min-width: 0;
}

.ai-saved {
    padding: 0.125rem 0.5rem;
    border-radius: 999px;
    background: var(--tblr-success-lt);
    color: var(--tblr-success);
    font-size: 0.8125rem;
    font-weight: 600;
}

.ai-setup {
    display: flex;
    gap: 0.75rem;
    padding: 1rem 1.25rem;
    border-top: 1px solid var(--tblr-border-color);
    background: var(--tblr-warning-lt);
}

.ai-setup-icon {
    font-size: 1.25rem;
}

.ai-setup-body {
    min-width: 0;
    flex: 1;
}

.ai-setup-steps {
    margin: 0 0 0.75rem;
    padding-inline-start: 1.25rem;
    color: var(--tblr-secondary);
    font-size: 0.875rem;
}

.ai-threshold {
    display: flex;
    flex-wrap: wrap;
    align-items: flex-start;
    gap: 1rem;
}

.ai-range {
    flex: 1 1 14rem;
    min-width: 0;
}

.ai-range-ends {
    display: flex;
    justify-content: space-between;
    gap: 0.5rem;
    color: var(--tblr-secondary);
    font-size: 0.75rem;
}

.ai-number {
    flex: none;
    inline-size: 7rem;
}

.ai-presets,
.ai-examples {
    display: flex;
    flex-wrap: wrap;
    gap: 0.5rem;
}

.ai-presets {
    margin-top: 0.75rem;
}

.ai-chip {
    min-block-size: 44px;
    padding-inline: 0.75rem;
    border: 1px solid var(--tblr-border-color);
    background: var(--tblr-bg-surface);
    color: var(--tblr-body-color);
    font-size: 0.8125rem;
}

.ai-examples .ai-chip {
    border-radius: 999px;
}

.ai-chip-active {
    border-color: var(--tblr-primary);
    background: color-mix(in oklab, var(--tblr-primary) 10%, transparent);
    color: var(--tblr-primary);
    font-weight: 600;
}

.ai-explain {
    margin-top: 1rem;
    padding: 0.75rem 1rem;
    border: 1px solid var(--tblr-border-color);
    border-radius: var(--tblr-border-radius);
    background: var(--tblr-bg-surface-secondary);
}

.ai-explain p {
    margin: 0;
}

.ai-explain-lead {
    margin-bottom: 0.5rem !important;
    font-weight: 500;
}

.ai-explain-line {
    display: flex;
    gap: 0.5rem;
    margin-top: 0.375rem !important;
    font-size: 0.875rem;
}

.ai-flag {
    color: var(--tblr-danger);
}

.ai-ok {
    color: var(--tblr-success);
}

.ai-editor {
    border: 1px solid var(--tblr-border-color);
    border-radius: var(--tblr-border-radius);
    background: var(--tblr-bg-forms, var(--tblr-bg-surface));
    overflow: hidden;
}

.ai-editor-invalid {
    border-color: var(--tblr-danger);
}

.ai-examples {
    align-items: center;
    padding: 0.5rem;
    border-bottom: 1px solid var(--tblr-border-color);
    background: var(--tblr-bg-surface-secondary);
}

.ai-examples-label {
    color: var(--tblr-secondary);
    font-size: 0.8125rem;
}

.ai-textarea {
    display: block;
    inline-size: 100%;
    padding: 0.75rem;
    border: 0;
    background: transparent;
    color: var(--tblr-body-color);
    resize: vertical;
}

.ai-textarea:focus-visible {
    outline: 2px solid var(--tblr-primary);
    outline-offset: -2px;
}

.ai-editor-foot {
    display: flex;
    justify-content: space-between;
    gap: 0.5rem;
    padding: 0.375rem 0.75rem;
    border-top: 1px solid var(--tblr-border-color);
    color: var(--tblr-secondary);
    font-size: 0.8125rem;
}

.ai-context {
    display: flex;
    align-items: flex-start;
    justify-content: space-between;
    gap: 1rem;
    min-block-size: 44px;
    padding: 0.75rem 1rem;
    border: 1px solid var(--tblr-border-color);
    border-radius: var(--tblr-border-radius);
}

.ai-context-name {
    display: block;
    font-weight: 600;
}

.ai-context-hint {
    display: block;
    color: var(--tblr-secondary);
    font-size: 0.8125rem;
}

.ai-advanced {
    border-top: 1px solid var(--tblr-border-color);
}

.ai-advanced summary {
    display: flex;
    flex-wrap: wrap;
    align-items: center;
    gap: 0.25rem 0.75rem;
    min-block-size: 44px;
    padding: 0.75rem 1.25rem;
    cursor: pointer;
    list-style: none;
}

.ai-advanced summary::-webkit-details-marker {
    display: none;
}

.ai-advanced[open] summary .ti-chevron-down {
    transform: rotate(180deg);
}

.ai-advanced-hint {
    color: var(--tblr-secondary);
    font-size: 0.8125rem;
}

.ai-advanced-body {
    padding: 0 1.25rem 0.75rem;
}

@media (max-width: 767.98px) {
    .ai-section {
        grid-template-columns: minmax(0, 1fr);
        padding: 1rem;
    }

    .ai-context-wrap {
        grid-column: 1;
        margin-top: 0;
    }

    .ai-engines {
        grid-template-columns: minmax(0, 1fr);
    }

    .ai-advanced summary {
        padding-inline: 1rem;
    }

    .ai-advanced-body {
        padding-inline: 1rem;
    }
}
</style>
