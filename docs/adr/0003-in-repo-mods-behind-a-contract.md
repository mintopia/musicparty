# First-party mods live in-repo, auto-registered behind a Mod contract

Today each mod is hand-listed in `config/app.php`, writes into core columns that exist only for it, and can only react to clock ticks. We give each mod its own directory (`app/Mods/<Name>/`) containing a service provider that is discovered and registered automatically, plus a manifest class implementing a `Mod` contract. Mods hook into defined extension points: request rules, score modifiers, Queue additions, decorations, scheduled actions and typed settings. Each Party enables mods individually, and a deploy-time command syncs the mod registry.

Mods stay in this repository rather than separate Composer packages, so contract changes and mods move together while the contract is still settling. Splitting them into packages remains possible later.
