<script setup lang="ts">
import {computed} from 'vue';
import type {InertiaForm} from '@inertiajs/vue3';
import SettingRow from '@/components/SettingRow.vue';
import {hintFor, labelFor} from '@/lib/adminSettings';
import type {SettingField} from '@/lib/adminSettings';

const props = defineProps<{
    form: InertiaForm<Record<string, string | boolean>>;
    settings: SettingField[];
}>();

const byCode = (codes: string[]): SettingField[] =>
    codes.flatMap((code) => props.settings.filter((setting) => setting.code === code));
const logoSettings = computed(() => byCode(['logo-light', 'logo-dark']));
const browserSettings = computed(() => byCode(['cover-image', 'favicon']));
</script>

<template>
    <div id="panel-branding" role="tabpanel" aria-labelledby="tab-branding">
        <section class="mp-sec">
            <h2>Logos</h2>
            <SettingRow
                v-for="setting in logoSettings"
                :key="setting.code"
                stacked
                :label="labelFor(setting)"
                :for="setting.code"
                :hint="hintFor(setting)"
                :error="form.errors[setting.code]"
            >
                <input
                    :id="setting.code"
                    v-model="form[setting.code]"
                    type="url"
                    :name="setting.code"
                    class="form-control"
                    :class="{'is-invalid': form.errors[setting.code]}"
                />
                <figure v-if="form[setting.code]" class="brand-preview m-0" :data-test="`preview-${setting.code}`">
                    <span class="brand-tile" :class="setting.code === 'logo-light' ? 'is-sidebar' : 'is-surface'">
                        <img :src="String(form[setting.code])" alt="" />
                    </span>
                    <figcaption>
                        {{ setting.code === 'logo-light' ? 'Preview on the sidebar colour' : 'Preview on white' }}
                    </figcaption>
                </figure>
            </SettingRow>
        </section>
        <section class="mp-sec">
            <h2>Login and browser</h2>
            <SettingRow
                v-for="setting in browserSettings"
                :key="setting.code"
                stacked
                :label="labelFor(setting)"
                :for="setting.code"
                :hint="hintFor(setting)"
                :error="form.errors[setting.code]"
            >
                <input
                    :id="setting.code"
                    v-model="form[setting.code]"
                    type="url"
                    :name="setting.code"
                    class="form-control"
                    :class="{'is-invalid': form.errors[setting.code]}"
                />
                <figure v-if="form[setting.code]" class="brand-preview m-0" :data-test="`preview-${setting.code}`">
                    <img
                        v-if="setting.code === 'cover-image'"
                        class="brand-cover"
                        :src="String(form[setting.code])"
                        alt=""
                    />
                    <span v-else class="brand-tile is-favicon">
                        <img :src="String(form[setting.code])" alt="" />
                    </span>
                    <figcaption>
                        {{
                            setting.code === 'cover-image'
                                ? 'Preview, cropped as on a laptop'
                                : '32 pixels, as in a tab'
                        }}
                    </figcaption>
                </figure>
            </SettingRow>
        </section>
    </div>
</template>

<style scoped>
.brand-preview {
    display: grid;
    gap: 0.375rem;
    justify-items: start;
    inline-size: 100%;
}

.brand-preview figcaption {
    color: var(--tblr-secondary-color);
    font-size: var(--mp-fs-small);
}

.brand-tile {
    display: grid;
    place-items: center;
    min-inline-size: 11rem;
    block-size: 4.5rem;
    border: 1px solid var(--tblr-border-color);
    border-radius: var(--tblr-border-radius);
}

.brand-tile img {
    max-block-size: 2.25rem;
    max-inline-size: 80%;
}

.brand-tile.is-sidebar {
    background: var(--mp-nav-background, var(--mp-default-nav-background));
}

.brand-tile.is-surface {
    background: var(--tblr-white);
}

.brand-tile.is-favicon {
    min-inline-size: 2rem;
    inline-size: 2rem;
    block-size: 2rem;
}

.brand-tile.is-favicon img {
    max-block-size: 1.25rem;
}

.brand-cover {
    inline-size: 100%;
    max-inline-size: 12rem;
    aspect-ratio: 16 / 10;
    object-fit: cover;
    border: 1px solid var(--tblr-border-color);
    border-radius: var(--tblr-border-radius);
}
</style>
