# Deployment preparation

This is a demo application; deployment does not authorize real-money activity. Use supported PHP 8.2+, Node 22.23+ for builds and MySQL 8. Enable intl, fileinfo, bcmath, mbstring, openssl, curl, PDO MySQL and zip. Use HTTPS, APP_ENV=production, APP_DEBUG=false, secure sessions, log/secret isolation, private storage permissions and backups. Set the Apache document root to public. Never expose project root.

Install locked dependencies, build frontend, run migrations after a backup, cache config/routes/views, run a persistent queue worker and schedule runner, and use a TLS-terminated Reverb endpoint with a restricted origin list. Restart workers after release. Do not change APP_KEY after storing encrypted data without a migration plan.

Monitor failed jobs, settlement lag, negative customer account totals and ledger reconciliation. Scale only after load tests against the actual MySQL engine. Database queue is the baseline; Redis is optional, not required. Document recovery of transactions/queues together. No Docker, Sail or orchestration is used.

Before a real service, independently review security, privacy, legal eligibility, risk disclosures, custody integrations, signed provider webhooks, blockchain verification, payout authorization, AML/KYC obligations and operational controls. Those integrations are absent here.
