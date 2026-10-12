# Triage Labels

The skills speak in terms of five canonical triage roles. This file maps those roles to the actual label strings used in this repo's issue tracker.

| Label in mattpocock/skills | Label in our tracker | Meaning                                  |
| -------------------------- | -------------------- | ---------------------------------------- |
| `needs-triage`             | `needs-triage`       | Maintainer needs to evaluate this issue  |
| `needs-info`               | `needs-info`         | Waiting on reporter for more information |
| `ready-for-agent`          | `ready-for-agent`    | Fully specified, ready for an AFK agent  |
| `ready-for-human`          | `ready-for-human`    | Requires human implementation            |
| `wontfix`                  | `wontfix`            | Will not be actioned                     |

When a skill mentions a role (e.g. "apply the AFK-ready triage label"), use the corresponding label string from this table.

Edit the "Label in our tracker" column to match whatever vocabulary you actually use.

## Additional labels

| Label           | Meaning                                                                 |
| --------------- | ----------------------------------------------------------------------- |
| `epic`          | Parent of a ticket set. Never also labelled `ready-for-agent`.          |
| `coding`        | Work type: implementation                                               |
| `research`      | Work type: investigation or decision-making                             |
| `testing`       | Work type: writing or fixing tests                                      |
| `documentation` | Work type: docs                                                         |

Every ticket carries exactly one work-type label. `ready-for-agent` is applied only after the whole ticket set and its relationships exist; see `docs/agents/issue-tracker.md`.
