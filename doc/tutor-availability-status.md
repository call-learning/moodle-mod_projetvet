# Tutor availability status

**Capability:** `tutor-availability-status` — implemented by the archived
change `openspec/changes/archive/2026-09-29-add-tutor-availability-status/`.

## What it is

Each tutor has an explicit **availability status**, stored per
(tutor, ProjetVet activity) pair, with a controlled vocabulary of four values:

| Code | Meaning |
|---|---|
| `active_open` | Active, open to new assignments |
| `active_closed` | Active, but no new assignments |
| `temporarily_unavailable` | Temporarily unavailable |
| `inactive` | Inactive (out of the scheme) |

This lets a manager express "this tutor is closed to new assignments" without
touching the tutor's capacity, which is a separate concept.

## Why it exists

Before this change the only dial was the tutor's capacity (derived from their
capacity level). There was no way to say "this tutor stays active for the
follow-up of their current students but should not receive new ones". The
status is the **data foundation** on which later, dedicated changes build the
assignment controls, the management interface, display, import and history.

## What to expect

### One status per tutor per activity

- A tutor's status is scoped to a single ProjetVet activity, exactly like
  their current rating. The **same tutor can hold different statuses in
  different activities**, and the values never propagate between activities.
- When a settings record is created for a tutor who has none yet, the status
  is set to `active_open`.
- The upgrade that introduced the status set **every pre-existing record to
  `active_open`**, preserving today's behaviour.

### Controlled vocabulary

- Only the four codes above are accepted. Any other value is **rejected at
  write time** with an explicit validation error.
- A settings record with no status value is **interpreted as `active_open`**,
  consistent with the lazy creation of the rating record.

### Changing the status never disturbs existing data

Changing a tutor's status has **no side effects** on anything already in
place:

- existing tutor–student assignments are kept,
- existing groups are kept (a tutor is not removed from their groups),
- the tutor's rating and the capacity computed from it are unchanged.

So a tutor who is `active_open` with assigned students can be switched to
`inactive` or `temporarily_unavailable` without losing or altering any
existing assignment, group, or capacity.

### Status is independent of capacity

The status and the rating/capacity are separate dimensions:

- changing a tutor's **rating** does not change their status (a tutor who is
  `active_closed` stays `active_closed` after a rating change);
- changing a tutor's **status** does not change their rating or the capacity
  computed from it.

## What this change does not do

This archived change is intentionally a **foundation**: it adds the data
attribute, its controlled vocabulary and its lifecycle rules, plus the schema
column and the upgrade step. It deliberately adds **no interface and no
assignment control**. The behaviours that consume the status (enforcing
eligibility, editing it in the UI, displaying it, importing it, logging
changes) are carried by the follow-up changes listed in the proposal.

## Where to find it in the code

- `projetvet_teacher_rating` schema — new `status` column — and `db/upgrade.php`
  (the new savepoint that migrates existing records to `active_open`).
- The `teacher_rating` persistent — property, validation, and accessors.
- `mod_projetvet\local\api\groups` — status accessors used by workflows.

## Source

Formal specification: `openspec/specs/tutor-availability-status/spec.md`.
Full change record (proposal, design, tasks):
`openspec/changes/archive/2026-09-29-add-tutor-availability-status/`.
