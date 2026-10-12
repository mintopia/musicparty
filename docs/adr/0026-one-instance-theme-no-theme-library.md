# One Instance Theme; the v2 multi-theme library is not restored

v2 had admin CRUD for several named themes, with one active at a time and a separate navigation colour (triage dec-1, diff-14). v3 has a single Instance Theme edited as design tokens, a Party Theme for each Party, and a Colour Scheme for each user (`openspec/specs/theming/spec.md`). The triage asked whether the library should come back.

We considered restoring named themes with an active-theme switch, and keeping the library as import/export presets. Both add a second way to set the instance look alongside the token editor, and a Party that wants its own look already has a Party Theme.

Decision:
- The theme library is dropped permanently. The instance has one Instance Theme, edited in the token editor.
- Per-event looks are covered by the Party Theme. Per-user light and dark preference is covered by the Colour Scheme.
- There is no named-theme switching and no theme import or export.
- The admin Theme page stays a single editor, not a list.

Consequences: admins cannot keep several instance looks and swap between them; they change the tokens, or give a Party its own Party Theme. The `seat_*` settings and raw CSS from v2 are already removed and stay removed. Nothing in the theming spec changes.
