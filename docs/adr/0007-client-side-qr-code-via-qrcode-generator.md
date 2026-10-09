# Client-side QR code via qrcode-generator

The TV screen must show a QR code that joins the Party. No first-party Laravel package generates QR codes, and the TV page already runs in the browser.

Decision:
- Add the zero-dependency npm package `qrcode-generator` and render the QR as inline SVG in a Vue component.
- Generate it client-side from the Party's join URL, so no PHP dependency or image endpoint is needed.

Revisit if a first-party Laravel QR package appears or the QR is needed in server-rendered output.
