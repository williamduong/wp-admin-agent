# Security Guide

The assistant can perform administrator-level operations. Treat it as privileged infrastructure, not as a general public chatbot.

## Existing controls

- REST routes require a logged-in WordPress user with `manage_options`.
- Browser requests use WordPress REST authentication and nonces.
- Provider credentials are encrypted with AES-256-CBC using WordPress key material.
- Provider secrets remain on the server and are not returned to the browser after storage.
- Agent, MCP, and provider-test endpoints are rate-limited.
- Tools have explicit schemas and pass through a central registry.
- High-impact operations can require confirmation.
- Conversations and execution metadata support operational review.

## Deployment hardening

1. Use HTTPS end to end.
2. Restrict administrator accounts, require MFA where available, and remove unused accounts.
3. Use dedicated API keys with the minimum provider permissions and billing limits.
4. Disable every tool that the site's workflow does not need.
5. Stage and back up the site before enabling write, delete, plugin, theme, or user operations.
6. Keep WordPress salts and keys private and stable; changing them may make stored encrypted values unreadable.
7. Protect Ollama and other private endpoints with network controls. Never expose an unauthenticated model server publicly.
8. Configure log retention and avoid submitting secrets or regulated data in prompts.
9. Monitor unusual usage, repeated failures, tool changes, and provider spending.
10. Keep WordPress core, PHP, plugins, and themes patched.

## Prompt injection and untrusted content

Posts, comments, product descriptions, uploaded documents, and remote content may contain instructions intended to manipulate the model. Treat all retrieved content as data, never as authority. A tool should validate explicit user intent, exact targets, types, allowed values, and WordPress capabilities independently of model output.

## Confirmation policy

Require confirmation for at least:

- Deletes and irreversible updates
- Publishing or unpublishing content
- Plugin or theme installation, activation, update, or removal
- User, role, and credential-related changes
- Global settings, database maintenance, and bulk operations
- WooCommerce order or inventory mutations
- Any action with an ambiguous target

A confirmation must identify the action and target. A generic “continue?” is not sufficient for high-risk operations.

## MCP security

The MCP endpoint is an additional interface to privileged tools. Keep it behind WordPress authentication, enforce nonces/cookies according to the client integration, preserve `manage_options` checks, apply rate limits, and expose only enabled tools. Do not copy administrator session material into third-party clients you do not trust.

## Incident response

If unsafe behavior is suspected:

1. Disable the plugin or affected tools.
2. Revoke provider credentials.
3. Preserve WordPress, PHP, web-server, plugin, and provider logs.
4. Review users, sessions, content revisions, plugin/theme changes, and WooCommerce activity.
5. Restore from a known-good backup if required.
6. Fix the validation or authorization gap before re-enabling the workflow.

## Reporting a vulnerability

Do not publish exploitable details in a public issue. Contact the repository owner privately and include affected versions, impact, reproduction steps, and a proposed remediation when possible.
