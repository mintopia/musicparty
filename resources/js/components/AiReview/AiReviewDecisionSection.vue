<script setup lang="ts">
import {computed} from 'vue';
import type {ModSettingValue} from '@/lib/mods';

defineProps<{
    id: string;
    locked: boolean;
    errors: Record<string, string | undefined>;
}>();

const values = defineModel<Record<string, ModSettingValue>>({required: true});

const PRESETS = [
    {label: 'Strict', value: 30},
    {label: 'Balanced', value: 50},
    {label: 'Lenient', value: 80},
];

const threshold = computed(() => {
    const raw = Number(values.value.confidence_percentage);
    return Number.isFinite(raw) ? Math.min(100, Math.max(0, raw)) : 0;
});
const sureAbove = computed(() => Math.min(100, threshold.value + 15));
const sureBelow = computed(() => Math.max(0, threshold.value - 15));

function set(code: string, value: ModSettingValue): void {
    values.value[code] = value;
}

function setThreshold(raw: string): void {
    set('confidence_percentage', raw === '' ? null : Number(raw));
}
</script>

<template>
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
</template>
