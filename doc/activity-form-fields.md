# Activity form fields (mandatory report stage)

**Capability:** `activity-form-fields` —
implemented by the archived change
`openspec/changes/archive/2026-09-25-make-report-fields-mandatory/`.

## What it is

A student's project goes through several stages. At the report
stage ("Bilan du projet"), the student writes up the report on their project.
This feature makes the core fields of that stage **mandatory** before the entry
can leave the report stage, and requires the student to confirm a **minimum of
two competencies** actually put into practice.

## Why it exists

Students could previously leave the report stage with the fields that give the
report its pedagogical value — and its exports — left empty. The report stage
is the stage where the project is summarised and reflected upon, so the module
now refuses to advance an entry that is not properly completed.

## What to expect

### Mandatory fields at the report stage

An entry can only advance from "Bilan du projet" when all of the following are
filled in:

- the realization's **start date** ("Date de début de réalisation", `start_date`)
  and **end date** ("Date de fin de réalisation", `end_date`),
- the **summary of the main realizations and actions** ("Résumé des
  principales réalisations et actions menées pendant ce projet",
  `actions_summary`),
- the self-reflection field **"Grâce à cette activité, j'ai progressé sur…"**
  (`progress_on`),
- the self-reflection field **"Au cours de cette activité, j'ai réalisé que je
  devais encore progresser sur…"** (`must_still_progress_on`).

If any of them is empty at submission, the form is rejected with a visible
validation error on the empty field and the entry **stays in the report stage**.
A student who fills everything in correctly submits normally and the entry
advances to the next status.

### Minimum confirmed competencies

The "competencies actually put into practice" field
(`practiced_competencies`) requires **at least two confirmed competencies**
before the entry can advance. Confirming one (or none) produces a validation
error on that field. The minimum is a per-field setting in the field's
configuration data, so it can be tuned without a code change.

### What does not block saving

- **Draft saves are not blocked.** A student can save work in progress at the
  report stage without completing the mandatory fields; the entry remains
  editable in the report stage until a real submission is attempted.
- **Frozen fields are not validated.** Required-field validation only applies
  to fields the current user is allowed to edit at the entry's current stage.
  When a student edits an earlier stage (report fields are frozen/read-only
  there), the report fields are not validated at all.
- **Tutors and privileged users are subject to the same rules** when they
  submit the form on an entry in the report stage with edit rights.
- **Already-submitted entries are not retro-validated.** Entries that are
  past the report stage keep their data as is.

## Configuration and upgrades (for administrators)

The form fields are driven by per-field configuration data
(`projetvet_form_field.configdata`, JSON) imported from
`data/default_activity_form.json` on fresh installs.

- `configdata.required = true` makes a field mandatory (enforced by the form
  only when the field is editable at the current stage).
- `configdata.mintags = 2` on a "tag confirmation" field enforces the minimum
  number of confirmations.

Upgrading to a version that makes the report fields mandatory runs an upgrade
step that **re-syncs the stored configuration on existing installs**:

- it adds the `required` flag to the five mandatory fields and the minimum
  confirmation count to the competencies field,
- it **merges** into the existing JSON, so site-specific customizations (for
  example a customized text-area row count) are preserved,
- it purges the cached form structure, so the change takes effect immediately,
- it is **idempotent**: running it twice does not duplicate or corrupt
  configuration data.

Fresh installs simply get the mandatory configuration from the default JSON,
with no upgrade or import step.

**Note:** editing `data/default_activity_form.json` alone has no effect on an
installed site — the JSON is only imported on fresh install or via
`cli/import_forms.php`; the upgrade step is what applies the change to
existing sites.

## Where to find it in the code

- `data/default_activity_form.json` — default field configuration
  (source of truth for fresh installs).
- `classes/form/projetvet_form.php` — required-field registration and
  minimum-confirmation validation.
- `db/upgrade.php` + `version.php` — the savepoint that re-syncs field
  `configdata` on existing installs.
- `classes/local/api/entries.php` — form structure loading and caching.

## Source

Formal specification:
`openspec/specs/activity-form-fields/spec.md`.
Full change record (proposal, design, tasks):
`openspec/changes/archive/2026-09-25-make-report-fields-mandatory/`.
