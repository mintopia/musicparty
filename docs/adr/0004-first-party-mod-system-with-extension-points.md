# First-party Mod system with fixed extension points

v2's Mods framework was a thin scaffold with a single Mod (Whamageddon), and it hooked in through cron tick events. We are designing the v3 Mod system up front, because trust scores, Whamageddon and AI Request review are all planned as Mods. Mods are first-party classes in this repository, registered through a service provider. Each has per-Party enablement and typed settings. There is no loading of third-party code at runtime.

Mods may act only through these fixed extension points:
- **Request Rules**: accept, reject or hold a Request.
- **Score Modifiers**: adjust a Request's score at selection time.
- **Scheduled actions**: may create system Requests.
- **Presentation hooks**: change how Requests and Plays are shown.
- **Domain event listeners**.

Keeping the surface explicit lets core change without breaking Mods, and lets the API and AsyncAPI specs describe whatever a Mod can add.
