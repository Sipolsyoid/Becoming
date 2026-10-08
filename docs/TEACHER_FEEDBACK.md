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
| Tests must remain reproducible | CI installs dependencies from a clean checkout, builds assets, runs SQLite/JavaScript tests and the MySQL concurrency harness. The workflow has now passed on a clean GitHub Linux/MySQL runner; see the follow-up evidence below. |

## Verification performed

- The initial fix pass completed 135 PHP tests with 656 assertions. The independently installed clean copy passed the preceding 134-test suite with 654 assertions; the additional fractional-weekday case was then verified on SQLite and a freshly migrated, isolated MySQL schema.
- Nine JavaScript tests passed in both copies.
- Both production builds passed and produced identical asset names/content after removing the compiled-view cache dependency.
- Composer manifest validation and platform checks passed. Fresh Composer installation installed 124 locked packages; fresh npm installation installed 159 packages.
- All new migrations were applied to local MySQL. Fresh migrations succeeded on isolated SQLite and MySQL databases.
- Real MySQL concurrency tests passed using separate processes. A final direct-write check exposed MySQL accepting fractional JSON weekdays; an additional integer-type constraint fixed it, and the same failing case then passed. Temporary databases, photo directories and the clean-install copy were removed.
- Real Ollama `gemma3:4b` smoke evaluation initially passed three of four cases. After clarifying directly visible-object evidence, the unchanged cases passed four of four. Both reports and the policy change are retained under docs/verification.

## Follow-up verification and fixes

The owner requested completion of the remaining work and authorised pushing after local checks passed. The follow-up completed:

- A durable reminder delivery ledger, unique per user/local day and committed before SMTP. Interrupted/uncertain attempts cannot be automatically resent. This trades possible missed reminders for avoiding duplicate attempts; it does not claim SMTP exactly-once delivery. Three regression tests cover interruption, receipt-save failure and account cleanup. A real MySQL race with independent caches produced one notification attempt and one ledger record.
- A Windows helper for hidden, checkout-specific worker/scheduler startup, status and stop. Repeated startup did not duplicate the processes. Runtime checks observed recent worker and scheduler heartbeats; the worker was gracefully restarted after the verifier fix. MySQL, Ollama and private storage passed, with zero pending migrations. Mail readiness still warns about the placeholder sender.
- A real email test command that creates no account or delivery record. One owner-authorised SMTP test failed. Connection setup and reminder rendering passed, but the sender is a placeholder and no SMTP login/password was configured. The recipient and private settings are excluded from committed reports. Sender validation now blocks placeholder tests before contacting the service.
- A private Resend SMTP setup command with a hidden key prompt, plus demonstration recipient isolation and an explanation in Settings. The command is tested against a temporary environment file; it preserves unrelated values, exposes no key and sends no email. The owner still needs to create an account and enter its credential locally. [Mail setup instructions](MAIL_SETUP.md).
- A 30-run AI evaluation using five owner-supplied activity photos and one synthetic instruction card. The first run passed 27/30 and exposed three false approvals. After adding a conservative application rule for recognised measured activities, the unchanged suite passed 30/30. Original and guarded results are retained; personal photos are not copied into Git.
- An eight-user load test through the HTTP kernel at 200 habits/30 active per user, with 11 schedule snapshots per habit and 900 historical approvals. All 40 responses matched expectations, owner isolation held, and eight uploads produced eight jobs/check-ins/files. The measured batch took 3.33 seconds with p95 1908 ms on this machine. Temporary data was removed. CI repeats a four-user workload.
- Full local verification: **158 PHP tests / 750 assertions**, **9 JavaScript tests**, production build, Composer manifest validation, and all four MySQL concurrency/integrity checks passed.
- The first remote push exposed a YAML parsing error in the unquoted SQLite `:memory:` command before any job ran. A separate fix quoted the command. The corrected [GitHub run](https://github.com/Sipolsyoid/Becoming/actions/runs/37770296024) completed successfully for commit `cacfaed`: clean Linux dependency installation, build, SQLite regressions, JavaScript tests, fresh MySQL migrations, concurrency and bounded load all passed.

## Remaining practical limits

Actual inbox email delivery is still incomplete. The project owner has no configured mail provider account yet. The setup guide and private credential prompt are ready, but account creation, a provider key and inbox confirmation require the owner. Resend’s default sender is restricted to the account inbox; normal user delivery requires a verified domain the owner controls. The school domain cannot be verified without its administrator’s cooperation. Never substitute demo delivery or SMTP acceptance for an inbox test.

The activity evaluation is a small targeted sample, not representative accuracy or universal prompt-injection protection. Repeated runs are not independent images. The measurement rule covers recognised English/Latvian patterns and can conservatively refuse valid activity photos; it cannot recognise every wording/language. The application is safer in the recorded cases even though raw model decisions can still be wrong.

The load check measures local application/database behaviour with test-mode CSRF bypass, not network-server overhead, sustained traffic, GPU inference or mail-provider throughput. Production still requires service supervision and capacity planning. Windows helpers stop when the computer shuts down.

A clean remote Linux installation now passed, in addition to the earlier independent Windows copy. This does not reproduce physical deployment with a GPU and real mail provider on another personal computer; local Ollama and external inbox testing are separate from CI.

Historic progress after permanent deletion excludes the deleted habit; archive to retain it. Older unrecorded schedule edits and photo descriptions cannot be reconstructed. The reminder ledger deliberately does not automatically retry ambiguous sends; `habits:reminder-status` identifies attempts needing delivery review.
