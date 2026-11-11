# ProjetVet documentation

Human-readable documentation for the implemented features of the `mod_projetvet`
Moodle activity. Each document below is a plain-language version of an
implemented capability: either an OpenSpec capability that has been implemented
and archived (see `openspec/specs/` for the formal specifications and
`openspec/archive/` / `openspec/changes/archive/` for the full change history,
including design notes and task checklists), or a feature implemented in code
and commits that has no archived spec yet (documented from the code, with the
formal spec referenced when an open OpenSpec change defines it).

These documents are written for people who use or maintain the module —
managers (gestionnaires), tutors (tuteurs), students and site administrators —
to explain **how the module works and what to expect** from each feature.

An HTML entry point for this folder is [index.html](index.html), reachable on a
site as `/mod/projetvet/doc/index.html`. The Markdown files are the source of
truth; the HTML page is a browsable index of them.

## Contents

### Student workflow

| Document | What it covers |
|---|---|
| [Activity form fields](activity-form-fields.md) | The mandatory fields of the "Bilan du projet" (report) stage, the minimum number of confirmed competencies, and how the field configuration is upgraded on existing sites. |
| [Eligibility ECTS](eligibility-ects.md) | How the ECTS value agreed during eligibility validation is preserved and used in later stages (report stage, final validation). |
| [Archive of previously validated (CPP) projects](legacy-cpp-project-archive.md) | The one-time, tutor-reviewed archive of legacy CPP projects already validated, their statuses, preserved data and the no-silent-duplication import rules. |

### Tutor model and assignment rules

| Document | What it covers |
|---|---|
| [Tutor availability status](tutor-availability-status.md) | The per-activity status of a tutor (active & open, active & closed, temporarily unavailable, inactive) and what changing it does — and does not do. |
| [Tutor capacity levels](tutor-capacity-levels.md) | The "Capacity A / B / C" vocabulary, the 12/8/5 student limits behind each level, and compatibility with legacy "Expert / Moyen / Novice" data. |
| [Tutor A1 acceptance](tutor-a1-acceptance.md) | The "Accepte les étudiants A1" flag, how it gates new A1 assignments, and how existing A1 assignments are protected. |
| [Tutor assignment eligibility](tutor-assignment-eligibility.md) | The single server-side rule applied to every new primary assignment (status + A1 acceptance + capacity), the all-or-nothing batch behaviour, and how ineligible tutors are shown in the assignment interface. |
| [Per-student temporary substitute tutors](per-student-substitute-tutors.md) | Temporary per-student substitutions: the principal tutor stays the referent, eligibility and capacity rules, close / extend / make-definitive, history, and notifications during a substitution. |

### Communication and maintenance

| Document | What it covers |
|---|---|
| [Tutor assignment notifications](tutor-assignment-notifications.md) | The four notification scenarios sent at a manager's explicit request when a tutoring assignment changes, their recipients, and their message content. |
| [Tutor broadcast message](tutor-dashboard-broadcast.md) | How a tutor sends an announcement to the students they follow, from the activity view page. |
| [Notification delivery](notification-delivery.md) | The shared asynchronous delivery path behind all ProjetVet notifications (queue, Moodle messaging, per-recipient language and links). |
| [Project deletion](project-deletion.md) | Who can delete entries (students, tutors, administrators), what deletion removes (data, files, subset entries), and what can never be deleted (archives). |

## The module in one page

ProjetVet manages the follow-up of student projects by tutors. Three roles
matter:

- **Students** work through the stages of a project (eligibility, activity,
  report…). At the report stage ("Bilan du projet") they must
  complete a set of mandatory fields, including at least two confirmed
  competencies — see [Activity form fields](activity-form-fields.md). They can
  archive the projects already validated under the previous CPP system — see
  [Archive of previously validated (CPP)
  projects](legacy-cpp-project-archive.md) — and delete their own projects —
  see [Project deletion](project-deletion.md).
- **Tutors (tuteurs)** follow one or more students per activity. Each tutor
  has, per activity: a *status* that says whether they can receive new
  students — see [Tutor availability status](tutor-availability-status.md) —
  a *capacity level* that limits how many students they can follow
  (12/8/5) — see [Tutor capacity levels](tutor-capacity-levels.md) — and an
  *A1 acceptance* flag — see [Tutor A1 acceptance](tutor-a1-acceptance.md).
  All three are checked together, in one place, before any new assignment:
  see [Tutor assignment eligibility](tutor-assignment-eligibility.md).
  Tutors can also broadcast a message to the students they follow — see
  [Tutor broadcast message](tutor-dashboard-broadcast.md) — and their
  temporary substitute follow-up is clearly distinguished from their principal
  follow-up — see [Per-student temporary substitute
  tutors](per-student-substitute-tutors.md).
- **Managers (gestionnaires)** assign and re-assign students to tutors
  (manually, in bulk, or via CSV import). When an assignment changes, they can
  optionally send notifications to the people concerned — see
  [Tutor assignment notifications](tutor-assignment-notifications.md). When a
  principal tutor is unavailable, they appoint temporary substitute tutors per
  student — see [Per-student temporary substitute
  tutors](per-student-substitute-tutors.md) — and they review the students'
  archive of previously validated (CPP) projects — see [Archive of previously
  validated (CPP) projects](legacy-cpp-project-archive.md).

All user-facing notifications (assignment changes, contact messages, entry
actions, substitutions) go through a single queued delivery path that respects
each recipient's language and Moodle messaging preferences — see
[Notification delivery](notification-delivery.md).

## Mapping to OpenSpec

### Archived capabilities

Each of these documents corresponds to an OpenSpec capability implemented by an
archived change:

| Document | Capability | Implemented by (archived changes) |
|---|---|---|
| [Activity form fields](activity-form-fields.md) | `activity-form-fields` | `2026-09-25-make-report-fields-mandatory` |
| [Eligibility ECTS](eligibility-ects.md) | `eligibility-ects` | `2026-09-25-fix-917-ects-value-after-validation` |
| [Tutor availability status](tutor-availability-status.md) | `tutor-availability-status` | `2026-09-29-add-tutor-availability-status` |
| [Tutor capacity levels](tutor-capacity-levels.md) | `tutor-capacity-levels` | `2026-11-11-rename-tutor-capacity-labels` |
| [Tutor A1 acceptance](tutor-a1-acceptance.md) | `tutor-a1-acceptance` | `2026-09-28-add-a1-student-acceptance-attribute` |
| [Tutor assignment eligibility](tutor-assignment-eligibility.md) | `tutor-assignment-eligibility` | `2026-11-11-fix-assignment-eligibility-regressions` |
| [Tutor assignment notifications](tutor-assignment-notifications.md) | `tutor-assignment-notifications` | `2026-09-29-add-tutor-assignment-notifications` |
| [Tutor broadcast message](tutor-dashboard-broadcast.md) | `tutor-dashboard-broadcast` | `2026-09-25-add-tutor-dashboard-broadcast-message` |
| [Notification delivery](notification-delivery.md) | `notification-delivery` | `2026-11-11-unify-notification-delivery` |

### Implemented features without an archived spec

| Document | Spec status |
|---|---|
| [Per-student temporary substitute tutors](per-student-substitute-tutors.md) | Open OpenSpec change `add-per-student-temporary-substitute-tutors` (capability `per-student-substitute-tutors`); implemented (Planio #866). |
| [Archive of previously validated (CPP) projects](legacy-cpp-project-archive.md) | Open OpenSpec change `import-legacy-cpp-validated-projects` (capability `legacy-cpp-import`); implemented (Planio #882). |
| [Project deletion](project-deletion.md) | No OpenSpec change; implemented (Planio #913). |

### Other open changes (verified against the code)

| Open change | Status in code |
|---|---|
| `enforce-tutor-assignment-eligibility` | Implemented (the centralised rule documented above under [Tutor assignment eligibility](tutor-assignment-eligibility.md)). |
| `display-tutor-status` | Implemented (status column with colour-coded badges in the tutor reports). |
| `edit-tutor-availability-status` | Implemented (status is editable in the tutor settings form, independently of the capacity level). |
| `sort-tutors-and-students-by-last-name` | Implemented (tutors and students are listed by last name, then first name). |
| `fix-tutor-student-capability-context` | Implemented (module capabilities are checked at the module context). |
| `fix-teacher-assignment-filter-popup` | Implemented (the teacher-selection modal and its report work as specced). |
| `attach-practical-info-to-tutor` | Largely implemented (tutors can set their "Informations pratiques pour mes tutorés" from the view page; a few tasks remain open). |
| `review-tutor-capabilities` | Audit deliverable (a capability matrix; no behaviour change). |
| `import-tutor-status-csv` | Not implemented yet. |
| `log-tutor-status-changes` | Not implemented yet. |

These are not documented with a dedicated page yet: when one of them is
archived (or, for the not-yet-implemented ones, delivered), a document should
be added to this folder.
