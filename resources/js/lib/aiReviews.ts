import type {AiReviewOutcome} from '@/types';

const base = (code: string): string => `/parties/${code}/ai-reviews`;

export const aiReviewsUrl = (code: string): string => base(code);
export const aiReviewUrl = (code: string, id: number, query: Record<string, string | number | null> = {}): string => {
    const params = new URLSearchParams();
    for (const [key, value] of Object.entries(query)) {
        if (value !== null && value !== '' && !(key === 'page' && value === 1)) {
            params.set(key, String(value));
        }
    }
    const suffix = params.toString();
    return `${base(code)}/${id}${suffix === '' ? '' : `?${suffix}`}`;
};

export const outcomeBadge: Record<AiReviewOutcome, {class: string; icon: string}> = {
    allow: {class: 'bg-green-lt', icon: 'ti-check'},
    flag: {class: 'bg-yellow-lt', icon: 'ti-flag'},
    reject: {class: 'bg-red-lt', icon: 'ti-ban'},
    error: {class: 'bg-orange-lt', icon: 'ti-alert-triangle'},
    skipped: {class: 'bg-secondary-lt', icon: 'ti-player-skip-forward'},
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
        const blocks: TextBlock[] = [];
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

export interface ProbabilityRow {
    key: string;
    label: string;
    percent: number;
}

export interface AnswerRow {
    id: string;
    value: string;
    confidence: string | null;
    probabilities: ProbabilityRow[];
}

export const formatPercent = (fraction: number): string => `${Math.round(fraction * 100)}%`;

function probabilityRows(value: unknown, labelFor: (key: string) => string): ProbabilityRow[] {
    if (value === null || typeof value !== 'object' || Array.isArray(value)) {
        return [];
    }
    return Object.entries(value as Record<string, unknown>)
        .filter((entry): entry is [string, number] => typeof entry[1] === 'number')
        .sort((a, b) => b[1] - a[1])
        .map(([key, probability]) => ({key, label: labelFor(key), percent: Math.round(probability * 100)}));
}

const scalar = (value: unknown): string | null =>
    typeof value === 'string' || typeof value === 'number' || typeof value === 'boolean' ? String(value) : null;

export function answerRows(
    response: Record<string, unknown> | null,
    criterionLabels: Record<string, string> = {},
): AnswerRow[] {
    const answers = response?.answers;
    if (answers === null || typeof answers !== 'object') {
        return [];
    }
    const entries: [string, unknown][] = Array.isArray(answers)
        ? answers.map((answer, index): [string, unknown] => [
              scalar((answer as Record<string, unknown> | null)?.id) ??
                  scalar((answer as Record<string, unknown> | null)?.question) ??
                  String(index + 1),
              answer,
          ])
        : Object.entries(answers);

    return entries.map(([id, answer]) => {
        if (answer === null || typeof answer !== 'object') {
            return {id, value: scalar(answer) ?? '-', confidence: null, probabilities: []};
        }
        const record = answer as Record<string, unknown>;
        const choice = scalar(record.choice);
        const value =
            record.type === 'refusal'
                ? 'Refused'
                : ((id === 'criterion' && choice !== null ? criterionLabels[choice] : undefined) ??
                  choice ??
                  scalar(record.value) ??
                  scalar(record.score) ??
                  scalar(record.answer) ??
                  scalar(record.selected));
        return {
            id,
            value: value ?? prettyJson(answer),
            confidence:
                typeof record.confidence === 'number' ? formatPercent(record.confidence) : scalar(record.confidence),
            probabilities: probabilityRows(record.probabilities, (key) =>
                id === 'criterion' ? (criterionLabels[key] ?? key) : record.type === 'score' ? `Level ${key}` : key,
            ),
        };
    });
}
