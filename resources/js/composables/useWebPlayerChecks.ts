import {computed} from 'vue';
import type {Ref} from 'vue';
import {useTicker} from '@/composables/useProgress';
import type {SpotifyTrack} from '@/lib/spotifyPlayer';
import {create as linkAccount} from '@/routes/linkedaccounts';

export type WebPlayerCheck = {
    label: string;
    detail: string;
    tone: string;
    action?: {label: string; href: string; primary: boolean};
};

export type PlayerErrorKind = 'premium' | 'login' | 'generic';

export type WebPlayerCheckState = {
    status: Ref<string>;
    error: Ref<string | null>;
    errorKind: Ref<PlayerErrorKind>;
    track: Ref<SpotifyTrack | null>;
    paused: Ref<boolean>;
    active: Ref<boolean>;
    takenOver: Ref<boolean>;
    deviceReady: Ref<boolean>;
    tokenOk: Ref<boolean>;
    lastUpdateAt: Ref<number | null>;
};

const SPOTIFY_ACCOUNT_URL = 'https://www.spotify.com/account/';
const PREMIUM_COPY =
    'The linked Spotify account is on the Free plan, and Spotify only lets Premium accounts play in a browser. Upgrade the account, or play on another device from the Dashboard.';
const LOGIN_COPY =
    "Spotify stopped accepting the saved login, so this tab can't play. Log in again and playback resumes here. Nobody else needs to do anything.";

export function useWebPlayerChecks(state: WebPlayerCheckState, deviceName: string) {
    const {status, error, errorKind, track, paused, active, takenOver, deviceReady, tokenOk, lastUpdateAt} = state;
    const updateTicker = useTicker(
        () => lastUpdateAt.value !== null,
        () => lastUpdateAt.value,
    );
    const lastUpdateLabel = computed(() => {
        if (lastUpdateAt.value === null) {
            return 'Never';
        }
        const seconds = Math.max(0, Math.floor((updateTicker.value - lastUpdateAt.value) / 1000));
        if (seconds < 60) {
            return `${seconds} ${seconds === 1 ? 'second' : 'seconds'} ago`;
        }
        const minutes = Math.floor(seconds / 60);

        return `${minutes} ${minutes === 1 ? 'minute' : 'minutes'} ago`;
    });
    const reporting = computed(() => deviceReady.value && !takenOver.value && error.value === null);
    const spotifyLoginUrl = linkAccount('spotify').url;
    const errorTitle = computed(() =>
        errorKind.value === 'premium'
            ? 'Spotify Premium needed'
            : errorKind.value === 'login'
              ? 'Log in to Spotify again'
              : "This tab can't play",
    );
    const errorCopy = computed(() =>
        errorKind.value === 'premium' ? PREMIUM_COPY : errorKind.value === 'login' ? LOGIN_COPY : (error.value ?? ''),
    );
    const errorIcon = computed(() =>
        errorKind.value === 'login'
            ? 'ti-key-off'
            : errorKind.value === 'premium'
              ? 'ti-brand-spotify'
              : 'ti-alert-triangle',
    );
    const spotifyCheck = computed<WebPlayerCheck>(() => {
        if (error.value !== null && errorKind.value === 'premium') {
            return {
                label: 'Spotify link',
                detail: 'Connected, but the account is Spotify Free',
                tone: 'red',
                action: {label: 'Open Spotify account', href: SPOTIFY_ACCOUNT_URL, primary: false},
            };
        }
        if (error.value !== null && errorKind.value === 'login') {
            return {
                label: 'Spotify link',
                detail: 'Spotify no longer accepts the saved login',
                tone: 'red',
                action: {label: 'Log in with Spotify', href: spotifyLoginUrl, primary: true},
            };
        }
        if (error.value !== null) {
            return {label: 'Spotify link', detail: error.value, tone: 'red'};
        }

        return tokenOk.value
            ? {label: 'Spotify link', detail: 'Connected', tone: 'green'}
            : {label: 'Spotify link', detail: 'Connecting...', tone: 'secondary'};
    });
    const deviceCheck = computed<WebPlayerCheck>(() => {
        if (takenOver.value) {
            return {
                label: 'Device',
                detail: `${deviceName} (this tab) is no longer the active Web Player`,
                tone: 'yellow',
            };
        }

        return deviceReady.value
            ? {label: 'Device', detail: `${deviceName} (this tab)`, tone: 'green'}
            : {label: 'Device', detail: 'Not registered', tone: 'secondary'};
    });
    const reportingCheck = computed<WebPlayerCheck>(() => {
        if (reporting.value) {
            return {label: 'Reporting', detail: 'Reporting to Music Party', tone: 'green'};
        }

        return takenOver.value
            ? {label: 'Reporting', detail: 'This tab stopped reporting', tone: 'secondary'}
            : {label: 'Reporting', detail: 'Not reporting to Music Party', tone: 'red'};
    });
    const updateCheck = computed<WebPlayerCheck>(() => {
        if (lastUpdateAt.value === null) {
            return {label: 'Last update', detail: 'Never', tone: 'secondary'};
        }
        if (error.value !== null && errorKind.value === 'login') {
            return {label: 'Last update', detail: `${lastUpdateLabel.value}, before the login expired`, tone: 'yellow'};
        }

        return {label: 'Last update', detail: lastUpdateLabel.value, tone: 'green'};
    });
    const checks = computed<WebPlayerCheck[]>(() => {
        if (takenOver.value) {
            return [
                deviceCheck.value,
                {label: 'Active Web Player', detail: 'Another Web Player has claimed this party', tone: 'green'},
                reportingCheck.value,
                spotifyCheck.value,
            ];
        }
        if (error.value !== null) {
            return [spotifyCheck.value, deviceCheck.value, reportingCheck.value, updateCheck.value];
        }

        return [deviceCheck.value, reportingCheck.value, updateCheck.value, spotifyCheck.value];
    });
    const pillLabel = computed(() => {
        if (takenOver.value) {
            return 'Taken over';
        }
        if (error.value !== null) {
            return 'Not connected';
        }
        if (track.value !== null && active.value) {
            return paused.value ? 'Paused' : 'Playing in this tab';
        }

        return status.value;
    });
    const pillWarn = computed(() => takenOver.value || error.value !== null || !active.value);

    return {
        errorTitle,
        errorCopy,
        errorIcon,
        spotifyCheck,
        checks,
        pillLabel,
        pillWarn,
    };
}
