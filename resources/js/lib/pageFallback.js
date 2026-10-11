const titles = {
    Home: 'Home',
    Login: 'Login',
    Signup: 'Sign up',
    'Party/Show': 'Queue',
    'Party/Create': 'Create Party',
    'Party/Blocklist': 'Blocklist',
    'Party/BrowserPlayer': 'Web Player',
    'Party/Log': 'Log',
    'Party/Members': 'Users',
    'Party/Mods': 'Mods',
    'Party/Pending': 'Pending requests',
    'Party/Settings': 'Settings',
    'Party/Stats': 'Stats',
    'Party/Theme': 'Theme',
    'Party/Tv': 'TV Mode',
};

const humanise = (name) => name.replace(/([a-z])([A-Z])/g, '$1 $2');
const home = {label: 'Home', href: '/'};

export const pageFallback = (component, props = {}) => {
    const name = component ?? '';

    if (name.startsWith('Admin/')) {
        const parts = name.split('/').slice(1);
        const title = parts[0] === 'Dashboard' ? 'Dashboard' : humanise(parts[0] ?? 'Admin');
        const crumbs = [home, {label: 'Admin', href: '/admin'}];
        if (parts[0] !== 'Dashboard') {
            crumbs.push({label: title});
        }
        return {title, crumbs};
    }

    if (name.startsWith('Party/')) {
        const title = titles[name] ?? humanise(name.split('/').pop());
        const party = props.party;
        const crumbs = [home];
        if (party?.name) {
            crumbs.push({label: party.name, href: party.code ? `/parties/${party.code}` : undefined});
        }
        crumbs.push({label: title});
        return {title, crumbs};
    }

    const title = titles[name] ?? humanise(name.split('/').pop() || 'Home');
    return {title, crumbs: title === 'Home' ? [{label: 'Home'}] : [home, {label: title}]};
};
