# Tutor broadcast message

**Capability:** `tutor-dashboard-broadcast` — implemented by the archived
change `openspec/changes/archive/2026-09-25-add-tutor-dashboard-broadcast-message/`.

## What it is

A tutor can send one message (an announcement or invitation, for example at the
start of an academic year) to several of the students they follow, choosing
the recipients and writing the subject and body themselves.

## Why it exists

Tutors previously had no convenient way to make an initial announcement to
the students they follow without contacting them one by one.

## What to expect

### Where the control lives

- The broadcast button is shown on the activity's **overview (student list)
  view page**, for users who are tutors of the activity and are authorized to
  contact their students (the same authorization used for tutor–student
  messaging). It is not shown when a specific student is being viewed.
- Users without the required authorization never see the control.
- The button links to a **dedicated broadcast page**
  (`mod/projetvet/broadcast.php`), protected by a session key and capability
  checks, so it stays fast and safe. (The original spec described a dashboard
  modal; the implementation became a dedicated page on the view page — the
  synced specification reflects that.)

### Choosing recipients

- Only the **students the current tutor follows in this activity** are
  selectable recipients.
- **All of them are selected by default** when the page opens.
- The tutor can deselect one or more students; the message goes only to the
  students still selected.
- Submitting with **no recipients selected** sends nothing and shows an error
  explaining that at least one recipient is required. The recipient list is
  re-verified server-side before delivery is queued.

### Composing and sending

- The tutor enters a **subject** and a **message body**. An empty subject or
  body is rejected with a validation error and no message is sent.
- The message is delivered through **Moodle's messaging system** (the
  `contact` provider): each student's message preferences and available
  delivery providers are respected, and the link label appended to the message
  is localized per recipient (see
  [Notification delivery](notification-delivery.md) for the shared path).
- Delivery is **queued as an asynchronous task** rather than sent inline, so
  the page responds quickly even for a large broadcast (there is a ceiling of
  100 recipients).
- After submission, the tutor sees a **success or failure indication**.

### What this feature does not do

- It is limited to the students the tutor follows — there is no
  group-level or course-wide broadcast.
- There are no saved templates, scheduling, delivery history, or message
  editing after sending.

## Where to find it in the code

- `view.php` — the broadcast button (capability-gated).
- `broadcast.php` — the dedicated broadcast page.
- `classes/local/messaging.php` — `schedule_broadcast_send()` and the
  recipient ceiling (`MAX_RECIPIENTS`).
- `classes/task/send_message.php` — the queued delivery task.
- `classes/external/send_message.php` — the web service behind the send.

## Source

Formal specification: `openspec/specs/tutor-dashboard-broadcast/spec.md`.
Full change record (proposal, design, tasks):
`openspec/changes/archive/2026-09-25-add-tutor-dashboard-broadcast-message/`.
