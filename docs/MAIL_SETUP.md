# Set up reminder email without Gmail

The project already renders reminder emails. It needs a real provider account and credential before it can deliver them. The recipient inbox and sender are different settings. Never paste a provider key into chat, commit it, or use your normal mailbox password.

## Qualification demonstration with Resend

1. Create a [Resend account](https://resend.com/signup) using the inbox where you want the demonstration reminder to arrive. Verify the account email. The default `resend.dev` sender can send only to the account's own inbox; it is not a production sender for other users. See [the provider's restriction](https://resend.com/docs/knowledge-base/403-error-resend-dev-domain).
2. Create a sending API key in [API Keys](https://resend.com/api-keys). Keep it private. SMTP uses this key as its password, with username `resend`; it does not use your email account password. See [SMTP configuration](https://resend.com/docs/send-with-smtp).
3. In your own PowerShell terminal, run:

   ```powershell
   Set-Location C:\laragon\www\Becoming
   & 'C:\laragon\bin\php\php-8.3.33-Win32-vs16-x64\php.exe' artisan mail:setup-resend --demo
   ```

   Enter your Resend account email, then paste the API key into the hidden prompt. The command changes only private mail settings in `.env`, clears cached configuration, creates no account and sends no email. It uses TLS on port 465. Demo mode excludes all other recipients from scheduled delivery, and Settings explains the restriction.
4. Restart the local background processes to load the new configuration:

   ```powershell
   ./scripts/background-services.ps1 -Action Stop
   ./scripts/background-services.ps1 -Action Start -PhpPath 'C:\laragon\bin\php\php-8.3.33-Win32-vs16-x64\php.exe'
   ```

5. Run one test, replacing the example recipient with the email used for Resend:

   ```powershell
   & 'C:\laragon\bin\php\php-8.3.33-Win32-vs16-x64\php.exe' artisan habits:test-reminder your-account-email --send --timezone=Europe/Riga
   ```

   Confirm the actual inbox/spam folder and provider dashboard. Transport acceptance is not proof of inbox arrival. Do not repeat an uncertain test blindly.

## Sending to normal project users

Add and verify a domain you control in Resend, using the DNS records it supplies. You cannot authenticate the school's domain unless its administrator grants access. Run `mail:setup-resend` without `--demo`, supplying a sender on the verified domain; this clears the recipient restriction. Restart services and check delivery again. Provider quotas and account approval requirements still apply.

There is no configured Resend account/key yet. This guide and helper are ready, but real inbox delivery remains incomplete until the owner creates the provider account and enters its credential locally.
