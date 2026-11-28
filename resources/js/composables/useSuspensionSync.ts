import {usePage} from '@inertiajs/vue3';
import {useEcho} from '@/composables/useEcho';
import type {SharedProps} from '@/types';

type UserChannel = {listen(event: string, cb: () => void): unknown};

export function useSuspensionSync(): void {
    const page = usePage<SharedProps>();
    const user = page.props.auth?.user;
    if (!user || !page.props.reverb) {
        return;
    }

    const channel = useEcho().privateChannel(`user.${user.id}`) as UserChannel;
    channel.listen('UserSuspended', () => window.location.reload());
}
