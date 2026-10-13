<script setup lang="ts">
export interface ModerationFormData {
    type: string;
    action: string;
    value: string;
    enabled: boolean;
    regex: boolean;
    notes: string;
    errors: Record<string, string>;
}

defineProps<{
    form: ModerationFormData;
    types: Record<string, string>;
    actions: Record<string, string>;
}>();
</script>

<template>
    <div class="card-body">
        <div class="mb-3 col-md-4">
            <label class="form-label required" for="type">Type</label>
            <div>
                <select
                    id="type"
                    v-model="form.type"
                    name="type"
                    class="form-control"
                    :class="{'is-invalid': form.errors.type}"
                >
                    <option value="">Select...</option>
                    <option v-for="(text, name) in types" :key="name" :value="name">{{ text }}</option>
                </select>
                <small class="form-hint">The type of filter to create</small>
                <p v-if="form.errors.type" class="invalid-feedback">{{ form.errors.type }}</p>
            </div>
        </div>
        <div class="mb-3 col-md-4">
            <label class="form-label" for="action">Action</label>
            <div>
                <select
                    id="action"
                    v-model="form.action"
                    name="action"
                    class="form-control"
                    :class="{'is-invalid': form.errors.action}"
                >
                    <option v-for="(text, value) in actions" :key="value" :value="value">{{ text }}</option>
                </select>
                <small class="form-hint">What happens when a request matches this filter</small>
                <p v-if="form.errors.action" class="invalid-feedback">{{ form.errors.action }}</p>
            </div>
        </div>
        <div class="mb-3">
            <label class="form-label required" for="value">Value</label>
            <div>
                <input
                    id="value"
                    v-model="form.value"
                    type="text"
                    name="value"
                    class="form-control"
                    :class="{'is-invalid': form.errors.value}"
                    placeholder="Filter Text"
                />
                <small class="form-hint">The value to filter</small>
                <p v-if="form.errors.value" class="invalid-feedback">{{ form.errors.value }}</p>
            </div>
        </div>
        <div class="mb-3">
            <label class="form-check form-switch">
                <input v-model="form.enabled" type="checkbox" class="form-check-input" name="enabled" />
                Enable this filter
            </label>
        </div>
        <div class="mb-3">
            <label class="form-check form-switch">
                <input v-model="form.regex" type="checkbox" class="form-check-input" name="regex" />
                Apply filter as a regular expression
            </label>
        </div>
        <div class="mb-3">
            <label class="form-label" for="notes">Notes</label>
            <div>
                <input
                    id="notes"
                    v-model="form.notes"
                    type="text"
                    name="notes"
                    class="form-control"
                    :class="{'is-invalid': form.errors.notes}"
                    placeholder="Notes"
                />
                <small class="form-hint">Notes for your own reference</small>
                <p v-if="form.errors.notes" class="invalid-feedback">{{ form.errors.notes }}</p>
            </div>
        </div>
    </div>
</template>
