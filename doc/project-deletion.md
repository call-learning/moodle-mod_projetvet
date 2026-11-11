# Project deletion

**Implemented in code** (Planio #913) — no OpenSpec change exists for this
feature; this document is derived from the code and the commits.

## What it is

Students, tutors and administrators can **delete entries** — projects and the
subset entries attached to them.
A "Delete" action with a confirmation is available in the projects menu.

## Why it exists

Entries are created by mistake, duplicated, or become obsolete. They need a
controlled way to be removed — with the right permissions, so that only the
owner or an authorized manager can delete an entry.

## What to expect

### Who can delete what

| Who | What they can delete |
|---|---|
| **Student** | Their own **projects** (the main entries) at **any status**; their other entries (for example subset entries such as a case notebook) only while they are a **draft or submitted** |
| **Tutor / administrator** (with the activity's approve/admin rights and the ability to view all activities) | **Any** entry, at any status |

Anyone else (for example a tutor who does not manage the activity, or a user
without "view all activities") cannot delete entries.

### What deletion removes

Deleting an entry removes, in a single transaction:

- the entry's **field data**,
- the entry's **uploaded files**, and
- its **subset entries** (for example the case notebook of a project).

If anything fails, the whole operation is rolled back.

### What cannot be deleted

- **Archives are never deleted.**
- **Archived (CPP) project subset entries** can only be deleted while their
  archive can still be edited — once the archive is validated (locked), its
  projects can no longer be deleted (see
  [Archive of previously validated projects](legacy-cpp-project-archive.md)).

### How it works technically

The UI action calls the `mod_projetvet_delete_entry` web service, which
validates the parameters and the module context, then asks the entries API to
delete the entry **after checking the caller's deletion permission**
(`form_entry::can_delete()`). The permission check is done directly against the
capability — the action is available in the interface only when the check
passes, and it is enforced again server-side.

## Where to find it in the code

- `classes/external/delete_entry.php` — the web service.
- `classes/local/api/entries.php` — `delete_entry()` and
  `delete_entry_and_children()` (data, files, subset entries, transaction).
- `classes/local/persistent/form_entry.php` — `can_delete()` (who may delete).
- `tests/behat/project_delete.feature` — the deletion scenarios.

## Source

Implemented by the Planio #913 commits (`allow tutors and administrators to
delete projects`, `add "Delete" to the projects menu with a confirmation`,
`allow students to delete their projects at any status`,
`check the capability directly when deleting entries`). No OpenSpec change
covers this feature.
