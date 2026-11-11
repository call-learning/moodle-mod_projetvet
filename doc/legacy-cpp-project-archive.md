# Archive of previously validated (CPP) projects

**Implemented in code** (Planio #882) with a formal specification in the still
open change `openspec/changes/import-legacy-cpp-validated-projects/`
(capability `legacy-cpp-import`). This document describes the implemented
behaviour; the OpenSpec change is not archived yet.

## What it is

Students can record the projects that were **already validated under
the previous CPP system** into ProjetVet, so that their final status, awarded
ECTS and other historical data remain visible in student tracking. The flow is
a **one-time, tutor-reviewed archive** per student per activity.

## Why it exists

Without this, validated historical projects would be missing from the new
workflow: their final status and awarded ECTS would not be visible when
tracking a student's overall progression.

## What to expect

### One archive per student per activity

- A student has **at most one archive entry** (form set `archive`) per
  ProjetVet activity; within it, each archived project is a separate
  "Projet repris" subset entry (form set `archive_project`).
- The feature for students is controlled by a **site setting**: student access
  is **one-shot and temporary** — a student can only create and work on their
  archive while the function is enabled and the archive is not yet submitted.

### The workflow (statuses)

| Status | Who can act |
|---|---|
| **Draft** | the student enters their previously validated projects and saves |
| **Submitted** | the student has submitted; **tutors and administrators** can review and correct the values |
| **Validated** | a tutor or administrator has validated; the archive is **locked** — nobody can edit it |

Allowed transitions:

- draft → submitted: **the student only**;
- submitted → validated: **a tutor or an administrator**;
- validated → draft (**reopen**): **an administrator only** (via the
  `managearchive` capability).

While in *submitted*, tutors and administrators may edit the entered projects
to correct values; once *validated*, the "Projet repris" records are locked
(shown as locked information) until an administrator reopens the archive.

### What the archive preserves

Each archived project keeps, where available in the source data: the student,
the tutor, the activity description, the workload, the **final (awarded)
ECTS**, the qualitative assessment and the **validation date**. An optional
field that is missing in the source is left **empty** — the import never
invents a value.

Only projects whose source status indicates they were **already validated**
under the previous CPP process are accepted; unvalidated records are rejected
with a reason.

### No silent duplication or modification

- A record whose project identifier already exists in ProjetVet is **skipped**
  and reported as a duplicate — the existing entry is never modified.
- A record that would modify an existing entry rather than create a new one is
  **rejected** and left unchanged.
- After an import operation, the manager gets a **summary** with the number of
  imported, skipped and failed records, each failure identified with a
  human-readable reason. The import is restricted to **authorized managers**.

### Tracking

- The archived, validated projects are used for student tracking: the
  **validated ECTS** per project and whether a **mobility** was part of the
  project are available for reporting.
- **Archives are never deleted.** Archived project subset entries can only be
  deleted while the archive can still be edited (see
  [Project deletion](project-deletion.md)).

### Who can review

- **Tutors** review and validate the archives of the students they follow
  ("Vérifier la reprise des CPP antérieurs").
- **Administrators** review archives like tutors (and can reopen a validated
  archive).

## Where to find it in the code

- `classes/local/api/archive.php` — the archive API (form sets, statuses,
  transitions, reviewers, validated projects/ECTS/mobility).
- `classes/external/get_archive_entry.php` — the web service feeding the
  archive form.
- `templates/view_page.mustache` — the archive button and the
  "waiting for tutor" message.
- `db` + `data` — the `archive` / `archive_project` form sets and the
  "Projet repris" locked information.
- `lang/en/projetvet.php` / `lang/fr/projetvet.php` — archive strings
  (`archive_*`).

## Source

Formal specification (in the open change, not yet synced to `openspec/specs/`):
`openspec/changes/import-legacy-cpp-validated-projects/specs/legacy-cpp-import/spec.md`.
Change record: `openspec/changes/import-legacy-cpp-validated-projects/`.
