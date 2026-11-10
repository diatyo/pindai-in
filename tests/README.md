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

Windows runtime tests: tests/windows-installer-smoke.ps1 -Payload <payload>.
Windows Service tests require an administrator and a temporary fixture service:
tests/windows-service-smoke.ps1 -Payload <payload> -ResultFile <result.json>.
These fixtures are not included in distribution archives or Git commits.
