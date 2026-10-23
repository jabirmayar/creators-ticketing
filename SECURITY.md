# Security policy

## Supported versions

Security fixes target the latest patch release in the 1.3.x series. Earlier versions do not receive backported security fixes; upgrade to the latest supported release.

## Reporting a vulnerability

Email **hello@jabirkhan.com** with the subject `CreatorsTicketing security report`. This is the private reporting address already documented in the README. Do not post vulnerability details, tracking tokens, credentials, or customer data in a public issue.

Include the affected package, Laravel, Filament, Livewire, and PHP versions; relevant configuration; steps to reproduce with synthetic data; and the expected impact. A minimal proof of concept is helpful. Remove customer attachments, personal information, secrets, and private ticket links before sending a report.

Reports are reviewed privately. Confirmed vulnerabilities are addressed through a fix and a release; disclosure timing should be coordinated with the maintainer so users can update. This volunteer-maintained project does not promise a fixed response time.

## Maintenance checks and limits

The dependency-security workflow checks the committed Composer and npm lock files on pushes, pull requests, and a weekly schedule. Composer advisories cover runtime dependencies; npm advisories also cover the frontend build dependencies. These checks identify known dependency advisories, not all vulnerabilities in this package or a host application.

Keep the host application's dependencies current and run its own dependency audit. Guest tracking links grant access to a ticket and must be kept private. Report authorization bypasses, exposure of internal notes or attachments, unsafe file handling, script injection, and tracking-token disclosure through the private channel above.
