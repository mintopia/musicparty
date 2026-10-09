# Issue tracker: GitHub

Issues and specs for this repo live as GitHub issues (`mintopia/musicparty`). Use the `gh` CLI for all operations.

## Repo rules for ticket sets (override skill defaults)

These apply to every set of tickets created by `/to-tickets` or `/wayfinder`. Where a skill's own instructions conflict, these win.

1. **Every ticket has an epic parent.** Before creating any child ticket, there must be an epic issue:
   - `/to-tickets` from an existing issue: that issue is the epic. Add the `epic` label to it (removing `ready-for-agent` if present). Do not otherwise modify it.
   - `/to-tickets` with no source issue: create the epic first, summarising the plan, labelled `epic`.
   - `/wayfinder`: the map issue is the epic. Label it `wayfinder:map` **and** `epic`.
2. **The epic is labelled `epic` and never `ready-for-agent`.** It may carry other labels (e.g. `wayfinder:map`, a work-type label).
3. **Children are native GitHub sub-issues of the epic.** Create them with `gh issue create --parent <epic> ...` (or `gh issue edit <epic> --add-sub-issue <child>`). Never rely on a `Part of #<n>` body line alone.
4. **Blocking edges use GitHub's native `blocked_by` dependencies** (see **Blocking** under Wayfinding operations). Never use a body-only `Blocked by:` line as a substitute.
5. **`ready-for-agent` goes on last.** Create every ticket and wire every parent/child and `blocked_by` relationship first. Only then, in a final pass, add `ready-for-agent` to the children that qualify. Create children without any triage label.
6. **Every ticket gets a work-type label**: one of `coding`, `research`, `testing`, `documentation` (create a new type label with `gh label create` only if none fit). Wayfinder tickets carry both their `wayfinder:<type>` label and a work-type label: `research`/`grilling` → `research`, `prototype` → `coding`, `task` → whichever fits the work.

Order of operations: epic → children (with `--parent`, work-type label) → `blocked_by` edges → verify (`gh issue view <epic> --json subIssues` or the sub-issues API) → `ready-for-agent` on qualifying children.

## Conventions

- **Create an issue**: `gh issue create --title "..." --body "..."`. Use a heredoc for multi-line bodies.
- **Read an issue**: `gh issue view <number> --json number,title,body,labels,comments`.
- **List issues**: `gh issue list --state open --json number,title,body,labels,comments --jq '[.[] | {number, title, body, labels: [.labels[].name], comments: [.comments[].body]}]'` with appropriate `--label` and `--state` filters.
- **Make an issue a sub-issue of a parent**: `gh issue create --parent <parent> ...`, or `gh issue edit <parent> --add-sub-issue <child>` afterwards (`gh` 2.94+). Older `gh`: `gh api --method POST repos/<owner>/<repo>/issues/<parent>/sub_issues -F sub_issue_id=<child-db-id>` (database id, as in **Blocking** below). Without sub-issues, put `Part of #<parent>` at the top of the child body.
- **Comment on an issue**: `gh issue comment <number> --body "..."`
- **Apply / remove labels**: `gh issue edit <number> --add-label "..."` / `--remove-label "..."`
- **Close**: `gh issue close <number> --comment "..."`

Infer the repo from `git remote -v`; `gh` does this automatically when run inside a clone.

## Pull requests as a triage surface

**PRs as a request surface: no.** _(Set to `yes` if this repo treats external PRs as feature requests; `/triage` reads this flag.)_

When set to `yes`, PRs run through the same labels and states as issues, using the `gh pr` equivalents:

- **Read a PR**: `gh pr view <number> --comments` and `gh pr diff <number>` for the diff.
- **List external PRs for triage**: `gh api --paginate 'repos/{owner}/{repo}/pulls?state=open' --jq '.[] | select(.author_association | IN("OWNER","MEMBER","COLLABORATOR") | not) | {number, title, author: .user.login, author_association, labels: [.labels[].name]}'`.
- **Comment / label / close**: `gh pr comment`, `gh pr edit --add-label`/`--remove-label`, `gh pr close`.

GitHub shares one number space across issues and PRs, so a bare `#42` may be either: resolve with `gh pr view 42` and fall back to `gh issue view 42`.

## When a skill says "publish to the issue tracker"

Create a GitHub issue.

## When a skill says "fetch the relevant ticket"

Read it as in **Read an issue** above.

## Wayfinding operations

Used by `/wayfinder`. The **map** is a single issue with **child** issues as tickets.

- **Map**: a single issue labelled `wayfinder:map` and `epic`, holding the Notes / Decisions-so-far / Fog body. `gh issue create --label wayfinder:map --label epic`.
- **Child ticket**: an issue linked to the map as a GitHub sub-issue (see **Make an issue a sub-issue of a parent**). Where sub-issues aren't enabled, add the child to a task list in the map body and put `Part of #<map>` at the top of the child body. Labels: `wayfinder:<type>` (`research`/`prototype`/`grilling`/`task`) plus a work-type label (see repo rules above). Once claimed, the ticket is assigned to the driving dev.
- **Blocking**: GitHub's **native issue dependencies**, the canonical, UI-visible representation. Add an edge with `gh api --method POST repos/<owner>/<repo>/issues/<child>/dependencies/blocked_by -F issue_id=<blocker-db-id>`, where `<blocker-db-id>` is the blocker's numeric **database id** (`gh api repos/<owner>/<repo>/issues/<n> --jq .id`, _not_ the `#number` or `node_id`). GitHub reports `issue_dependencies_summary.blocked_by` (open blockers only, the live gate). Where dependencies aren't available, fall back to a `Blocked by: #<n>, #<n>` line at the top of the child body. A ticket is unblocked when every blocker is closed.
- **Frontier query**: list the map's open children (`gh issue list --state open`, scoped to the map's sub-issues / task list), drop any with an open blocker (`issue_dependencies_summary.blocked_by > 0`, or an open issue in the `Blocked by` line) or an assignee; first in map order wins.
- **Claim**: `gh issue edit <n> --add-assignee @me`, the session's first write.
- **Resolve**: `gh issue comment <n> --body "<answer>"`, then `gh issue close <n>`, then append a context pointer (gist + link) to the map's Decisions-so-far.
