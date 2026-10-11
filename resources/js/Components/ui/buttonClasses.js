const base = 'inline-flex items-center justify-center gap-2 rounded border px-4 py-2 text-sm font-medium transition-colors';

const variants = {
    primary: 'border-primary bg-primary text-white hover:opacity-90',
    'outline-primary': 'border-primary bg-transparent text-primary hover:bg-primary/10',
    'outline-danger': 'border-danger bg-transparent text-danger hover:bg-danger/10',
    danger: 'border-danger bg-danger text-white hover:opacity-90',
    secondary: 'border-border bg-surface text-text hover:bg-background',
    link: 'border-transparent bg-transparent text-primary hover:underline',
};

export const buttonClasses = (variant = 'primary', disabled = false) => [
    base,
    variants[variant] ?? variants.primary,
    disabled ? 'pointer-events-none cursor-not-allowed opacity-50' : '',
];
