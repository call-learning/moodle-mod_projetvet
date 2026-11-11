# Tutor capacity levels

**Capability:** `tutor-capacity-levels` — implemented by the archived change
`openspec/archive/2026-11-11-rename-tutor-capacity-labels/`.

## What it is

Tutors are grouped into three capacity levels that limit how many students
they can follow. The user-facing vocabulary now describes these levels as
**capacity levels** instead of the former "rating" wording:

- the level column / settings field is labelled **Capacity** (French:
  **Capacité**),
- the three levels are displayed as **Capacity A**, **Capacity B** and
  **Capacity C** (French: **Capacité A**, **Capacité B**, **Capacité C**).

## Why it exists

The interface previously exposed "Expert", "Average"/"Moyen" and "Novice",
which did not match the intended business vocabulary. The change updates the
visible wording to capacity language while **keeping the underlying rules,
stored values and data formats completely compatible**.

## What to expect

### The capacity limits are unchanged

Each level keeps its existing student limit:

| Level | Student limit |
|---|---|
| Capacity A | 12 students |
| Capacity B | 8 students |
| Capacity C | 5 students |

### Legacy data stays fully compatible

This was a **labelling change only** — no data migration:

- The internal codes `expert`, `average` and `novice` are unchanged in the
  database, in persistent constants, and in capacity calculations.
- Backups, restores, imports and exports keep using those legacy codes and
  remain readable and usable.
- A stored tutor with the legacy code `expert` is displayed as **Capacity A**
  (with its 12-student limit), `average` as **Capacity B** (8), and `novice`
  as **Capacity C** (5).
- A CSV import that still contains `expert`/`average`/`novice` is accepted and
  mapped to the corresponding capacity level.

### Legacy wording is no longer shown to users

Reports, forms, accessibility labels and notifications related to tutor
capacity **must not** display "Expert", "Average", "Moyen" or "Novice" as
level labels — only Capacity A/B/C (or Capacité A/B/C). The language-string
keys used by the forms and reports were kept; only their displayed values were
changed, so the call sites stay stable and the new vocabulary flows through
report callbacks, form options and accessibility text automatically.

## What this change does not do

- It does not rename database values or persistent constants.
- It does not change the 12/8/5 capacity rules.
- It does not change CSV, backup, restore or import/export formats.
- It introduces no database migration and does not alter assignment
  eligibility logic.

## Where to find it in the code

- English and French language strings for the capacity column and levels
  (`lang/en/projetvet.php`, `lang/fr/projetvet.php`).
- The tutor settings form and the tutor assignment (teacher) report entity.
- Tutor assignment and capacity-related Behat/PHPUnit assertions, updated to
  expect the new vocabulary and unchanged limits.

## Source

Formal specification: `openspec/specs/tutor-capacity-levels/spec.md`.
Full change record (proposal, design, tasks):
`openspec/archive/2026-11-11-rename-tutor-capacity-labels/`.
