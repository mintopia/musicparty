<script setup lang="ts">
import {computed} from 'vue';
import SettingRow from '@/components/SettingRow.vue';

export interface ModerationFormData {
    type: string;
    action: string;
    value: string;
    enabled: boolean;
    regex: boolean;
    notes: string;
    errors: Record<string, string>;
}

const props = defineProps<{
    form: ModerationFormData;
    types: Record<string, string>;
    actions: Record<string, string>;
}>();

const orderedActions = computed(() =>
    Object.keys(props.actions).sort((a, b) => Number(b === 'flag') - Number(a === 'flag')),
);

const actionDetails: Record<string, {title: string; text: string}> = {
    flag: {
        title: 'Flag for approval',
        text: 'Hold the Request in Flagged Requests until someone approves or rejects it.',
    },
    reject: {
        title: 'Reject the Request',
        text: 'Turn it away straight away. The requester is told it was rejected.',
    },
};
</script>

<template>
    <div class="card-body pt-2">
        <SettingRow label="Type" for="type" hint="What part of the track to look at." :error="form.errors.type">
            <select
                id="type"
                v-model="form.type"
                name="type"
                class="form-select"
                :class="{'is-invalid': form.errors.type}"
            >
                <option value="">Select...</option>
                <option v-for="(text, name) in types" :key="name" :value="name">{{ text }}</option>
            </select>
        </SettingRow>
        <SettingRow
            label="Value"
            for="value"
            hint='What to look for. With "Match as a pattern" on, this is a regular expression.'
            :error="form.errors.value"
        >
            <input
                id="value"
                v-model="form.value"
                type="text"
                name="value"
                class="form-control"
                :class="{'is-invalid': form.errors.value}"
            />
        </SettingRow>
        <SettingRow label="Match as a pattern" for="regex" hint="Use a regular expression instead of an exact match.">
            <span class="form-check form-switch mb-0 ps-0 min-height-0">
                <input
                    id="regex"
                    v-model="form.regex"
                    type="checkbox"
                    role="switch"
                    class="form-check-input m-0"
                    name="regex"
                />
            </span>
        </SettingRow>
        <SettingRow label="When a Request matches" stacked :error="form.errors.action">
            <div class="w-100" role="radiogroup" aria-label="When a Request matches">
                <label
                    v-for="value in orderedActions"
                    :key="value"
                    class="mp-rule-opt"
                    :class="{'is-on': form.action === value}"
                >
                    <input v-model="form.action" class="form-check-input" type="radio" name="action" :value="value" />
                    <b>{{ actionDetails[value]?.title ?? actions[value] }}</b>
                    <span>{{ actionDetails[value]?.text ?? '' }}</span>
                </label>
            </div>
        </SettingRow>
        <SettingRow label="Enabled" for="enabled" hint="Turn a rule off to keep it without using it.">
            <span class="form-check form-switch mb-0 ps-0 min-height-0">
                <input
                    id="enabled"
                    v-model="form.enabled"
                    type="checkbox"
                    role="switch"
                    class="form-check-input m-0"
                    name="enabled"
                />
            </span>
        </SettingRow>
        <SettingRow label="Notes" for="notes" hint="Only the owner and moderators see this." :error="form.errors.notes">
            <input
                id="notes"
                v-model="form.notes"
                type="text"
                name="notes"
                class="form-control"
                :class="{'is-invalid': form.errors.notes}"
            />
        </SettingRow>
    </div>
</template>
