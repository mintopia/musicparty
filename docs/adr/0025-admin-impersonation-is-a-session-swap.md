# Admin impersonation is a session swap with a stored impersonator id

Admins need to see Music Party as a user sees it to reproduce reports. v2 did this by swapping the logged-in user in the session. The alternatives were a Sanctum token minted for the target, or a read-only "view as" mode that rewrites every query. Both add a second authentication path, and a view-as mode needs every controller to know about it.

Decision:
- `POST /admin/users/{user}/impersonate` stores the admin's id in the session under `impersonator_id`, logs the target in on the web guard and regenerates the session. `DELETE /admin/impersonate` pulls the id, logs the admin back in and redirects to the target's user page.
- Starting is refused for yourself, suspended users, other admins and while already impersonating. Other admins are refused so impersonation cannot be used to act with another admin's powers.
- The stop route sits outside the admin gate, because while impersonating the session user is not an admin. It only acts when `impersonator_id` is present. If the stored admin is gone, suspended or no longer an admin, the session is logged out instead.
- Both transitions write an admin audit entry (`impersonation.started`, `impersonation.stopped`) attributed to the admin with the target as subject. Actions taken while impersonating are attributed to the target in the ordinary way; the audit trail shows the window in which it happened.
- Inertia shares `impersonating` (`name`, `stopUrl`) while the session key is set, and the shell shows a banner with a Remove button.
- The logic lives in `StartImpersonation` and `StopImpersonation` in the Admin context. They write no Identity models, so ADR-0012 is unaffected.

Consequences: no extra credential type and no per-controller awareness. An impersonated session is a normal user session, so anything the user can do, the admin can do as them for as long as the session lasts. Sanctum tokens and API routes are unaffected, and impersonation is browser-only.
