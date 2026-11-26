<script setup lang="ts">
import {onBeforeUnmount, onMounted, ref, watch} from 'vue';
import {createEditor, type PrismEditor} from 'prism-code-editor';
import {defaultKeymap, editorCommands} from 'prism-code-editor/commands';
import 'prism-code-editor/prism/languages/css';
import 'prism-code-editor/languages/css';
import 'prism-code-editor/layout.css';

export interface ThemeFormData {
    name: string;
    css: string;
    active: boolean;
    dark_mode: boolean;
    tokens: Record<string, string>;
    errors: Record<string, string>;
}

const props = defineProps<{
    form: ThemeFormData;
    readonly: boolean;
    colourTokens: {token: string; label: string; required: boolean}[];
}>();

const editorHost = ref<HTMLElement | null>(null);
let editor: PrismEditor | null = null;

onMounted(() => {
    if (editorHost.value === null) {
        return;
    }
    editor = createEditor(
        editorHost.value,
        {
            language: 'css',
            value: props.form.css ?? '',
            tabSize: 4,
            insertSpaces: true,
            readOnly: props.readonly,
            wordWrap: true,
            lineNumbers: true,
            onUpdate: (value) => {
                if (value !== props.form.css) {
                    props.form.css = value;
                }
            },
        },
        editorCommands(defaultKeymap),
    );
    const {textarea} = editor;
    textarea.id = 'css';
    textarea.name = 'css';
    textarea.setAttribute('aria-describedby', 'css-hint');
    textarea.setAttribute('aria-multiline', 'true');
    textarea.setAttribute('spellcheck', 'false');
    editor.container.classList.toggle('is-invalid', Boolean(props.form.errors.css));
});

watch(
    () => props.form.css,
    (css) => {
        if (editor !== null && css !== editor.value) {
            editor.setOptions({value: css ?? ''});
        }
    },
);

watch(
    () => props.readonly,
    (readOnly) => editor?.setOptions({readOnly}),
);

watch(
    () => props.form.errors.css,
    (error) => editor?.container.classList.toggle('is-invalid', Boolean(error)),
);

onBeforeUnmount(() => {
    editor?.remove();
    editor = null;
});
</script>

<template>
    <div class="card-body">
        <div class="row">
            <div class="col-md-6 mb-3">
                <label class="form-label required" for="name">Name</label>
                <div>
                    <input
                        id="name"
                        v-model="form.name"
                        type="text"
                        name="name"
                        class="form-control"
                        :class="{'is-invalid': form.errors.name}"
                        placeholder="Name"
                        :disabled="readonly"
                    />
                    <small class="form-hint">The name of the theme.</small>
                    <p v-if="form.errors.name" class="invalid-feedback">{{ form.errors.name }}</p>
                </div>
            </div>
        </div>
        <div class="row">
            <div class="col-md-6">
                <label class="form-check form-switch">
                    <input v-model="form.active" type="checkbox" class="form-check-input" name="active" />
                    Active
                </label>
            </div>
        </div>
        <div class="row mb-3">
            <div class="col">
                <label class="form-label" for="css">Custom CSS</label>
                <div ref="editorHost" class="d-grid" data-testid="css-editor"></div>
                <small id="css-hint" class="form-hint code-editor-hint">
                    Press <kbd>Ctrl</kbd>+<kbd>M</kbd> to let Tab move focus out of the editor.
                </small>
                <p v-if="form.errors.css" class="invalid-feedback">{{ form.errors.css }}</p>
            </div>
        </div>
        <div class="row">
            <div class="col-md-6">
                <h3 class="my-4">Site Colours</h3>
                <div v-for="item in colourTokens" :key="item.token" class="mb-3">
                    <label class="form-label" :class="{required: item.required}">{{ item.label }} Colour</label>
                    <div class="col-12 col-sm-10 col-md-12 col-lg-9">
                        <div class="input-group">
                            <input
                                v-model="form.tokens[item.token]"
                                type="color"
                                class="form-control form-control-color flex-grow-0"
                                style="width: 3.5rem; height: 2.75rem"
                                :title="`${item.label} Colour`"
                                :disabled="readonly"
                            />
                            <input
                                v-model="form.tokens[item.token]"
                                type="text"
                                :name="`tokens[${item.token}]`"
                                class="form-control"
                                :class="{'is-invalid': form.errors[`tokens.${item.token}`]}"
                                placeholder="Colour"
                                :disabled="readonly"
                            />
                        </div>
                    </div>
                    <p v-if="form.errors[`tokens.${item.token}`]" class="invalid-feedback d-block">
                        {{ form.errors[`tokens.${item.token}`] }}
                    </p>
                </div>

                <div class="row">
                    <div class="col-md-6">
                        <label class="form-check form-switch">
                            <input v-model="form.dark_mode" type="checkbox" class="form-check-input" name="dark_mode" />
                            Dark Mode
                        </label>
                    </div>
                </div>
            </div>
        </div>
    </div>
</template>
