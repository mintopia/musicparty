# No forced playback; the Polling Player targets a Host-chosen device

v2 party settings had `poll` (polling on or off), `device_id` (a picker of the Host's Spotify devices) and `force`. With `force`, the `party:force` command ran every minute and started playback on the chosen device for non-polling Parties that had been idle for 15 minutes. v3 replaced `poll` and `force` with Player kinds: Polling Player, Soloist Player and Browser Player (triage dec-6).

Starting playback unprompted is surprising on a device the Host did not just use, and it fights the Host's own controls. The device picker, though, solves a real problem: the Spotify active device is often the wrong one.

Decision:
- The Host chooses the Player kind on the Party settings page. This is v3's equivalent of the v2 `poll` toggle.
- For the Polling Player, the Host may choose a playback device from the Host's Spotify devices. Host playback controls and hand-offs target that device.
- The system never starts or transfers playback on its own. There is no force playback and no `party:force` command.
- With no chosen device, Spotify's active device is used, as today.
- If the chosen device is gone, the action fails with a clear conflict error and a Party Log entry. It does not silently transfer playback.
- The Spotify control link requests the `user-read-playback-state` and `user-modify-playback-state` scopes.

Consequences: a Party whose device goes idle stays idle until the Host acts. Hosts who linked before this change must re-link to grant the new scopes before they can list devices.
