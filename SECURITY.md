# Security Policy

## Supported Versions

The package follows semantic versioning. Security fixes are released for the latest
minor on each supported major.

| Version | Supported          |
| ------- | ------------------ |
| 1.x     | :white_check_mark: |
| < 1.0   | :x:                |

## What the generator trusts

- **Your project's code runs.** The generator loads the classes that carry
  `#[TypedCollection]` through the project's autoloader and creates their attributes by
  reflection, so run it only on a project you trust. Files are first parsed with
  nikic/php-parser; only those that declare a candidate class are loaded.
- **The config is input, not code.** The YAML or JSON config is parsed without executing
  anything.
- **Writes stay in the project.** A file at a target path without the `@generated` header
  is never overwritten, and a generated file is deleted only inside the config's directory
  or a directory the run writes into; manifest entries that would leave them are not
  trusted.

## Reporting a Vulnerability

Please report vulnerabilities privately to maxim.shamaev@gmail.com rather than
opening a public issue. You will get an answer within a week.
