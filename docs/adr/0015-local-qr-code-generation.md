# Local QR code generation: render the TV join QR as SVG on the server with bacon/bacon-qr-code

The TV page used to fetch its join QR code from `api.qrserver.com`, sending the party URL to a third party and failing at LAN parties with no internet. The QR is now generated locally.

**Server-side SVG.** `App\Support\QrCode::svg()` renders the party URL with `bacon/bacon-qr-code` and its `SvgImageBackEnd`, so no Imagick or GD extension is needed. `PartyController::tv` passes the result as the `party.qrSvg` Inertia prop, and `TvPlayer.vue` shows it as an `<img>` with a `data:image/svg+xml` source so it can carry an `alt`. Nothing is fetched at runtime and the client bundle does not grow.

**Why not a first-party Laravel package.** No first-party Laravel package generates QR codes, and neither Socialite, Fortify nor Sanctum includes one. `bacon/bacon-qr-code` is the library Laravel's own Fortify two-factor flow uses, which makes it the closest thing to a framework-blessed choice.

**Why not a JavaScript library.** A client library would add bundle weight to every page that shares the entry chunk or to a new async chunk, and would regenerate the code in each browser. The URL is known on the server and is stable per party, so rendering it there is cheaper.

**Consequence.** The QR is regenerated on every TV page load; the cost is negligible next to the rest of the request, and the SVG is not cached.
