import type {AiReviewOutcome} from '@/types';

const base = (code: string): string => `/parties/${code}/ai-reviews`;

export const aiReviewsUrl = (code: string): string => base(code);
export const aiReviewUrl = (code: string, id: number): string => `${base(code)}/${id}`;

export const outcomeBadge: Record<AiReviewOutcome, {class: string; icon: string}> = {
    allow: {class: 'bg-green-lt', icon: 'ti-check'},
    flag: {class: 'bg-yellow-lt', icon: 'ti-flag'},
    reject: {class: 'bg-red-lt', icon: 'ti-ban'},
    error: {class: 'bg-orange-lt', icon: 'ti-alert-triangle'},
};

export interface TextBlock {
    key: string;
    title: string;
    text: string;
}

function parseJson(value: unknown): unknown {
    if (typeof value !== 'string') {
        return value;
    }
    try {
        return JSON.parse(value);
    } catch {
        return value;
    }
}

export function prettyJson(value: unknown): string {
    return JSON.stringify(value, null, 2) ?? '';
}

export function requestBlocks(request: Record<string, unknown>): TextBlock[] {
    const messages = request.messages;
    if (Array.isArray(messages)) {
        const blocks: TextBlock[] = [{key: 'model', title: 'Model', text: String(request.model ?? '')}];
        for (const [index, message] of messages.entries()) {
            const entry = message as {role?: string; content?: unknown};
            const isUser = entry.role === 'user';
            blocks.push({
                key: `message-${index}`,
                title: isUser ? 'User message' : 'System message',
                text: isUser ? prettyJson(parseJson(entry.content)) : String(entry.content ?? ''),
            });
        }
        return blocks;
    }

    return [{key: 'body', title: 'Request body', text: prettyJson({...request, state: parseJson(request.state)})}];
}

export function formatTokens(value: number | null): string {
    return value === null ? '-' : value.toLocaleString();
}

export function formatLatency(ms: number): string {
    return ms >= 1000 ? `${(ms / 1000).toFixed(1)} s` : `${ms} ms`;
}
