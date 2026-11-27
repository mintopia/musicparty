export interface ParsedSearch {
    text: string;
    tokens: Record<string, string[]>;
}

const WORD = /(?:[^\s"]|"[^"]*"?)+/g;
const TOKEN = /^([A-Za-z][\w-]*):(.+)$/s;

function unquote(value: string): string {
    return value.replace(/"([^"]*)"?/g, '$1');
}

function quote(value: string): string {
    return /[\s"]/.test(value) ? `"${value.replace(/"/g, '')}"` : value;
}

export function parseSearch(query: string, allowedKeys: readonly string[]): ParsedSearch {
    const allowed = new Set(allowedKeys.map((key) => key.toLowerCase()));
    const words: string[] = [];
    const tokens: Record<string, string[]> = {};

    for (const word of query.match(WORD) ?? []) {
        const match = TOKEN.exec(word);
        const key = match?.[1].toLowerCase();
        const value = match ? unquote(match[2]) : '';
        if (key !== undefined && allowed.has(key) && value !== '') {
            (tokens[key] ??= []).includes(value) || tokens[key].push(value);
        } else {
            words.push(word);
        }
    }

    return {text: words.join(' '), tokens};
}

export function serializeSearch(search: ParsedSearch): string {
    const parts = search.text === '' ? [] : [search.text];
    for (const [key, values] of Object.entries(search.tokens)) {
        for (const value of values) {
            parts.push(`${key}:${quote(value)}`);
        }
    }
    return parts.join(' ');
}

export function hasToken(query: string, key: string, value: string): boolean {
    return parseSearch(query, [key]).tokens[key.toLowerCase()]?.includes(value) ?? false;
}

export function toggleToken(query: string, key: string, value: string): string {
    const name = key.toLowerCase();
    const search = parseSearch(query, [name]);
    const values = search.tokens[name] ?? [];
    const next = values.includes(value) ? values.filter((v) => v !== value) : [...values, value];
    if (next.length > 0) {
        search.tokens[name] = next;
    } else {
        delete search.tokens[name];
    }
    return serializeSearch(search);
}
