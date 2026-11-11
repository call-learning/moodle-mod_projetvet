# Tutor assignment eligibility

**Capability:** `tutor-assignment-eligibility` — implemented by the archived
change `openspec/archive/2026-11-11-fix-assignment-eligibility-regressions/`.

## What it is

Every **new primary student assignment** is checked against a single,
centralised eligibility rule before anything is changed: the tutor's
**availability status**, their **A1 acceptance** flag, and their **available
capacity**. The same rule is applied no matter how the assignment is made —
manual, bulk, or CSV import — and the assignment interface shows ineligible
tutors as non-selectable.

## Why it exists

Eligibility checks used to live in several places with slightly different
behaviour. This change formalises one consistent contract between the server
workflows and the interface, closes the remaining coverage gaps, and fixes the
UI regression test so the "closed tutor is non-selectable" behaviour is
actually verified.

## What to expect

### One eligibility rule for new assignments

A new primary assignment is allowed only when the tutor:

- is open to new assignments (availability status),
- accepts A1 students, if the new student is A1, and
- has enough remaining capacity for the new student count.

Concretely:

- a **closed** tutor cannot receive a new student — the assignment is
  rejected and existing memberships stay unchanged;
- a tutor who **does not accept A1** cannot receive a new A1 student — the
  assignment is rejected *before* any membership changes;
- a batch that would **exceed the tutor's capacity** is rejected as a
  **complete batch** — there is no partial assignment; either the whole batch
  is applied or none of it is.

### Re-submitting existing assignments is a safe no-op

Students who are **already assigned to the target tutor** are never rejected
just because the tutor has since become closed, unavailable, or no longer
accepts A1:

- a batch containing **only** students already assigned to the target tutor
  succeeds as a **no-op** — no memberships change, no error;
- a **mixed** batch (some existing, some new students) evaluates **only the
  new students** against status, A1 and capacity.

This is what makes the manual form safe: re-saving an unchanged group of
students shows the component's "no change" success message instead of a
false-positive rejection.

### Ineligible tutors are non-selectable, not hidden

In the teacher-selection interface, a tutor who cannot receive the selected
students is still **shown**, but presented as **non-selectable** (the control
is disabled), with the reason exposed through accessible interface content
where available. A closed tutor's radio in the assignment modal is present
and disabled.

### Controls are findable by the tutor's name

Each teacher assignment control exposes the tutor's **full name** through a
semantic, accessible label — so the control can be located by name by
assistive technologies and automated tests, without relying on CSS selectors
or DOM details.

## What this change does not do

- It does **not** change the eligibility policy or the capacity model — it
  validates and completes the already-centralised rule.
- It does **not** alter substitution rules.
- It does **not** weaken the UI regression test (no arbitrary waits; the
  disabled-radio assertion is preserved and made reliable).

## Where to find it in the code

- `classes/local/api/groups.php` — the central eligibility check
  (`check_student_assignment_eligibility()` and the batch helpers) used by
  every assignment entry point.
- `classes/form/edit_member_form.php` — the component-owned
  "no change" success string.
- `lang/en/projetvet.php` / `lang/fr/projetvet.php` — the no-change and
  blocked-assignment strings.
- `tests/local/api/groups_test.php` — direct coverage of the decision table
  (mixed batches, existing assignments, non-A1 capacity, rejection
  precedence, duplicate IDs not inflating the count) plus workflow tests for
  the all-or-nothing transaction boundaries.
- `tests/behat/behat_mod_projetvet.php` and
  `tests/behat/tutor_assignment_interface.feature` — the closed-tutor UI
  scenario, locating the rendered radio by name and asserting it is disabled.

## Source

Formal specification: `openspec/specs/tutor-assignment-eligibility/spec.md`.
Full change record (proposal, design, tasks):
`openspec/archive/2026-11-11-fix-assignment-eligibility-regressions/`.
