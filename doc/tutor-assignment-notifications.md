# Tutor assignment notifications

**Capability:** `tutor-assignment-notifications` — implemented by the archived
change `openspec/changes/archive/2026-09-29-add-tutor-assignment-notifications/`.

## What it is

When a tutoring assignment is created or modified, the manager can **choose**
to notify the people concerned — the student, the tutors involved — with a
message matched to the situation. Nothing is ever sent automatically.

## Why it exists

Assignment changes affect several people (the student, the tutor who stops
following, the tutor who takes over), and those people need to be told — but
sending a message by accident would be worse than not sending one. The rule is
therefore: **notification is an explicit, post-validation manager decision**,
with a controlled set of scenarios, recipients and message templates.

## What to expect

### The manager decides, after the assignment is validated

- The notification choice is offered **only** when a tutor name was entered or
  changed and the assignment operation has been validated.
- The manager can pick **"Ne pas envoyer"** — the assignment is saved and no
  notification is queued or sent — or **"Envoyer"**, in which case a
  notification scenario is required and the corresponding messages are
  prepared.
- An operation that does not change any tutor **does not** propose a
  notification at all.

### Exactly four scenarios

| Scenario | When it applies |
|---|---|
| **Première affectation** (first assignment) | a student who had no tutor gets one for the first time |
| **Changement définitif de tuteur** (definitive change) | the principal tutor is definitively replaced by another tutor |
| **Début d'une substitution temporaire** (substitution start) | a temporary tutor starts replacing the principal tutor |
| **Fin d'une substitution temporaire** (substitution end) | an active temporary substitution ends and the principal tutor resumes follow-up |

### Recipients per scenario

- **First assignment:** the student and the new tutor.
- **Definitive change:** the student, the former tutor and the new tutor.
- **Substitution start** (and end): the student, the **principal** tutor and
  the **temporary** tutor.

### Message content (V1)

Each recipient receives a subject and body built from the approved templates,
with the student's and tutors' first/last names, the date, and a ProjetVet
link substituted in. In French (the primary operational language), the
subjects are:

- **Student, first assignment:** "Votre tuteur dans ProjetVet" — states who
  their tutor now is, with the ProjetVet link.
- **New tutor:** "Nouveau tutoré – [student name]" — the student has been
  assigned to them, with the link.
- **Student, definitive change:** "Changement de votre tuteur" — names the new
  and the former tutor, with the link.
- **Former tutor:** "Fin de tutorat – [student name]" — the student is no
  longer among the students they follow, names the new tutor.
- **Student, substitution start:** "Remplacement temporaire de votre tuteur" —
  names the principal and temporary tutors, states the start date, and tells
  the student whom to contact during the period, with the link.
- **Principal tutor:** "Substitution temporaire – [student name]" — the
  follow-up is temporarily assigned from the given date; they remain recorded
  in ProjetVet.
- **Temporary tutor:** "Tutorat temporaire – [student name]" — names the
  principal tutor, states the follow-up is temporarily assigned from the
  given date, with the link.
- **Student, substitution end:** "Fin du remplacement temporaire de votre
  tuteur" — the principal tutor becomes active again on the given date, with
  the link.
- **Principal tutor:** "Reprise du tutorat – [student name]" — the
  substitution ends on the given date and they become the active tutor again.
- **Temporary tutor:** "Fin du tutorat temporaire – [student name]" — the
  temporary follow-up ends on the given date, names the principal tutor and
  thanks them for the relay.

### The internal substitution reason is never disclosed

The internal reason recorded for a temporary substitution (for example an
administrative detail) is **excluded from every subject and body**. Messages
state the facts of the change, not the private reason behind it.

### Delivery follows Moodle messaging rules

Messages go through Moodle's messaging infrastructure: each recipient's
**language preference**, **messaging preferences** and **available delivery
providers** are respected. If a selected recipient cannot receive a message,
the delivery problem is **reported** to the manager — the system does not
silently replace the recipient or send to an unintended user.

## Where to find it in the code

- `classes/local/assignment_notifications.php` — the four business scenarios,
  recipient calculation and message construction.
- `classes/external/send_assignment_notifications.php` — the web service the
  manager-facing choice modal calls.
- `lang/en/projetvet.php` / `lang/fr/projetvet.php` — subjects and bodies for
  every scenario and recipient.
- Integration with the temporary-substitution management (start/end events).
- PHPUnit/Behat coverage of scenarios, recipients, content and the
  no-sending path.

## Source

Formal specification:
`openspec/specs/tutor-assignment-notifications/spec.md`.
Full change record (proposal, design, tasks):
`openspec/changes/archive/2026-09-29-add-tutor-assignment-notifications/`.
