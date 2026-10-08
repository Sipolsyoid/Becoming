# Teacher feedback: verification and fixes

Reviewed against the current Becoming code on 8 October 2026. The feedback covers several behaviours already changed since the reviewed version. This record distinguishes existing fixes, new fixes, and limits that still matter during the qualification defence.

## Corrections and evidence

| Concern | Current behaviour and evidence |
| --- | --- |
| Photos left behind after interrupted uploads | The existing daily orphan cleaner protects both saved and pending references and removes only unreferenced files older than 24 hours. ReliablePhotoChecksTest covers preview and deletion safeguards. |
| Habit deletion retains personal photos | DeleteHabit locks the habit and check-ins, collects saved/pending paths, deletes database records, then removes owned, unreferenced files after commit. HabitDeletionTest also deletes a habit during inference and confirms the late verdict cannot recreate records. Storage failures are logged and orphan cleanup retries them. |
| Account deletion retains photos | Settings now exposes password-protected account deletion. DeleteAccount removes the user's photo directory after database commit, including unreferenced files. AccountDeletionTest covers other-user isolation, invalid passwords and transaction rollback. |
| Check-in owner can disagree with habit owner | A composite database foreign key enforces the habit/user pair. CompletionIntegrityTest verifies direct inserts and updates are rejected. The migration refuses inconsistent existing data instead of silently changing ownership. |
| Invalid schedules can bypass request validation | ScheduleRules validates Habit and HabitScheduleVersion writes. MySQL CHECK constraints and SQLite write triggers reject invalid types, targets and weekday lists, including duplicates and string weekday values. ScheduleIntegrityTest covers direct database writes. |
| Changing schedules rewrites earlier statistics | Existing effective-date versions drive historic calculations. AccurateProgressTest covers edits, pause/resume and weekly target history. Earlier unrecorded changes cannot be reconstructed; the baseline limitation remains documented. |
| New habits lower progress for dates before creation | Existing creation-date schedule boundaries exclude those dates. AccurateProgressTest includes creation boundaries and timezone stability. |
| Incomplete today breaks yesterday's streak | Existing streak calculations preserve it until the local day ends. DashboardRolloverTest and JavaScript tests also check dashboard refresh at midnight. |
| Weekly goals disagree with daily percentages | Weekly goals are intentionally separate because their days are flexible. The dashboard, progress view, history and README explain this. Partial weeks keep the full target; midweek edits use the latest active weekly target for that week. |
| Uncertain AI result implies promised moderation | Upload feedback and photo history explicitly explain no credit and no human review. UncertainEvidenceTest verifies the explanation and statistics. No administrator/moderator role was added because it is not a project requirement. |
| A photo cannot objectively establish an activity's duration/distance | UI and README state the limitation. The system instruction requests an unconfirmed result for unverifiable requirements. AI approval means visible support, not proof of camera authorship, elapsed time or distance. |
| User text can influence model instructions | Habit text is encoded as an untrusted JSON field beneath system rules. VerifierContractTest checks request construction and invalid/oversized/blank verdict rejection. Real-model reports include an instruction-like habit name. These controls are not a universal prompt-injection guarantee. |
| Queue evaluates the wrong description after a rename | New submissions retain the habit name at upload time; saved verdicts retain the evaluated name separately. PhotoDescriptionTest covers a rename while queued. Legacy names without snapshots cannot be recovered. |
| Unlimited AI load | Uploads and manual retries share configurable per-user hourly/daily limits with readable HTTP 429 responses and Retry-After. PhotoRequestLimitTest covers shared limits, separate owners and window expiry. Viewing/cancelling remains available. |
| Unlimited habit list and statistics work | Configurable total/active caps protect creation, resume and restore under an owner row lock. HabitLimitTest covers all three paths. Existing habits are preserved when limits are lowered. |
| Schedule history adds repeated work to statistics | Ordered versions now use binary search instead of scanning all versions for every date. ScheduleLookupTest covers a long history, boundaries, gaps and no extra database queries. Date/habit iteration remains bounded by account caps. |
| Timezone changes alter local day/week unexpectedly | Settings explains the effect. TimezoneBoundaryTest covers a change during pending verification and across Monday's boundary. Existing calendar dates are not rewritten; a submitted photo keeps its captured date. |
| Sequential tests do not prove database locking | The MySQL integration harness starts separate PHP processes together against a temporary database/storage root. Simultaneous uploads produced exactly one check-in, job and photo; simultaneous activations could not exceed the active cap. This exercises controller transactions, not a broad HTTP/load benchmark. |
| Scheduler definition alone does not start reminders | Composer development startup now includes schedule:work. becoming:doctor checks migrations, storage, queue setup, model availability and worker/scheduler heartbeats. Production scheduling and real mail configuration are documented. |
| Clean installation may hide missing prerequisites | A source archive was installed with locked Composer/npm dependencies, migrated against fresh SQLite, built and tested. ZIP was disabled in the local CLI PHP; enabling it for Composer resolved the failure. The prerequisite is documented and included in CI. Fresh MySQL migrations also passed in the concurrency harness. |
| Cached views change CSS build output | Tailwind scans committed Blade sources and pagination templates, not generated view caches. Main and clean-copy builds produced identical assets after this correction. |
| Tests must remain reproducible | CI installs dependencies from a clean checkout, builds assets, runs SQLite/JavaScript tests and the MySQL concurrency harness. The workflow has not run remotely yet because these commits have not been pushed. |

## Verification performed

- The final main-project suite passed 135 PHP tests with 656 assertions. The independently installed clean copy passed the preceding 134-test suite with 654 assertions; the additional fractional-weekday case was then verified on SQLite and a freshly migrated, isolated MySQL schema.
- Nine JavaScript tests passed in both copies.
- Both production builds passed and produced identical asset names/content after removing the compiled-view cache dependency.
- Composer manifest validation and platform checks passed. Fresh Composer installation installed 124 locked packages; fresh npm installation installed 159 packages.
- All new migrations were applied to local MySQL. Fresh migrations succeeded on isolated SQLite and MySQL databases.
- Real MySQL concurrency tests passed using separate processes. A final direct-write check exposed MySQL accepting fractional JSON weekdays; an additional integer-type constraint fixed it, and the same failing case then passed. Temporary databases, photo directories and the clean-install copy were removed.
- Real Ollama `gemma3:4b` smoke evaluation initially passed three of four cases. After clarifying directly visible-object evidence, the unchanged cases passed four of four. Both reports and the policy change are retained under docs/verification.

## Remaining practical limits

The real-model suite uses one public logo image for four narrowly defined cases. It is not representative of real exercise, study or other activity photos, nor does it test instructions embedded in arbitrary images. Broader accuracy and security claims require consented, labelled positive and negative images and reporting false approvals/refusals. Model decisions can remain wrong even with valid JSON.

Actual inbox email delivery was not tested in this pass. The mail transport is configured, but that does not establish delivery. A crash after SMTP acceptance and before recording delivery can still cause a duplicate; cache locks handle ordinary overlaps, not atomicity across SMTP and the database. Exactly-once delivery would require provider-supported idempotency or a documented delivery/recovery policy.

The local deployment check found Ollama and the database available, but no recent worker or scheduler heartbeat. Restart the worker after deployment and keep the scheduler running. Do not claim runtime readiness based only on a successful build.

New limits reduce abuse but do not replace a multi-user load test, capacity planning or supervised production services. Historic progress after permanent deletion excludes the deleted habit; archiving is the supported way to retain its history. Older unrecorded schedule edits and photo descriptions cannot be reconstructed from current records.

The clean-install test ran on this Windows computer, not a second physical machine. CI is configured for a separate Linux/MySQL environment; its first remote run still needs to pass after pushing.
