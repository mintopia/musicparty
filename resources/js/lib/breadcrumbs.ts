import {home} from '@/routes';
import {dashboard} from '@/routes/admin';
import {index as adminSettings} from '@/routes/admin/settings';
import {index as adminUsers, show as adminUser} from '@/routes/admin/users';
import {show} from '@/routes/parties';
import {index as moderationIndex} from '@/routes/parties/moderation';

export interface BreadcrumbItem {
    label: string;
    href?: string;
}

export function homeCrumbs(): BreadcrumbItem[] {
    return [{label: 'Home', href: home().url}];
}

export function partyCrumbs(party: {code: string; name: string}): BreadcrumbItem[] {
    return [...homeCrumbs(), {label: party.name, href: show(party.code).url}];
}

export function moderationCrumbs(party: {code: string; name: string}): BreadcrumbItem[] {
    return [...partyCrumbs(party), {label: 'Moderation', href: moderationIndex(party.code).url}];
}

export function adminCrumbs(): BreadcrumbItem[] {
    return [...homeCrumbs(), {label: 'Admin', href: dashboard().url}];
}

export function adminSettingsCrumbs(): BreadcrumbItem[] {
    return [...adminCrumbs(), {label: 'Settings', href: adminSettings().url}];
}

export function adminUserCrumbs(user?: {id: number; nickname: string}): BreadcrumbItem[] {
    const users = {label: 'Users', href: adminUsers().url};
    return user
        ? [...adminCrumbs(), users, {label: user.nickname, href: adminUser(user.id).url}]
        : [...adminCrumbs(), users];
}
