# Mods are told when they are enabled or disabled through Laravel events

ADR-0004 gave Mods Request rules, Score Modifiers, Decorations, Scheduled actions and listeners for Party-scoped events. None of these runs at the moment a Host enables or disables a Mod, and a disabled Mod receives no events. The Whamageddon Mod (ADR-0030) must add its system Request on enable and remove it on disable.

Decision:
- `EnableMod` and `DisableMod` dispatch the Laravel events `App\Domain\Mod\Events\ModEnabled` and `ModDisabled` (the Party and the Mod id) once the change is committed, and only when the state actually changed.
- A Mod's own listener classes are registered in `ModServiceProvider` and filter on their Mod id. They are not delivered through `ModEventDispatcher`, because the disabled Mod is by definition not enabled.
- Mod code reaches the Queue only through Queue Actions (`RequestTrack::placeSystemRequest`, `RemoveRequestForMod`, `SetRequestNotBefore`), per ADR-0012. An architecture test asserts that nothing under `App\Domain\Mod\` writes to Queue models.
- A failing lifecycle listener is reported and never blocks enabling or disabling.

Consequences: this is a new extension point, so it is recorded here as ADR-0030 requires. Core Mods that need setup or teardown use it; Mods that only react to Party events keep using `listeners()`.
