export function timezoneOffsetLabel(identifier: string): string {
    try {
        const part = new Intl.DateTimeFormat('en', {timeZone: identifier, timeZoneName: 'longOffset'})
            .formatToParts(new Date())
            .find((p) => p.type === 'timeZoneName')?.value;
        const match = part?.match(/^GMT(?:([+-])(\d\d):(\d\d))?$/);
        if (!match) {
            return '';
        }
        if (!match[1]) {
            return 'UTC+0';
        }
        const minutes = match[3] === '00' ? '' : `:${match[3]}`;
        return `UTC${match[1]}${Number(match[2])}${minutes}`;
    } catch {
        return '';
    }
}
