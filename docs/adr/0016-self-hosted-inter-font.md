# Inter is self-hosted with @fontsource-variable/inter

`resources/sass/app.scss` loaded Inter with `@import url('https://rsms.me/inter/inter.css')`. That request blocks rendering until a third-party host answers, and it fails at LAN parties with no internet, leaving every page on the system fallback font and shifting the layout when the real font does arrive.

We install `@fontsource-variable/inter` and import its `index.css` from `app.scss`. Vite bundles the woff2 files into the build, so the font ships from our own origin. The package declares `font-display: swap`, so text is visible immediately in the fallback and swaps when Inter loads, and it splits the font by `unicode-range`, so a browser downloads only the scripts a page actually uses.

The package registers the family as `Inter Variable`. `--mp-font-family` in `_tokens.scss` lists that name first, then `Inter`, then a system stack (`ui-sans-serif`, `system-ui`, `-apple-system`, `Segoe UI`, `Roboto`, `Helvetica Neue`, `Arial`), so a failed download degrades to the platform UI font.

There is no first-party Laravel package for web fonts. Fontsource is a plain npm dependency with no runtime code, which fits the existing Vite pipeline better than committing font binaries to the repository by hand.

The cost is about 225 KB of woff2 in `public/build` and a dependency to keep current. In return the font works offline, no third party sees our visitors' requests, and first paint no longer waits on `rsms.me`.
