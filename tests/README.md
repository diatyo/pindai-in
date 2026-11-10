# Developer tests

Install development dependencies using Composer, enable PHP SQLite3, then run:

```sh
php vendor/bin/phpunit --no-coverage tests/unit
```

Unit tests use isolated fixtures. Never point developer integration tests at
an operational database. Browser tests require Node.js, Playwright, and Chrome.
Install Playwright locally or set PINDAI_PLAYWRIGHT_MODULES to its module folder.
The accessory runner creates and drops only a random pindai_acc_test_* database.
Its local database configuration must use a developer-only MySQL account.

Run browser integration tests from the project root:

```sh
php tests/accessory-browser-smoke.php --accounts-only
php tests/accessory-browser-smoke.php --letterhead-only
php tests/accessory-browser-smoke.php --tablet-only
```

The account test creates 1,005 synthetic student records, exercises filtered
generation, pause/resume and CSV export, then checks retries and audit rollback.
It uses a lower hashing cost only through a test-injected callback for the large
fixture; HTTP requests use the application's normal password hashing.
The letterhead test checks per-line font sizes/bold, telephone formatting,
PDF preview, persistence, uploaded logos and administrator-only access.

Windows runtime tests: tests/windows-installer-smoke.ps1 -Payload <payload>.
Windows Service tests require an administrator and a temporary fixture service:
tests/windows-service-smoke.ps1 -Payload <payload> -ResultFile <result.json>.
These fixtures are not included in distribution archives or Git commits.
