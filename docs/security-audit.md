# Application security audit — 11 October 2026

This is an application-level review with executable regressions, not an external penetration test. No secrets or private document contents are included in reports.

| Area | Result and evidence |
|---|---|
| Authentication / staff MFA bypass | Removed separate Filament credential flow. /admin/login redirects to Fortify /login with intended /admin destination. Login, password reset request, verification redirect, enrollment protection and one-use recovery-code replacement are tested |
| Wallet authorization | Locked fresh active/verified customer, password verification inside transaction, authenticator verification for new withdrawals; changed-key payloads rejected. Native wrong-password/suspended tests leave balances unchanged |
| Private KYC / support files | Authenticated policy-checked download, private local disk, no-store responses. Unrelated customer download is forbidden in tests. KYC IDs are encrypted; destinations remain encrypted and excluded from page props |
| Sensitive validation data | Exceptions.dontFlash excludes authenticator/recovery codes, sample identity fields, destination and support body; passwords already excluded by Laravel. Identity non-flashing regression passes |
| CSRF | Laravel web and Filament CSRF middleware retained. Browser mutations use Inertia tokens; Echo private-channel authorization sends CSRF header. Unit HTTP tests bypass CSRF by Laravel convention, so their success alone is not evidence of CSRF enforcement |
| XSS | React renders user text; Blade uses escaped interpolation. No customer-supplied HTML rendering found. Chart data is numerical; MFA QR originates from authenticated Fortify |
| SQL injection / IDOR | Parameterized query builder; search truncated, status/sort/type whitelisted; customer history queries scoped to owner; private-file policies tested |
| Role escalation | Role/status absent from customer fillable profile attributes; staff permissions and password confirmation enforced. Customer/auditor forbidden actions tested |
| WebSockets | Private account channel checks owner, active status and verification; Reverb origins restricted to configurable host allowlist rather than wildcard. TLS choice now follows deployment scheme; import failure falls back to polling |
| Rate limiting / uploads | Existing route/authentication limits retained; JPEG/PNG image checks and support MIME/size validation. Executable KYC upload rejection tested. Upload rollback cleanup added |
| Dependency advisories | Composer audit: no advisories/abandoned packages; npm audit: zero reported vulnerabilities, including development dependencies |

Local log review is recorded as aggregate counts in test-artifacts/log-privacy-review.json. No literal password fields were found by that narrow scanner. Logged local mail contains authentication-link lines and fixture emails; this is a confirmed development privacy limitation, not a clean-log certification. No tokens were copied into the report. Production must use delivered email, protected/rotated logs, HTTPS, secure cookies, encrypted session storage, patched runtimes and controlled backups.

KYC malware scanning, encryption at rest, production retention policy for encrypted application metadata, security headers/CSP hardening and mandatory staff-MFA policy need deployment work. The app remains a local paper-trading environment. No claim of production security readiness or exhaustive screen-reader/penetration coverage is made.
