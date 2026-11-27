<script setup lang="ts">
import {computed, onBeforeUnmount, onMounted, ref, watch} from 'vue';
import SettingRow from '@/components/SettingRow.vue';
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
    tokens: Record<string, string | null | undefined>;
    errors: Record<string, string>;
}

const props = defineProps<{
    form: ThemeFormData;
    readonly: boolean;
    colourTokens: {token: string; label: string; default: string}[];
}>();

const HEX = /^#(?:(?:[0-9a-f]{3}){1,2}|(?:[0-9a-f]{4}){1,2})$/i;
const COLOUR_ERROR = 'That is not a colour. Use #RGB or #RRGGBB, for example #d63939.';
const DARK_SURFACES = {bg: '#101820', surface: '#182433', text: '#e6e7e9', muted: '#9aa5b1', border: '#2c3a4a'};
const DARK_STATES = {success: '#4ade80', danger: '#fb5c5c', warning: '#e38902'};
const LIGHT_BG = '#f6f8fb';

const previewDark = ref(props.form.dark_mode);

watch(
    () => props.form.dark_mode,
    (dark) => {
        previewDark.value = dark;
    },
);

function value(token: string): string {
    return props.form.tokens[token] ?? '';
}

function isUnset(token: string): boolean {
    return value(token) === '';
}

function isValid(token: string): boolean {
    return HEX.test(value(token));
}

function fieldError(token: string): string | undefined {
    if (!isUnset(token) && !isValid(token)) {
        return COLOUR_ERROR;
    }
    return props.form.errors[`tokens.${token}`];
}

function swatch(item: {token: string; default: string}): string {
    return isValid(item.token) ? value(item.token) : item.default;
}

function pickerValue(item: {token: string; default: string}): string {
    const colour = swatch(item);
    return colour.length === 4 ? `#${[...colour.slice(1)].map((c) => c + c).join('')}` : colour.slice(0, 7);
}

function setToken(token: string, colour: string): void {
    props.form.tokens[token] = colour;
}

function pickColour(token: string, event: Event): void {
    setToken(token, (event.target as HTMLInputElement).value);
}

const problemCount = computed(() => props.colourTokens.filter((item) => fieldError(item.token) !== undefined).length);

const previewStyle = computed(() => {
    const colour = (token: string, fallback: string): string => {
        return isValid(token) ? value(token) : fallback;
    };
    const dark = previewDark.value;
    const surfaces = dark
        ? DARK_SURFACES
        : {bg: LIGHT_BG, surface: '#ffffff', text: '#182433', muted: '#667382', border: '#e6e7e9'};
    const states = dark ? DARK_STATES : {success: '#2fb344', danger: '#d63939', warning: '#f76707'};
    return {
        '--pv-nav': colour('nav-background', '#182433'),
        '--pv-bg': surfaces.bg,
        '--pv-surf': colour('surface', surfaces.surface),
        '--pv-text': colour('text', surfaces.text),
        '--pv-muted': colour('muted', surfaces.muted),
        '--pv-border': colour('border', surfaces.border),
        '--pv-primary': colour('primary', '#0054a6'),
        '--pv-danger': colour('danger', states.danger),
        '--pv-success': colour('success', states.success),
        '--pv-warning': colour('warning', states.warning),
    };
});

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
    <div class="mp-editor">
        <div class="mp-stack">
            <div class="card">
                <div class="card-header">
                    <h2 class="card-title">Details</h2>
                </div>
                <div class="card-body pt-0">
                    <SettingRow label="Name" hint="Shown in the themes list." for="name" :error="form.errors.name">
                        <input
                            id="name"
                            v-model="form.name"
                            type="text"
                            name="name"
                            class="form-control"
                            :class="{'is-invalid': form.errors.name}"
                            :disabled="readonly"
                        />
                    </SettingRow>
                    <SettingRow
                        label="Dark mode"
                        hint="Everyone sees the dark version while this theme is active. Off follows each user's own light or dark choice."
                    >
                        <label class="form-check form-switch m-0">
                            <input
                                v-model="form.dark_mode"
                                type="checkbox"
                                class="form-check-input"
                                role="switch"
                                name="dark_mode"
                                aria-label="Dark mode"
                            />
                        </label>
                    </SettingRow>
                    <SettingRow label="Active" hint="Use this theme for the whole site. Only one theme is active.">
                        <label class="form-check form-switch m-0">
                            <input
                                v-model="form.active"
                                type="checkbox"
                                class="form-check-input"
                                role="switch"
                                name="active"
                                aria-label="Active"
                            />
                        </label>
                    </SettingRow>
                </div>
            </div>

            <div class="card">
                <div class="card-header">
                    <h2 class="card-title">Colours</h2>
                    <span v-if="problemCount > 0" class="badge bg-red-lt ms-auto" data-test="colour-problems">
                        {{ problemCount }} {{ problemCount === 1 ? 'colour needs' : 'colours need' }} fixing
                    </span>
                    <span v-else class="ms-auto text-secondary small">
                        Anything left on Default keeps the standard look.
                    </span>
                </div>
                <div class="mp-tokens">
                    <div v-for="item in colourTokens" :key="item.token" class="mp-token">
                        <label class="form-label" :for="`token-${item.token}`">{{ item.label }}</label>
                        <div class="input-group has-validation">
                            <span class="input-group-text">
                                <label class="mp-swatch-pick">
                                    <span
                                        class="mp-swatch"
                                        :class="{'is-unset': isUnset(item.token)}"
                                        :style="{'--swatch': swatch(item)}"
                                    ></span>
                                    <input
                                        type="color"
                                        :value="pickerValue(item)"
                                        :aria-label="`Pick ${item.label} colour`"
                                        :disabled="readonly"
                                        @input="pickColour(item.token, $event)"
                                    />
                                </label>
                            </span>
                            <input
                                :id="`token-${item.token}`"
                                v-model="form.tokens[item.token]"
                                type="text"
                                :name="`tokens[${item.token}]`"
                                class="form-control"
                                :class="{'is-invalid': fieldError(item.token)}"
                                :placeholder="item.default"
                                :aria-describedby="`token-hint-${item.token}`"
                                :aria-invalid="fieldError(item.token) !== undefined"
                                spellcheck="false"
                                :disabled="readonly"
                            />
                            <button
                                v-if="!isUnset(item.token) && !readonly"
                                class="btn"
                                type="button"
                                title="Back to the default colour"
                                @click="setToken(item.token, '')"
                            >
                                <i class="icon ti ti-restore"></i>Reset
                            </button>
                            <span v-else-if="isUnset(item.token)" class="input-group-text mp-tag">Default</span>
                            <div v-if="fieldError(item.token)" class="invalid-feedback">
                                {{ fieldError(item.token) }}
                            </div>
                        </div>
                        <div :id="`token-hint-${item.token}`" class="form-hint">
                            {{ isUnset(item.token) ? 'Using the Music Party default' : `Default is ${item.default}` }}
                        </div>
                    </div>
                </div>
            </div>

            <div class="card">
                <div class="card-header">
                    <h2 class="card-title">Custom CSS</h2>
                </div>
                <div class="card-body">
                    <label class="visually-hidden" for="css">Custom CSS</label>
                    <div ref="editorHost" class="d-grid" data-testid="css-editor"></div>
                    <small id="css-hint" class="form-hint d-block mt-2 code-editor-hint">
                        Press <kbd>Ctrl</kbd>+<kbd>M</kbd> to let Tab move focus out of the editor.
                        <code>:root</code> rules apply to light and dark.
                    </small>
                    <p v-if="form.errors.css" class="invalid-feedback d-block">{{ form.errors.css }}</p>
                </div>
            </div>
        </div>

        <div>
            <div class="card mp-sticky" data-test="theme-preview">
                <div class="card-header">
                    <h2 class="card-title">Preview</h2>
                    <div class="ms-auto mp-chips" role="group" aria-label="Preview mode">
                        <button class="mp-chip" type="button" :aria-pressed="!previewDark" @click="previewDark = false">
                            <i class="ti ti-sun"></i>Light
                        </button>
                        <button class="mp-chip" type="button" :aria-pressed="previewDark" @click="previewDark = true">
                            <i class="ti ti-moon"></i>Dark
                        </button>
                    </div>
                </div>
                <div class="card-body">
                    <div class="pv" :style="previewStyle">
                        <div class="pv-nav">
                            <b>Music Party</b>
                            <span>Home</span>
                            <span class="on">Coyote LAN 26</span>
                            <span>Profile</span>
                            <span>Admin</span>
                        </div>
                        <div class="pv-main">
                            <h3>Requests</h3>
                            <div class="pv-card">
                                <div class="pv-row">
                                    <span>Midnight City <span class="pv-muted">&middot; M83</span></span>
                                    <span class="pv-badge" style="background: var(--pv-success)">Played</span>
                                </div>
                                <div class="pv-row">
                                    <span>Sandstorm <span class="pv-muted">&middot; Darude</span></span>
                                    <span class="pv-badge" style="background: var(--pv-warning)">Flagged</span>
                                </div>
                                <span class="pv-muted">Muted text and a <a href="#" @click.prevent>link</a></span>
                            </div>
                            <div class="pv-btns">
                                <span class="pv-btn">Primary</span>
                                <span class="pv-btn o">Outline</span>
                                <span class="pv-btn d">Delete</span>
                            </div>
                        </div>
                    </div>
                    <p class="text-secondary small mt-3 mb-0">
                        Shows {{ form.name || 'this theme' }} with your unsaved changes. Nothing is changed yet.
                    </p>
                </div>
            </div>
        </div>
    </div>
</template>
