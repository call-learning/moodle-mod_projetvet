# Per-student temporary substitute tutors

**Implemented in code** (Planio #866) with a formal specification in the still
open change
`openspec/changes/add-per-student-temporary-substitute-tutors/`
(capability `per-student-substitute-tutors`). This document describes the
implemented behaviour; the OpenSpec change is not archived yet.

## What it is

When a principal tutor is unavailable, managers can appoint a **temporary
substitute tutor per student** — so different students of the same tutor can be
followed by different substitutes at the same time — while the principal tutor
stays the student's official referent.

## Why it exists

Before this feature, an unavailable tutor had to hand over *all* of their
students to a single secondary tutor. Per-student substitution lets the
follow-up be spread across several substitute tutors, preserves the principal
tutor assignment and the history of replacements, and always provides a clean
return to the principal tutor.

> Historical note: the module previously had a "secondary tutor(s)" concept
> (a column, an "Assign secondary teacher" action and a CSV field). Those were
> removed and replaced by this per-student substitution model.

## What to expect

### The principal assignment is never overwritten

- The student's **principal tutor assignment (the tutor group) is the source
  of record** and is never changed by a substitution: the substitute is
  recorded separately in the `projetvet_substitutions` table.
- While a substitution is **in effect** (open and started), the **substitute
  is the active tutor** of the student; otherwise it is the principal tutor.
- A student can have **at most one open (active) substitution** at a time.

### What is recorded for each substitution

The substitute tutor, a **start date**, an optional planned end date, an
**internal reason**, and a status. The full history of a student's (or a
principal tutor's) substitutions is kept, most recent first.

### Substitution status

| Status | Meaning |
|---|---|
| Open | Planned or in effect |
| Closed | Ended; the student is back with the principal tutor |
| Definitive | The substitute became the new principal tutor |

### Manager workflow

From the substitute-tutors management page (a principal tutor's student list),
a manager can:

- **Create** substitutions for one or several selected students in a single
  batch (one substitute, start date, reason), with different substitutes for
  different students;
- **See conflicts before anything is saved**: the same eligibility controls as
  for a regular assignment apply to substitutes — compatible **status**,
  **available capacity** (or an explicit over-capacity warning), **A1
  eligibility** when the student is A1, and the tutor must not be the student's
  principal tutor. A batch is applied **all-or-nothing**: if any student has a
  problem, nothing is saved, and the affected students are identified with the
  reason;
- **Close** substitutions (return to the principal tutor), close all at once,
  or close a selection — the usual case when a principal tutor becomes
  available again;
- **Extend** an open substitution by changing its planned end date (never to a
  date before its start);
- **Convert a substitution into a definitive change** — exceptionally, the
  substitute becomes the principal tutor (the student is re-assigned in one
  transaction).

### Capacity and load

Students followed through open substitutions **count towards the substitute
tutor's load and capacity** (a substitute's capacity includes both their
principal students and their substitute students).

### Tutor views

- A tutor's interface **distinguishes principal follow-up from temporary
  substitute follow-up**, and shows the counterpart tutor (the principal tutor
  being replaced, or the active substitute).
- The principal tutor keeps seeing their students with the active substitute
  indicated.

### Notifications during a substitution

- **Operational** follow-up notifications (for example, an entry requiring
  action) go to the **active substitute** during the substitution period; the
  principal tutor keeps receiving them only when configured.
- The **announcement** notifications (start / end of a substitution, first
  assignment, definitive change) are governed separately by the
  [tutor assignment notifications](tutor-assignment-notifications.md)
  capability and are sent only at the manager's explicit choice — never
  automatically by the substitution operation itself.

### Privacy and backups

Substitution records are covered by the plugin's **privacy provider** and its
**backup support**, like the rest of the module data.

## Where to find it in the code

- `substitutions.php` — the substitute-tutors management page.
- `classes/local/api/substitutions.php` — the substitution API (lookup,
  eligibility checks, create/close/extend/make-definitive, load and capacity).
- `classes/local/persistent/substitution.php` — the persistent record and
  status codes.
- `classes/form/substitution_create_form.php` / `substitution_manage_form.php`.
- `classes/local/assignment_notifications.php` — start/end announcement
  scenarios.
- `tests/local/api/substitutions_test.php`, `tests/local/persistent/` and the
  Behat features `substitutions_management.feature` /
  `substitutions_tutor_view.feature`.

## Source

Formal specification (in the open change, not yet synced to `openspec/specs/`):
`openspec/changes/add-per-student-temporary-substitute-tutors/specs/per-student-substitute-tutors/spec.md`.
Change record: `openspec/changes/add-per-student-temporary-substitute-tutors/`.
