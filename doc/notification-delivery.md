# Notification delivery

**Capability:** `notification-delivery` — implemented by the archived
change `openspec/archive/2026-11-11-unify-notification-delivery/`.

## What it is

Every notification the module sends — entry-action, contact (broadcast),
assignment and substitution — is delivered through **one shared, queued
delivery path**: the workflow queues a message as an asynchronous adhoc task,
and the task delivers it through Moodle's standard messaging API. No
notification is sent inline inside the page request.

## Why it exists

Before this change the delivery paths were split: entry/action notifications
called Moodle's `message_send()` directly during the request, while contact,
assignment and substitution notifications queued an adhoc task first. The two
paths behaved differently in timing, per-recipient language handling, context
links and debuggability — even though all of them end up in the same Moodle
messaging outputs (popup notification, email, …).

## What to expect

### One queue, one task

- All workflows call the `messaging` facade, which schedules the `send_message`
  adhoc task with a message descriptor (sender, recipients, provider,
  content, context link).
- The task delivers the message through Moodle's `message_send()`, so every
  standard Moodle messaging rule applies: each recipient's **message
  preferences**, the **enabled output plugins** (popup, email) and the
  **provider availability**.
- Queuing decouples delivery from the page request: the page responds
  immediately, and delivery happens later, in the scheduled task.

### The providers are preserved

Two message providers are registered in `db/messages.php`, both enabled by
default for popup and email:

| Provider | Used by |
|---|---|
| `entry_action_required` | entry-action notifications (a student submits an entry that requires tutor action) |
| `contact` | contact/broadcast messages, assignment notifications and substitution notifications |

### Per-recipient language

When the task delivers a message, it switches to **each recipient's language**
for that recipient, renders the subject and body from the module's language
strings, and restores the previous language afterwards. A single queued
notification sent to recipients with different language preferences results in
each of them receiving the message in their own language. (Tutor-composed
broadcast text is sent as written; the link label appended to it is
localized per recipient.)

### Recipient-specific context links

Each message keeps the **context URL** selected by the originating workflow —
for example the student's own entry for an entry-action notification, or the
assignment context for an assignment notification — instead of a generic
link. When no explicit link is given, the message links to the activity page,
and the link label is localized per recipient.

### Recipient routing is unchanged

Unifying the delivery path does **not** change who receives a notification,
or on whose behalf it is sent:

- during a substitution, an entry-action notification goes to the **active
  substitute** (the principal tutor only when configured) — see
  [Per-student temporary substitute tutors](per-student-substitute-tutors.md);
- a notification to a student is sent **on behalf of the active tutor**;
- recipients who no longer exist (deleted users) are skipped.

### Failures are observable

Because delivery happens in the adhoc task and through Moodle's messaging
subsystem, delivery problems are inspectable separately from the page request
that created the notification: an administrator can identify the queued task
(its execution or failure) and the resulting Moodle messaging records, without
digging through the originating web request.

## What this change does not do

- It does not change which recipients receive which notification, nor the
  sender identity.
- It does not change the message content or the controlled scenarios — see
  [Tutor assignment notifications](tutor-assignment-notifications.md) and
  [Tutor broadcast message](tutor-dashboard-broadcast.md).
- It does not introduce retry queues, delivery history, or new message
  providers.

## Where to find it in the code

- `classes/local/messaging.php` — the queue facade (`schedule_message()`,
  `schedule_send()`, `schedule_personal_send()`, `schedule_broadcast_send()`).
- `classes/task/send_message.php` — the adhoc delivery task (per-recipient
  language, content rendering, context URL, `message_send()`).
- `classes/local/notifications.php` — the entry-action helper
  (`entry_action_required` provider).
- `classes/local/assignment_notifications.php` — the assignment/substitution
  scenarios, queued per recipient.
- `db/messages.php` — the registered message providers.
- `tests/task/send_message_test.php`, `tests/local/assignment_notifications_test.php`,
  `tests/local/substitution_notifications_test.php` and the Behat feature
  `contact_student.feature` — regression coverage of queuing, execution,
  providers, recipient routing, per-recipient language and student-specific
  links.

## Source

Formal specification: `openspec/specs/notification-delivery/spec.md`.
Full change record (proposal, design, tasks):
`openspec/archive/2026-11-11-unify-notification-delivery/`.
