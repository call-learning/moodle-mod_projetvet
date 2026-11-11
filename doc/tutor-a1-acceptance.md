# Tutor A1 acceptance

**Capability:** `tutor-a1-acceptance` — implemented by the archived change
`openspec/changes/archive/2026-09-28-add-a1-student-acceptance-attribute/`.

## What it is

Some tutors still take part in the scheme but should **not** follow
first-year (A1) students. This feature adds an explicit, per-tutor rule that
blocks new, unauthorized A1 assignments while leaving every other kind of
assignment untouched.

## Why it exists

Previously there was no way to distinguish "this tutor is happy to follow
students but not A1 students" from "this tutor accepts everyone". The A1
acceptance flag is a **separate, explicit rule**, distinct from the tutor's
general status and from their available capacity.

## What to expect

### One flag per tutor

- Each tutor has a single **"Accepte les étudiants A1"** (accepts A1
  students) value, with exactly two possible values: **Oui** and **Non**.
- When the field was added, **every existing tutor was initialized to "Oui"**,
  so no existing tutor was unexpectedly blocked.
- The value is shown as an "Accepte A1" column in the tutor management list,
  and a manager can edit it on a tutor record and have the change persisted and
  visible in the management views.

### A1 assignment eligibility

A **new A1 student assignment is allowed only when all three** of the
following hold:

1. the tutor is open to new assignments (status),
2. the tutor has remaining capacity under the existing rules, and
3. the tutor's "Accepte les étudiants A1" value is **"Oui"**.

If the tutor's flag is **"Non"**, the new A1 assignment is **rejected** —
regardless of status or capacity.

### Non-A1 students are not affected

Assigning a student from any **other year** is evaluated exactly as before, on
status and capacity alone. A tutor set to "Non" for A1 can still receive new
students from other years.

### The rule is enforced everywhere assignments happen

The A1 rule applies consistently to every path that creates an assignment:

- **Manual assignment screens** exclude tutors set to "Non" from A1
  proposals, or present them as **non-selectable** with an explicit reason
  ("Ce tuteur n'accepte pas les etudiants A1.").
- **Existing import flows** reject an A1 assignment to a "Non" tutor.
- **Existing automatic assignment proposals** exclude "Non" tutors for an A1
  student. (No new assignment flows were introduced by this rule.)

### Existing assignments are protected

Changing a tutor's A1 flag **never** touches existing assignments:

- If a tutor already following A1 students is switched to "Non", those
  assignments are **kept unchanged**.
- The system **surfaces the resulting inconsistency to managers** with an
  explicit warning identifying the concerned tutor and the A1 assignments that
  have become inconsistent, so it can be resolved deliberately — not by
  silently dropping or moving students.

## Where to find it in the code

- Tutor data model — the A1 acceptance value (stored alongside the other
  tutor settings, initialized on migration).
- The tutor management interface and list display.
- The manual student-assignment screens (proposal filtering / non-selectable
  presentation).
- The import and automatic-proposal controls.
- The inconsistency warning surfaced to managers.

## Source

Formal specification: `openspec/specs/tutor-a1-acceptance/spec.md`.
Full change record (proposal, design, tasks):
`openspec/changes/archive/2026-09-28-add-a1-student-acceptance-attribute/`.
