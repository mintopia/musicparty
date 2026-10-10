# Bounded contexts own their models, events and jobs

The v3 design (D1) splits the domain into bounded contexts under `app/Domain`. In practice only Actions, contracts and a few events and jobs live there. All 20 Eloquent models sit in a flat `app/Models`, the broadcast events sit in `app/Events`, and most jobs and listeners sit in `app/Jobs` and `app/Listeners`. There is no Membership context, so joining, Bans and Party Roles live in Party. The architecture test maps 3 of the 9 contexts and only catches `Model::create(` calls, so it misses cross-context writes such as `PlaybackCoordinator` setting `TrackRequest.enqueued_at`. The 2026-10-10 v3 review found the layout had drifted from D1 without a record.

We considered amending D1 to accept the flat layout. We rejected that because a flat `app/Models` hides which context owns a table, and the guard test cannot tell a legitimate write from a cross-context one when ownership is not encoded anywhere.

Decision:
- Each context owns its models, events, broadcast events, jobs and listeners, under `app/Domain/<Context>/{Models,Events,Broadcast,Jobs,Listeners}`. `app/Models`, `app/Events`, `app/Jobs` and `app/Listeners` are removed.
- A new **Membership** context owns `PartyMember`, Party Roles, joining, Bans and role changes. Party keeps the Party itself, its lifecycle, settings, Blocklist and Party Log.
- `app/Services` is removed. Social login providers move to Identity. The API documentation tooling is replaced (ADR-0015).
- Only a context's own Actions write its models. Another context asks for a write through that context's Action, for example Playback calls `Queue\Actions\MarkUpNextEnqueued` instead of writing `enqueued_at`.
- The architecture test covers every context and detects writes by type (Eloquent `save`, `update`, `create`, `delete`, `forceFill`, query-builder writes) on another context's models, not by matching call text. A new context or a model outside a context fails the test.

Consequences: one large mechanical move, done before other work so later changes land in the right place. Model class names change, so factories, morph maps, policies, `_ide_helper_models.php`, the PHPStan baseline and the API documentation are regenerated. Morph-map aliases keep the stored `tokenable_type` and similar polymorphic columns stable.
