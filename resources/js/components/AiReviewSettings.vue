<script setup lang="ts">
import {computed} from 'vue';
import AiReviewDecisionSection from '@/components/AiReview/AiReviewDecisionSection.vue';
import AiReviewEngineSection from '@/components/AiReview/AiReviewEngineSection.vue';
import AiReviewGuidelinesSection from '@/components/AiReview/AiReviewGuidelinesSection.vue';
import AiReviewKeySetup from '@/components/AiReview/AiReviewKeySetup.vue';
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

const ADVANCED = ['timeout', 'decision_model', 'decision_endpoint', 'endpoint', 'model'];

const driver = computed(() => settingByCode(props.settings, 'driver'));
const keySetting = computed(() => settingByCode(props.settings, 'api_key'));
const keySaved = computed(() => hasSavedApiKey(props.settings));
const locked = computed(() => keySetting.value !== undefined && !keySaved.value);
const hasContextSetting = computed(() => settingByCode(props.settings, 'include_party_context') !== undefined);
const advanced = computed(() =>
    ADVANCED.map((code) => settingByCode(props.settings, code)).filter((s): s is ModSetting => s !== undefined),
);
const advancedHasError = computed(() => advanced.value.some((s) => props.errors[s.code]));
</script>

<template>
    <div class="ai-settings" data-test="ai-review-settings">
        <AiReviewEngineSection
            v-model="values"
            :id="id"
            :driver="driver"
            :key-setting="keySetting"
            :key-saved="keySaved"
            :errors="errors"
        />

        <AiReviewKeySetup v-if="keySetting && !keySaved" v-model="values" :id="id" :errors="errors" />

        <fieldset class="ai-gated" :disabled="locked" :class="{'ai-gated-off': locked}" data-test="ai-gated">
            <AiReviewDecisionSection v-model="values" :id="id" :locked="locked" :errors="errors" />
            <AiReviewGuidelinesSection
                v-model="values"
                :id="id"
                :locked="locked"
                :has-context-setting="hasContextSetting"
                :errors="errors"
            />
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

<style src="@/components/AiReview/ai-review.css"></style>
