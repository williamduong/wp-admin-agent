# Development Guide

## Repository layout

| Path | Purpose |
|---|---|
| `wp-admin-agent.php` | Plugin bootstrap and constants |
| `includes/` | Backend orchestration, REST, providers, storage, and shared services |
| `tools/` | WordPress/WooCommerce tool implementations |
| `src/` | React/JSX frontend source |
| `assets/` | Compiled frontend loaded by WordPress |
| `tests/` | JavaScript and PHP test suites |
| `knowledge-base/` | Product and implementation notes |
| `docs/` | Public English documentation and screenshots |

The commercial Pro add-on and internal deployment automation are maintained separately from the public Free repository.

## Frontend workflow

Install exact dependencies and start the Vite development workflow:

```bash
npm ci
npm run dev
```

Build production assets:

```bash
npm run build
```

The build uses WordPress's `wp-element` script for React and ReactDOM. It writes the interface to `assets/js/admin-agent.js` and extracts its styles to `assets/css/admin-agent.css`; both files must be present in the release package.

Run static checks and JavaScript tests:

```bash
npm run lint
npm run test:js
```

Commit updated compiled assets when the plugin distribution expects users to install directly from GitHub.

## PHP workflow

Use PHP 8.2 or newer and install development dependencies defined by the repository:

```bash
composer install
vendor/bin/phpunit -c phpunit.xml.dist
```

The repository's `npm run test:php` command runs that suite inside `wp-env` after `npm run test:php:setup`. Run syntax checks on changed PHP files before committing. Follow WordPress escaping, sanitization, nonce, capability, and prepared-query practices.

## Local WordPress environment

Repository scripts reference WordPress environment tooling, but a project `.wp-env.json` is not currently included. Supply a reviewed local configuration before using those scripts; do not assume the default environment is ready.

## Adding a provider

1. Implement the existing provider interface.
2. Normalize model messages, tool definitions, tool calls, usage, and errors.
3. Keep credentials and provider HTTP calls server-side.
4. Add settings fields and connection tests.
5. Add unit tests for successful responses, tool calls, malformed output, authentication failure, rate limiting, and timeout handling.
6. Update the security and user documentation.

## Adding a tool

1. Choose a narrow, deterministic responsibility.
2. Define a strict JSON input schema.
3. Sanitize and validate every argument independently of the model.
4. Check WordPress capabilities and resource ownership where applicable.
5. Classify risk and require confirmation for high-impact behavior.
6. Return structured, size-bounded results with actionable errors.
7. Add positive, negative, authorization, and edge-case tests.
8. Register the tool and update the documented tool families.

## Documentation and screenshot workflow

- Capture screenshots from a clean demo with no real customer data, secrets, or billing identifiers.
- Store documentation images under `docs/images/`.
- Prefer stable UI states and crop only irrelevant browser chrome.
- Update alt text and the User Guide when screens change.
- Do not use AI-generated UI mockups as product evidence.

## Pull request checklist

- Build, lint, and relevant tests pass.
- New privileged behavior includes capability checks and confirmation design.
- No credentials, local environment files, or generated deployment evidence are committed.
- Public docs, inline help, version metadata, and changelog are updated as needed.
- Licensing headers and the repository license remain consistent.
