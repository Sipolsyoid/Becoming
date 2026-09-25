# Becoming implementation audit

Date: 2026-09-25

## Follow-up fixes (2026-09-25)

The findings below describe the original audit. The subsequent implementation now validates the entire AI verdict shape, handles failed storage and cleanup safely, aggregates completion counts in SQL, normalizes completion dates across MySQL/SQLite, and provides a corrected `.env.example` and project-specific README. The test suite now has 33 passing tests with 174 assertions. Browser checks cover login, registration, dashboard, habits, history and progress at 375/768/1440 px; tablet navigation now uses the collapsible menu, with accessible toggle state. Ollama/model installation was explicitly excluded by the user and was not changed or retested. The earlier empty-model response was a point-in-time observation, not a statement about models installed elsewhere or a stopped instance.

The remaining optimization ideas below (queued inference, deletion of retained proof files, optional dependency cleanup) are outside these fixes. Synchronous inference and retained files remain consistent with the specification. Real inference quality, concurrent uploads and load testing are not covered by this follow-up.

Reference: `C:/Users/sipis/Downloads/KVD_Daniels_Sīpols.pdf`, especially pages 7–20 (FP-01–FP-14 and NFP-01–NFP-06).

## Verdict

The implementation closely follows the documented functions. It is not fully operational: the configured Ollama service is reachable but has no installed models. Several robustness and efficiency improvements remain. This was a source/configuration review with existing automated tests, build checks, and a mocked AI-response reproduction; it is not a complete browser acceptance test or load test.

## Verification performed

- PHP 8.3.33 and installed Composer package platform requirements: passed.
- Existing test suite: 8 passed, 15 assertions. Coverage is authentication, guest redirect, and a trivial unit test; there are no habit workflow tests.
- Vite production build: passed; CSS 47.71 kB (8.90 kB gzip), JS 81.66 kB (29.92 kB gzip). Browserslist reports stale browser data.
- Actual configured MySQL connection: migration status succeeded; all six migrations applied.
- Route inventory: authentication, dashboard, habits, photo submission, history, and progress present.
- Current Vite development endpoint: HTTP 200.
- Configured Ollama endpoint `/api/tags`: returned `{"models":[]}`. Configured model is `gemma3:4b`.
- Mocked verifier response with `decision` and `reason` but no `visible_evidence`: accepted. No actual inference or user-data mutation was used for this reproduction.

## Functional requirement comparison

"Matches" below means the inspected implementation matches the written behavior, not that every acceptance scenario has an automated or browser test.

| Requirement | Assessment |
| --- | --- |
| FP-01 Registration | Matches: validation, unique lowercase email, password hashing, login and redirect. Happy path tested. |
| FP-02 Login | Matches: credentials, remembered login, session regeneration, five-attempt limiter. Basic success/failure tested. |
| FP-03 Logout | Matches: logout, session invalidation, CSRF regeneration, redirect. Basic logout tested. |
| FP-04 Habit list/add | Matches: user ownership, name/category limits, per-user name uniqueness, daily-first sorting. |
| FP-05 Daily status | Matches: ownership check, boolean validation, existing completions retained. |
| FP-06 Delete habit | Matches: ownership check and database cascade. Retaining photo files is explicitly documented. |
| FP-07 Dashboard | Matches: current daily habits, approved-only completions, rounded percentage, empty state. |
| FP-08 Upload | Normal path matches: private storage, JPEG/PNG/WebP, 5 MiB cap, date fixed before synchronous analysis, replacement/cleanup logic. Storage failures need hardening. |
| FP-09 AI verification | Partial: request format, timeout and decisions match, but local model is absent and response validation is incomplete. |
| FP-10 History | Matches: 30 days including today, descending date order, current daily habit denominator. |
| FP-11 Progress | Matches: seven days, rounded percentages and N × 7 denominator. |
| FP-12 Streaks | Matches: 365-day window; current streak is zero until today is complete. |
| FP-13 Access control | Matches in source: auth middleware, user-scoped queries, ownership checks returning 404, private images. Dedicated isolation tests remain necessary. |
| FP-14 Least-completed habit | Matches: seven-day approved counts, zero counts included, earliest creation time wins ties with different timestamps. |

## Prioritized issues

1. **High: configured AI model is missing.** Ollama is reachable at the configured loopback endpoint, but has no models. Install `gemma3:4b`, then verify an actual image request. FP-08/FP-09 cannot complete successfully in the current environment.
2. **Medium: incomplete AI-response validation.** `app/Services/HabitPhotoVerifier.php:81` validates only `decision`. It does not enforce string `reason` and `visible_evidence`, or the full response shape. A mocked approved response missing evidence was accepted. Validate the complete decoded object before saving; test malformed JSON, missing fields, wrong types, and invalid decisions. This is a gap against FP-09's invalid-response handling.
3. **Medium: file-write failures are not handled reliably.** `app/Http/Controllers/HabitCompletionController.php:36` stores the image before its try/catch. The local disk has `throw=false`; a failed store can return false, and the code never checks it. Verify storage succeeded before inference and handle both false returns and exceptions without changing a prior completion. File deletion failure handling also deserves dedicated tests.
4. **Medium: fresh-install configuration is inconsistent.** `.env.example` has duplicate APP_URL entries, a populated application key, placeholder database credentials, no DB_CONNECTION despite describing a MySQL setup, and CACHE_DRIVER although config reads CACHE_STORE. A copied template defaults to SQLite with a placeholder database path. Keep the key blank, specify a coherent database setup, and use the configuration names actually consumed by the app. The current local MySQL connection works.
5. **Medium: requirements are mostly untested.** The existing eight tests do not establish habit validation, cross-user protection, verdict persistence, retry cleanup, history, percentages, streaks, or tie handling. Add acceptance tests against the PDF scenarios. NFP-05's six pages at 375/768/1440 px remain unverified in a browser.

## Optimization opportunities

- `HabitMetrics::completionCountsByDate` and `completionCountsByHabit` fetch matching rows and count them in PHP. SQL `GROUP BY`/`COUNT` would reduce transferred rows, model hydration and memory while preserving documented results. Preserve zero-count habits and their order.
- Dashboard/progress read overlapping weekly and yearly ranges. Consolidation may reduce work; measure query timing before adding indexes or caching.
- Photo analysis intentionally blocks the request for up to the configured 180-second HTTP timeout. This matches FP-08 and NFP-04. A queued workflow would require updating the documentation and interface, not merely changing QUEUE_CONNECTION.
- Photo files remain after habit deletion, exactly as FP-06 states. Cleanup would reduce disk growth but would change documented behavior and should be reflected in the specification.
- Axios is imported globally but the reviewed forms use normal HTML submissions. Removing unused client dependencies may reduce the JS bundle. The unused Tailwind Vite v4 plugin dependency also warrants cleanup; the active build uses Tailwind v3 through PostCSS.
- Current APP_ENV=local and APP_DEBUG=true are appropriate for development. Production configuration and deployment behavior were not verified.

## Behaviors that should not be "fixed" as specification mismatches

The PDF explicitly requires UTC dates, historical calculations based on today's daily-habit set, no creation-date adjustment to historical denominators, a zero current streak when today is incomplete, a 365-day best-streak window, synchronous AI checks, and retained files after habit deletion. These can be product improvement topics, but the present code follows the stated design. Password-reset/profile routes and habit-name editing are not part of the specified functional scope.

No application source/configuration was changed during this audit. The production assets were rebuilt. The pre-existing untracked `your_database_name` file was left untouched. No dependency security audit, real AI-quality test, concurrent-upload test, or responsive browser acceptance test was completed.
