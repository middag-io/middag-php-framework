# Security Policy

## Supported versions

The `1.x` line is pre-stable and unsupported: it gets no backports or security fixes, and the move to `2.0.0` is a clean cut.

From `2.0.0`, `middag-io/framework` is part of the `middag-php-*` version family ([Versioning and compatibility](https://docs.middag.dev/libraries/reference/versioning-and-compatibility)). Security fixes go to the latest MINOR of the current MAJOR. When the next MAJOR ships, the last MINOR of the previous MAJOR receives critical and high severity security fixes for 6 months, or until the end of life of the PHP or host version that motivated the new MAJOR, whichever comes first.

## Reporting a vulnerability

Do not open public issues, pull requests or discussions for security problems.

Report privately through either:

- GitHub private vulnerability reporting: **Report a vulnerability** under the repository's **Security** tab, or
- email to **security@middag.io**.

Include a description and its impact, steps to reproduce or a proof of concept, and the affected versions.

## What to expect

- **Acknowledgement** within 5 business days.
- **Triage:** we confirm whether the report is accepted and share our plan for a fix. There is no fixed deadline for the fix.
- **Coordinated disclosure:** please keep the report confidential until a fix is released.
- **Credit:** we credit reporters who want it once the issue is resolved.
