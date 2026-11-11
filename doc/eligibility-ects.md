# Eligibility ECTS (the agreed ECTS value)

**Capability:** `eligibility-ects` — implemented by the archived change
`openspec/changes/archive/2026-09-25-fix-917-ects-value-after-validation/`.

## What it is

When a tutor validates the eligibility of a student's project, the module
computes an **indicative ECTS value from the planned workload**. The tutor can
then agree a different value with the student. This feature makes the
**tutor-validated value the authoritative agreed ECTS** for the rest of the
activity.

## Why it exists

Previously, later screens (the report stage and final validation) re-ran the automatic
workload-based calculation instead of using the value the tutor had entered and
validated. A tutor who agreed 6 ECTS with a student for a 228-hour activity
could see the report screen display the recalculated 8 ECTS — recording an
agreement that was never made.

## What to expect

### During eligibility validation

- The automatic, workload-based suggestion is shown as an **aid to the
  eligibility decision only**.
- The tutor can confirm the suggested value as is — then the validated agreed
  ECTS equals the suggestion.
- Or the tutor can enter a different value before validating — then that
  entered value is preserved as the **validated agreed ECTS value**. The
  automatic suggestion does not override it afterwards.

### After eligibility validation

Every screen and process that refers to the agreed (pre-activity) ECTS —
including the **report** stage and the **final validation** stage — displays
and uses the tutor-validated value, never a value recalculated from the
activity workload:

- if the tutor overrode the suggestion, the report stage shows the overridden
  value, not the suggestion;
- the final-validation stage likewise displays and uses the validated value
  and does not replace it with a newly calculated one.

### What this does not change

- The workload-to-ECTS **calculation rules and configuration** are untouched.
- The **final ECTS award entered after the activity** (a separate workflow) is
  not recalculated or migrated for historical records.
- Activities where the tutor accepted the automatic suggestion behave exactly
  as before, since the validated value equals the suggestion.

## Where to find it in the code

- The eligibility save/validation path persists the tutor-entered ECTS value
  with the activity's existing form data (no extra database column).
- Balance and final-validation view data read that persisted value instead of
  calling the automatic suggestion calculation.
- Regression coverage exercises both an overridden value and an unchanged
  value through eligibility → report stage → final validation.

## Source

Formal specification: `openspec/specs/eligibility-ects/spec.md`.
Full change record (proposal, design, tasks):
`openspec/changes/archive/2026-09-25-fix-917-ects-value-after-validation/`.
