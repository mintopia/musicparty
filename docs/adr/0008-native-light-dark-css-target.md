# CSS is built for browsers with native light-dark()

Tabler 1.6 defines about 70 theme tokens with `light-dark()`. With Vite's default CSS target, Lightning CSS lowers each to `--lightningcss-light` and `--lightningcss-dark` fallbacks. Those resolve once on `:root`, so a `[data-bs-theme=dark]` scope inherits the light value: the dark sidebar and page rendered with light surface, text and border colours.

We set `build.cssTarget` to Chrome 123, Firefox 120 and Safari 17.5 (`vite.css-target.ts`), the first versions with native `light-dark()`. Lightning CSS then leaves `light-dark()` untouched and the browser resolves it per element.

The cost is that older browsers get no colours from these tokens. This is acceptable for a LAN-party app whose users run current browsers. A vitest build of the real stylesheet fails if any `--lightningcss-` fallback reappears, for example if the target is lowered.

Our own `--mp-*` colour defaults do not use `light-dark()`. They are defined per theme scope as `--mp-default-*` and read through `var(--mp-x, var(--mp-default-x))`, so a saved theme that sets `--mp-x` on `:root` still overrides them in every scope.
