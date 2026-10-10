export const SLOT_NAMES = ['settings', 'queue-item', 'now-playing'];

export const createModSlots = (modules) => {
    const registry = Object.fromEntries(SLOT_NAMES.map((name) => [name, []]));

    for (const [path, module] of Object.entries(modules)) {
        const modId = path.match(/mods\/([^/]+)\/index\.js$/)?.[1] ?? path;
        const slots = module?.default?.slots ?? {};
        for (const [name, component] of Object.entries(slots)) {
            if (SLOT_NAMES.includes(name) && component) {
                registry[name].push({modId, component});
            }
        }
    }

    return {
        contributions: (name, enabledMods = []) =>
            (registry[name] ?? []).filter((entry) => enabledMods.includes(entry.modId)),
    };
};

export default createModSlots(import.meta.glob('../mods/*/index.js', {eager: true}));
