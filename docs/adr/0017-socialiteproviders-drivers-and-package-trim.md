# SocialiteProviders drivers for supported logins only; no eloquent-sortable or direct ramsey/uuid

Laravel Socialite has no first-party drivers for Twitch, Steam or Laravel Passport, and its Spotify and Discord support comes from the community `socialiteproviders/*` packages. The app requires five of them. It also requires `spatie/eloquent-sortable`, used only to order `Setting` and `ProviderSetting` rows in admin screens, and `ramsey/uuid`, which nothing imports directly (Laravel already depends on it). None of this was recorded.

Decision:
- Keep one `socialiteproviders/*` driver for each login provider in the admin provider catalogue: Discord, Twitch, Steam, Spotify and Laravel Passport. A driver whose provider leaves the catalogue is removed in the same change. A test asserts that every catalogue entry resolves its driver and that every required driver has a catalogue entry.
- Remove `spatie/eloquent-sortable`. Settings keep their `order` column and are sorted with an ordinary `orderBy` scope.
- Remove the direct `ramsey/uuid` requirement.

Consequences: one fewer third-party runtime package. Adding a login provider means adding its driver, a catalogue entry and the test row together.
