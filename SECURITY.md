# Security Policy

## Reporting a vulnerability

Please **do not open a public GitHub issue** for security vulnerabilities.

Email **jpaulo4799santos@gmail.com** with:

- A description of the vulnerability and its impact.
- Steps to reproduce, or a proof-of-concept.
- The package version affected.
- Any suggested mitigation.

You will receive an acknowledgement within 5 business days. Coordinated disclosure timelines will be agreed case-by-case; the default target is a fix released within 30 days of confirmation.

## Scope

In scope:

- SQL injection or query-construction issues in the engine or schema macros.
- Authentication/authorisation bypasses introduced by this package.
- Information disclosure through error messages, logs, or query results.
- Denial-of-service through unbounded query construction or expensive SQL.

Out of scope:

- Vulnerabilities in upstream dependencies (Laravel, Scout, Postgres, `pg_trgm`). Report those upstream.
- Issues that require local code execution or a compromised database account.
- Misconfiguration of the host application (missing CSRF, unauthenticated routes, etc.).

## Supported versions

Security fixes are issued for the latest minor release. Older minors receive fixes only for issues with a CVSS score ≥ 7.0.

| Version | Supported          |
|---------|--------------------|
| 0.3.x   | ✅                 |
| < 0.3   | ❌                 |

## Credit

If you'd like public credit after disclosure, say so in your report. Anonymous reports are welcome.
