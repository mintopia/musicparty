// Renders each [data-frame] <template> into an iframe at the device's real width, so the app's media
// queries respond to the frame, not the sheet. Append ?capture to the URL to render frames at 1:1.
(() => {
    const SIZES = {
        desktop: {width: 1440, height: 900, scale: 0.75},
        phone: {width: 390, height: 844, scale: 1},
        tv: {width: 1920, height: 1080, scale: 0.5, fixed: true},
    };
    const capture = new URLSearchParams(location.search).has('capture');
    if (capture) {
        document.documentElement.dataset.capture = 'true';
    }
    const base = new URL('assets/', document.currentScript.src.replace(/assets\/mockup\.js.*$/, ''));
    const styles = ['app.css', 'components.css', 'mockup.css'];

    const pending = [];

    for (const frame of document.querySelectorAll('[data-frame]')) {
        const template = frame.querySelector('template');
        if (!template) {
            continue;
        }
        const size = SIZES[frame.dataset.frame] ?? SIZES.desktop;
        const width = Number(frame.dataset.width ?? size.width);
        const minHeight = Number(frame.dataset.height ?? size.height);
        const fixed = size.fixed || frame.dataset.fixed !== undefined;
        const scale = capture ? 1 : Number(frame.dataset.scale ?? size.scale);
        const theme = frame.dataset.theme ?? 'light';
        const extra = (frame.dataset.css ?? '').split(/\s+/).filter(Boolean);

        const head = [...styles, ...extra]
            .map((file) => `<link rel="stylesheet" href="${new URL(file, base)}">`)
            .join('');
        // The app sets data-bs-theme="dark" on <body> when the active theme has Dark Mode on (app.blade.php).
        const bodyTheme = theme === 'dark' ? ' data-bs-theme="dark"' : '';
        const html = `<!doctype html><html lang="en"><head><meta charset="utf-8">`
            + `<meta name="viewport" content="width=device-width, initial-scale=1">${head}</head>`
            + `<body class="${frame.dataset.bodyClass ?? ''}"${bodyTheme}>${template.innerHTML}</body></html>`;

        const screen = document.createElement('div');
        screen.className = 'mock-screen';
        const iframe = document.createElement('iframe');
        iframe.title = frame.querySelector('figcaption b')?.textContent ?? frame.id;
        iframe.width = width;
        iframe.height = minHeight;
        iframe.style.width = `${width}px`;
        iframe.style.height = `${minHeight}px`;
        iframe.style.transform = `scale(${scale})`;
        screen.style.width = `${width * scale}px`;
        screen.style.height = `${minHeight * scale}px`;
        screen.appendChild(iframe);
        template.replaceWith(screen);

        pending.push(new Promise((resolve) => {
            iframe.addEventListener('load', async () => {
                const doc = iframe.contentDocument;
                await doc.fonts?.ready;
                if (!fixed && doc) {
                    const height = Math.max(minHeight, doc.documentElement.scrollHeight);
                    iframe.style.height = `${height}px`;
                    screen.style.height = `${height * scale}px`;
                }
                resolve();
            }, {once: true});
        }));
        iframe.srcdoc = html;
    }

    window.mockReady = Promise.all(pending).then(() => {
        document.documentElement.dataset.mockReady = 'true';
    });
})();
