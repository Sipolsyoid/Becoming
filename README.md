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

Verification is synchronous, with a 180-second HTTP timeout. Only `approved` results count. A failed save or invalid verdict does not replace the previous completion; a successful retry replaces the same day's proof. Cleanup failures are logged without discarding a successfully saved new proof.

## Verification

- `php artisan test`: authentication and habit workflows, ownership, photo validation/retries, failure handling, statistics and streaks. Tests use a separate in-memory SQLite database.
- `npm run build`: compile production assets.
- `composer check-platform-reqs`: check installed PHP requirements.

Dates use UTC. Historical percentages use the current daily-habit set, and the current streak is zero until today is complete, as specified in the Becoming documentation. Deleting a habit removes its database completions but retains photo files, also as documented.

The environment template uses file caching and log mail for local development. Configure production credentials, HTTPS and `APP_DEBUG=false` before deployment. Never commit `.env`.
