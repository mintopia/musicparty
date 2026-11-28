import {dashboard as adminDashboard} from '@/routes/admin';
import {index as adminSettings} from '@/routes/admin/settings';
import {index as adminUsers} from '@/routes/admin/users';
import {index as horizon} from '@/routes/horizon';
import {create as createParty, dashboard, edit, search, show} from '@/routes/parties';
import {index as moderation} from '@/routes/parties/moderation';
import {index as songs} from '@/routes/parties/songs';
import {index as partyUsers} from '@/routes/parties/users';
import {home, login, logout, pulse} from '@/routes';
import {profile} from '@/routes/user';
import {aiReviewsUrl} from '@/lib/aiReviews';
import {flaggedUrl} from '@/lib/flagged';
import {adminModsUrl, partyModsUrl} from '@/lib/mods';
import type {PartyMembership, SharedProps} from '@/types';

export interface NavLink {
    title: string;
    href: string;
    badge?: number;
    match?: string[];
    exact?: boolean;
}

export interface NavItem {
    key: string;
    title: string;
    icon: string;
    href?: string;
    method?: 'post';
    children?: NavLink[];
}

export function partyLinks(party: PartyMembership): NavLink[] {
    const links: NavLink[] = [
        {title: 'Queue', href: show(party.code).url, exact: true},
        {title: 'Search', href: search(party.code).url},
    ];

    if (party.canManage || party.canModerate) {
        links.unshift({title: 'Dashboard', href: dashboard(party.code).url});
        links.push(
            {title: 'Requests', href: songs(party.code).url},
            {
                title: 'Moderation',
                href: flaggedUrl(party.code),
                badge: party.flaggedCount,
                match: [flaggedUrl(party.code), moderation(party.code).url, aiReviewsUrl(party.code)],
            },
            {title: 'Members', href: partyUsers(party.code).url},
        );
    }

    if (party.canManage) {
        links.push({
            title: 'Settings',
            href: edit(party.code).url,
            match: [edit(party.code).url, partyModsUrl(party.code)],
        });
    }

    return links;
}

export function adminLinks(): NavLink[] {
    return [
        {title: 'Dashboard', href: adminDashboard().url},
        {title: 'Users', href: adminUsers().url},
        {title: 'Settings', href: adminSettings().url},
        {title: 'Mods', href: adminModsUrl()},
        {title: 'Pulse', href: pulse().url},
        {title: 'Horizon', href: horizon().url},
    ];
}

type NavigationProps = Pick<SharedProps, 'auth' | 'site'>;

export function buildNavigation(props: NavigationProps): NavItem[] {
    const items: NavItem[] = [];

    if (!props.site.defaultParty) {
        items.push({key: 'home', title: 'Home', icon: 'ti-home', href: home().url});
    }

    for (const party of props.auth.parties) {
        items.push({key: `party:${party.code}`, title: party.name, icon: 'ti-playlist', children: partyLinks(party)});
    }

    if (props.auth.can.createParty) {
        items.push({key: 'party.create', title: 'Create Party', icon: 'ti-playlist-add', href: createParty().url});
    }

    if (!props.auth.user) {
        items.push({key: 'login', title: 'Login', icon: 'ti-login', href: login().url});
        return items;
    }

    items.push({key: 'profile', title: 'Profile', icon: 'ti-user', href: profile().url});

    if (props.auth.can.admin) {
        items.push({key: 'admin', title: 'Admin', icon: 'ti-settings', children: adminLinks()});
    }

    items.push({key: 'logout', title: 'Logout', icon: 'ti-logout', href: logout().url, method: 'post'});

    return items;
}

function matchesPath(path: string, base: string): boolean {
    const normalised = path.length > 1 ? path.replace(/\/+$/, '') : path;
    return normalised === base || normalised.startsWith(`${base}/`);
}

export function isActive(item: NavItem, currentUrl: string): boolean {
    const path = currentUrl.split('?')[0] ?? currentUrl;

    if (item.children) {
        if (item.key.startsWith('party:')) {
            return matchesPath(path, show(item.key.slice('party:'.length)).url);
        }
        return item.children.some((child) => matchesPath(path, child.href));
    }

    return item.href !== undefined && matchesPath(path, item.href);
}

export function isLinkActive(link: NavLink, currentUrl: string): boolean {
    const path = currentUrl.split('?')[0] ?? currentUrl;

    if (link.exact) {
        return path.replace(/\/+$/, '') === link.href;
    }

    return (link.match ?? [link.href]).some((base) => matchesPath(path, base));
}
