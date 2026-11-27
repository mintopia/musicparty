<script setup lang="ts">
import {computed} from 'vue';
import type {ModSettingValue} from '@/lib/mods';

defineProps<{
    id: string;
    locked: boolean;
    hasContextSetting: boolean;
    errors: Record<string, string | undefined>;
}>();

const values = defineModel<Record<string, ModSettingValue>>({required: true});

const EXAMPLES = [
    {
        label: 'LAN-friendly',
        text: 'Keep it danceable and LAN-friendly. No tracks that are mostly spoken word or sound effects.',
    },
    {label: 'Family-friendly', text: 'Family-friendly only: no explicit lyrics or themes.'},
    {label: 'No spoken word', text: 'No podcasts, comedy sets or tracks that are mostly spoken word.'},
    {label: 'Time-of-day rule', text: 'Novelty tracks are fine before 10pm. After that, keep to the main vibe.'},
];

const guidelines = computed(() => String(values.value.guidelines ?? ''));
const charCount = computed(() => [...guidelines.value].length);

function set(code: string, value: ModSettingValue): void {
    values.value[code] = value;
}

function addExample(text: string): void {
    const current = guidelines.value.trimEnd();
    set('guidelines', current === '' ? text : `${current}\n${text}`);
}
</script>

<template>
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
            <label v-if="hasContextSetting" class="ai-context" data-setting="include_party_context">
                <span>
                    <span class="ai-context-name">Include party context</span>
                    <span class="ai-context-hint">
                        Sends the model a short music-only summary of this party: recent, best-rated and most downvoted
                        tracks, top artists and a few stats. No names or user data.
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
</template>
