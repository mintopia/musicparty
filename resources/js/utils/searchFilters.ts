import {parseSearch, serializeSearch} from './searchTokens';

export type TokenSpec = Record<string, readonly string[] | null>;

export interface ResolvedSearch {
    text: string;
    filters: Record<string, string>;
}

export function resolveSearch(query: string, spec: TokenSpec, aliases: Record<string, string> = {}): ResolvedSearch {
    const keys = Object.keys(spec);
    const {text, tokens} = parseSearch(query, keys);
    const filters: Record<string, string> = {};
    const leftovers: string[] = [];

    for (const key of keys) {
        const allowed = spec[key];
        for (const value of tokens[key] ?? []) {
            const lower = value.toLowerCase();
            const canonical = aliases[lower] ?? lower;
            if (allowed === null) {
                filters[key] = value;
            } else if (allowed.includes(canonical)) {
                filters[key] = canonical;
            } else {
                leftovers.push(serializeSearch({text: '', tokens: {[key]: [value]}}));
            }
        }
    }

    return {text: [text, ...leftovers].filter((part) => part !== '').join(' '), filters};
}

export function composeSearch(text: string, filters: Record<string, string>): string {
    const tokens: Record<string, string[]> = {};
    for (const [key, value] of Object.entries(filters)) {
        if (value !== '') {
            tokens[key] = [value];
        }
    }
    return serializeSearch({text, tokens});
}

export function setSingleToken(
    query: string,
    spec: TokenSpec,
    key: string,
    value: string,
    aliases: Record<string, string> = {},
): string {
    const {text, filters} = resolveSearch(query, spec, aliases);
    if (filters[key] === value) {
        delete filters[key];
    } else {
        filters[key] = value;
    }
    return composeSearch(text, filters);
}
