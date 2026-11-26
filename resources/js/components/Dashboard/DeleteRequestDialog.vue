<script setup lang="ts">
import {inject, nextTick, ref, watch} from 'vue';
import {legacyFallbackName} from '@/lib/decoration';
import {deleteRequest, errorCode, notifyKey, reloadDashboard} from '@/lib/dashboard';
import type {DashboardRequest} from '@/types';

const props = defineProps<{code: string; request: DashboardRequest | null}>();
const emit = defineEmits<{close: []}>();

const notify = inject(notifyKey, () => undefined);
const busy = ref(false);
const cancelButton = ref<HTMLButtonElement | null>(null);

watch(
    () => props.request,
    async (request) => {
        if (request !== null) {
            await nextTick();
            cancelButton.value?.focus();
        }
    },
);

async function confirm(): Promise<void> {
    const request = props.request;
    if (request === null) {
        return;
    }
    busy.value = true;
    try {
        await deleteRequest(props.code, request.id);
    } catch (error) {
        const failure = errorCode(error);
        notify(
            failure.status === 400 ? "That request was already sent to Spotify, so it can't be removed." : failure.message,
        );
    } finally {
        busy.value = false;
        emit('close');
        reloadDashboard();
    }
}
</script>

<template>
    <template v-if="request">
        <div
            class="modal modal-blur fade show d-block"
            tabindex="-1"
            role="dialog"
            aria-modal="true"
            aria-labelledby="del-title"
            data-test="delete-dialog"
            @click.self="emit('close')"
            @keydown.esc="emit('close')"
        >
            <div class="modal-dialog modal-sm modal-dialog-centered" role="document">
                <div class="modal-content">
                    <div class="modal-status bg-danger"></div>
                    <div class="modal-body text-center py-4">
                        <i class="ti ti-trash text-danger mb-2 del-icon"></i>
                        <h3 id="del-title">Delete this request?</h3>
                        <div class="text-secondary">
                            <strong>{{ request.name }}</strong> by {{ request.artists.map((a) => a.name).join(', ') }},
                            requested by {{ request.user ?? legacyFallbackName(request) ?? 'the fallback playlist' }},
                            will leave the queue and its {{ request.score }} votes are lost.
                        </div>
                    </div>
                    <div class="modal-footer">
                        <div class="w-100">
                            <div class="row g-2">
                                <div class="col">
                                    <button
                                        ref="cancelButton"
                                        type="button"
                                        class="btn w-100"
                                        data-test="delete-cancel"
                                        @click="emit('close')"
                                    >
                                        Cancel
                                    </button>
                                </div>
                                <div class="col">
                                    <button
                                        type="button"
                                        class="btn btn-danger w-100"
                                        :disabled="busy"
                                        data-test="delete-confirm"
                                        @click="confirm"
                                    >
                                        Delete request
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <div class="modal-backdrop fade show"></div>
    </template>
</template>

<style scoped>
.del-icon {
    font-size: 2.5rem;
}
</style>
