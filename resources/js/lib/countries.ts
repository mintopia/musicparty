export interface CountryOption {
    code: string;
    name: string;
}

export function countryOptionsFor(current: string | null): CountryOption[] {
    const names = new Intl.DisplayNames(['en'], {type: 'region'});
    const letters = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ';
    const options: CountryOption[] = [];
    for (const a of letters) {
        for (const b of letters) {
            const code = a + b;
            const name = names.of(code);
            if (name && name !== code) {
                options.push({code, name});
            }
        }
    }
    if (current && !options.some((o) => o.code === current)) {
        options.push({code: current, name: current});
    }
    return options.sort((x, y) => x.name.localeCompare(y.name));
}
