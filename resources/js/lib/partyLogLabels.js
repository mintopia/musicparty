import labels from './partyLogLabels.json';

const formatValue = (value) => (value !== null && typeof value === 'object' ? JSON.stringify(value) : String(value));

const valuesFor = (entry) => {
    const details = entry.details ?? {};
    return {
        subject: entry.subject,
        old: 'old' in details ? details.old ?? '' : undefined,
        new: 'new' in details ? details.new ?? '' : undefined,
        changes: details.changes ? Object.keys(details.changes).join(', ') : undefined,
    };
};

const fill = (template, values, requireAll) => {
    let missing = false;
    const text = template.replace(/\{(\w+)(?:\|([^}]*))?\}/g, (_, name, fallback) => {
        const value = values[name];
        if (value === undefined || value === null) {
            if (fallback === undefined) {
                missing = true;
                return '';
            }
            return fallback;
        }
        return formatValue(value);
    });
    return requireAll && missing ? null : text;
};

export const hasPartyLogLabel = (action) => Object.hasOwn(labels, action);

export const describePartyLogEntry = (entry) => {
    if (!hasPartyLogLabel(entry.action)) {
        return entry.action;
    }
    const values = valuesFor(entry);
    const template = labels[entry.action].replace(/\[([^\]]*)\]/g, (_, optional) => fill(optional, values, true) ?? '');
    return fill(template, values, false).replace(/\s+/g, ' ').trim();
};
