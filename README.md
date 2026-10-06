# Becoming

A Laravel habit tracker with private photo evidence, Ollama verification, daily progress, 30-day history and seven-day statistics.

## Local setup

Requires PHP 8.3 or compatible newer 8.x with the Composer-required extensions, Composer, Node.js/npm, and MySQL. Run commands from this directory with the Laragon PHP and Node executables on PATH.

1. Run `composer install` and `npm ci`.
2. Copy `.env.example` to `.env` only for a new installation. Keep an existing `.env` and application key.
3. Create a MySQL database named `becoming`; set `DB_HOST`, `DB_PORT`, `DB_DATABASE`, `DB_USERNAME`, and `DB_PASSWORD` to your local values. The template assumes Laragon's local root account with no password; use your actual credentials.
4. Set `APP_URL` to the URL you will use (`http://becoming.test` with Laragon, or `http://127.0.0.1:8000` with Artisan).
5. For a new installation, run `php artisan key:generate`, then `php artisan migrate`.
6. Run `npm run build`. Serve Laravel from the `public` directory in Laragon, or run `php artisan serve`.

For live frontend editing, run `npm run dev`. Stop the development server before switching to built assets; Laravel uses `public/hot` while Vite is running.

The private storage directory is `storage/app/private`. PHP needs write access to `storage` and `bootstrap/cache`. Photo uploads accept JPEG, PNG and WebP up to 5 MiB; PHP and web-server upload limits must allow that request size.

## Photo verification

Use your existing Ollama service and vision model. `OLLAMA_BASE_URL` and `OLLAMA_MODEL` select the service and model; the template defaults to `http://127.0.0.1:11434` and `gemma3:4b`. Start your service when testing real photos. Automated tests fake its responses and never need the real service.

Verification runs on the dedicated `photos` database queue, with a 180-second Ollama HTTP timeout. Uploads return as soon as the file and job are saved. Only `approved` results count. An unsuccessful check preserves any previous completion and retains the new photo for retry; a valid verdict replaces the same day’s proof. Cleanup failures are logged without discarding a successfully saved new proof.

## Verification

- `node --test tests/js/photo-proof.test.js`: photo selection, client validation and duplicate-submit protection.
- `php artisan test`: authentication and habit workflows, ownership, photo validation/retries, failure handling, statistics and streaks. Tests use a separate in-memory SQLite database.
- `npm run build`: compile production assets.
- `composer check-platform-reqs`: check installed PHP requirements.

Dates use each user’s saved timezone (UTC until changed in Settings). Historical percentages use the current active schedules. Deleting a habit removes its database completions but retains photo files.

## Custom schedules

Habits support every day, selected weekdays, or a flexible target of 1–7 times per week. Choose a schedule when adding a habit, or expand **Change schedule** on an existing habit. **Pause** keeps the schedule and completion records; **Resume** restores it. Existing active habits remain daily after migration, and previously inactive habits remain paused.

The dashboard shows daily/weekday habits only when due. Percentages and the seven-day graph use each date's scheduled occurrences, excluding off-day approvals. Rest days neither add to nor break a scheduled-day streak; an incomplete due day breaks it. Focus suggestions compare the fraction of scheduled days completed, with creation order breaking ties.

Weekly goals appear separately on the dashboard and Progress, reset Monday at 00:00 in the user’s timezone, and count at most one approved completion per date. They do not affect daily percentages or streaks. Uploads are unavailable after the weekly target is reached, unless replacing today's existing approved record. Rejected or failed attempts do not count. Paused and off-day habits cannot accept uploads.

Schedule changes apply to past calculations too; schedule history snapshots are not implemented. This feature extends the original FP-04/05/07–14 specification, including rest-day and weekly-goal behavior. Run `php artisan migrate` when deploying the change; the local database has already been migrated.

## Interface update

The September 2026 interface adds local photo previews, file guidance, background check status messages, and success feedback for habit changes. This extends the original FP-08 interface description, which said there was no preview or waiting state. The server still validates every upload and the completion rules are unchanged. Progress charts show actual zero-height bars for 0%, and history distinguishes today in progress from complete and incomplete days.

The environment template uses file caching and log mail for local development. Configure production credentials, HTTPS and `APP_DEBUG=false` before deployment. Never commit `.env`.

## Personal timezone and email reminders

Open **Settings** in the navigation. Choose a timezone, or use **Use device timezone**, and save. The header date, due weekdays, completion dates, daily statistics, history range, and Monday weekly reset all use that timezone, including daylight saving changes. Server timestamps remain UTC. Existing completion dates are not rewritten when the timezone changes; new uploads use the local date captured when the request begins.

Reminders are off by default. Enable **Email reminders**, choose a local time, and save. Uncheck the same control and save to stop future reminders. One email per local calendar date lists only unfinished due habits and unfinished weekly goals. Paused habits, off-days, reached weekly targets, and habits already approved today are excluded. A missed run catches up later that day; it does not send a backlog for earlier days. A skipped daylight saving time sends at the next run after the clock jump; a repeated time does not produce a second email. Changing time or timezone does not clear the last-sent date.

Run `php artisan migrate` after pulling these changes. For actual delivery:

1. Configure `MAIL_MAILER=smtp`, `MAIL_HOST`, `MAIL_PORT`, `MAIL_USERNAME`, `MAIL_PASSWORD`, `MAIL_FROM_ADDRESS`, and (if required by your provider) `MAIL_SCHEME` in your private `.env`. Set `APP_URL` to the address users can open from email. Run `php artisan config:clear` after changes. Log/array mailers are previews only; the Settings page indicates that mode.
2. Locally, keep `php artisan schedule:work` running in another terminal. Production should invoke `php artisan schedule:run` every minute using its task scheduler. No queue worker is needed for reminder delivery.
3. `php artisan habits:send-reminders` manually processes due reminders using the configured mailer. Do not use this command to test against real users unintentionally. Automated tests fake delivery.

Reminder runs use per-user cache locks plus a saved last-sent date to avoid normal duplicate runs. Use a shared lock-capable cache when running multiple servers. Failed deliveries are logged and retried on subsequent runs. As with SMTP generally, a process crash after the server accepts an email but before its receipt is saved can cause a retry; exactly-once email delivery is not guaranteed.

## Background photo checks

Start Ollama as usual, then keep this worker running from the project directory:

```powershell
php artisan queue:work photos --queue=photos --tries=1 --timeout=210
```

`composer run dev` now starts this worker alongside the app and Vite. This dedicated connection works even if your existing `.env` has `QUEUE_CONNECTION=sync`. The queue uses the existing `jobs` table and a 300-second retry interval, longer than the job timeout. Keep the queue on the application's database so saving the upload record and enqueuing the job commit together. Run `php artisan migrate` when deploying, and restart long-running workers after code updates (`php artisan queue:restart`). Production needs a supervised worker. The email reminder scheduler remains separate.

The check-in card distinguishes uploading, queued, checking, approved, needs-review, rejected, and failed states. It polls only while a check is pending, backs off on network trouble, and pauses polling in hidden tabs. Approval updates daily totals, streaks, focus suggestions, and weekly totals without refreshing the page or clearing another selected photo. With JavaScript disabled, uploads still redirect and results can be checked by reloading.

A failed or interrupted check offers **Retry saved photo**. A check stuck for ten minutes can also be retried; old workers cannot overwrite a newer attempt. Earlier unresolved checks remain on the dashboard (up to ten most recent), with their original local date. Pending checks never earn completion credit. The model's inference speed is unchanged; the app stays responsive while it works. Automated and browser checks use controlled verifier results, not a claim of real-model accuracy.
