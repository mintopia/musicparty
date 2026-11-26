import axios from 'axios';
import {router} from '@inertiajs/vue3';
import {inject, type InjectionKey} from 'vue';
import {activate, control, devices} from '@/routes/api/v1/parties';
import {start} from '@/routes/api/v1/parties/playback';
import {destroy, play} from '@/routes/api/v1/parties/upcomingsongs';
import {flaggedApproveUrl, flaggedRejectUrl} from '@/lib/flagged';
import type {DashboardDevice, DashboardStartState, DashboardStartTarget} from '@/types';

export type PanelState = DashboardStartState | 'starting' | 'rate_limited';
export type ControlAction = 'play' | 'pause' | 'next' | 'previous' | 'seek';

export interface ApiError {
    status: number | null;
    code: string | null;
    message: string;
    retryAfter: number | null;
}

export const notifyKey: InjectionKey<(message: string) => void> = Symbol('dashboard-notify');

export const RELOAD_ALL = ['control', 'upNext', 'upcoming', 'attention'];

export function formatMs(ms: number): string {
    const total = Math.max(0, Math.floor(ms / 1000));
    return `${Math.floor(total / 60)}:${String(total % 60).padStart(2, '0')}`;
}

export function errorCode(error: unknown): ApiError {
    if (axios.isAxiosError(error)) {
        const data = (error.response?.data ?? {}) as {message?: string; code?: string; retryAfter?: number};
        return {
            status: error.response?.status ?? null,
            code: data.code ?? null,
            message: data.message ?? 'Something went wrong. Try again.',
            retryAfter: data.retryAfter ?? null,
        };
    }
    return {status: null, code: null, message: 'Something went wrong. Try again.', retryAfter: null};
}

export function badgeFor(state: PanelState): {text: string; cls: string} {
    switch (state) {
        case 'outside':
            return {text: 'Outside the party', cls: 'bg-warning text-warning-fg'};
        case 'starting':
            return {text: 'Starting', cls: 'bg-primary text-primary-fg'};
        case 'no_device':
            return {text: 'No device', cls: 'bg-danger text-danger-fg'};
        case 'premium_required':
            return {text: 'Premium required', cls: 'bg-danger text-danger-fg'};
        case 'relink_required':
            return {text: 'Re-link needed', cls: 'bg-warning text-warning-fg'};
        case 'rate_limited':
            return {text: 'Spotify busy', cls: 'bg-warning text-warning-fg'};
        default:
            return {text: 'Not playing', cls: 'bg-secondary text-secondary-fg'};
    }
}

export function startLabel(state: PanelState, target: DashboardStartTarget | null, host: string): string {
    if (state === 'starting') {
        return `Starting on ${host}'s Spotify…`;
    }
    if (state === 'resume' && target !== null) {
        return `Resume ${target.track.name} at ${formatMs(target.positionMs)}`;
    }
    return `Start on ${host}'s Spotify`;
}

export function deviceIcon(device: DashboardDevice): string {
    return device.icon || 'ti-device-desktop';
}

export function reloadDashboard(only: string[] = RELOAD_ALL): void {
    router.reload({only});
}

export async function sendControl(
    code: string,
    action: ControlAction,
    extra: {positionMs?: number; deviceId?: string} = {},
): Promise<void> {
    const route = control({party: code});
    await axios.request({url: route.url, method: route.method, data: {action, ...extra}});
}

export async function startPlayback(
    code: string,
    deviceId: string | null,
    mode: 'start' | 'restart' = 'start',
): Promise<void> {
    const route = start({party: code});
    await axios.request({url: route.url, method: route.method, data: {deviceId, mode}});
}

export async function fetchDevices(code: string): Promise<DashboardDevice[]> {
    const route = devices({party: code});
    const response = await axios.request({url: route.url, method: route.method});
    return (response.data as {data: DashboardDevice[]}).data;
}

export async function activateParty(code: string): Promise<void> {
    const route = activate({party: code});
    await axios.request({url: route.url, method: route.method});
}

export async function playRequest(code: string, id: number): Promise<void> {
    const route = play({party: code, upcomingsong: id});
    await axios.request({url: route.url, method: route.method});
}

export async function deleteRequest(code: string, id: number): Promise<void> {
    const route = destroy({party: code, upcomingsong: id});
    await axios.request({url: route.url, method: route.method});
}

export async function approveFlagged(code: string, id: number): Promise<void> {
    await axios.post(flaggedApproveUrl(code, id));
}

export async function rejectFlagged(code: string, id: number): Promise<void> {
    await axios.post(flaggedRejectUrl(code, id));
}

export function useRun(): (action: () => Promise<unknown>, only?: string[]) => Promise<boolean> {
    const notify = inject(notifyKey, () => undefined);

    return async (action, only) => {
        try {
            await action();
            reloadDashboard(only);
            return true;
        } catch (error) {
            notify(errorCode(error).message);
            return false;
        }
    };
}
