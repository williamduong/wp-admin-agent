=== William Research Admin Agent ===
Contributors: williamduong
Tags: ai assistant, admin, automation, woocommerce, ollama
Requires at least: 6.5
Tested up to: 7.1
Requires PHP: 8.2
Stable tag: 0.4.2
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

A privacy-conscious AI assistant for safe WordPress administration, site inspection, and draft workflows.

== Description ==

William Research Admin Agent adds a conversational AI assistant to WordPress Admin. Administrators connect their own Anthropic, Google Gemini, or Ollama provider and choose which WordPress tools the assistant may use.

The Free edition focuses on safe workflows:

* Inspect posts, plugins, themes, users, WooCommerce state, and selected security-plugin state.
* Navigate WordPress Admin from natural-language requests.
* Create WordPress drafts without automatically publishing them.
* Stream responses and tool progress in the admin interface.
* Store conversation history and execution metadata in WordPress.
* Disable tools that a site does not need.
* Encrypt provider credentials using WordPress security keys.

The plugin requires a WordPress administrator account with the `manage_options` capability. It does not expose the assistant to public site visitors.

= External services =

The plugin does not contact an AI provider until an administrator selects and configures one. Sending a chat request can transmit the administrator's prompt, recent conversation context, registered tool schemas, tool results, and relevant WordPress site information to the selected provider so it can answer the request and select tools.

Anthropic API

* Purpose: Generate assistant responses and tool calls when Anthropic is selected.
* Data sent: Prompts, conversation context, tool schemas, tool results, and relevant site context.
* Terms: https://www.anthropic.com/legal/commercial-terms
* Privacy: https://www.anthropic.com/legal/privacy

Google Gemini API

* Purpose: Generate assistant responses and tool calls when Gemini is selected.
* Data sent: Prompts, conversation context, tool schemas, tool results, and relevant site context.
* Terms: https://ai.google.dev/gemini-api/terms
* Privacy: https://policies.google.com/privacy

Ollama

* Purpose: Generate assistant responses and tool calls on an administrator-configured Ollama server.
* Data sent: The same assistant context described above, to the URL configured by the administrator.
* The site owner is responsible for the privacy and security policy of that server.
* Project information: https://ollama.com/

Iconify API

* Purpose: Search for icon candidates only when an administrator requests an icon search.
* Data sent: The search term.
* API terms: https://iconify.design/docs/api/
* Privacy: https://iconify.design/privacy/

RSS publishers

* Purpose: Fetch a selected public RSS feed only when an administrator requests current news or feed content.
* Data sent: A normal HTTP request from the WordPress server. The publisher receives standard request metadata such as IP address and user agent.
* Each publisher's terms and privacy policy apply. The plugin identifies the selected feed before it is requested.

Freemius

* Purpose: Optional opt-in telemetry and account management for the Free plugin. The Free plugin does not discover, download, install, or update Pro.
* Data sent: Only after administrator consent, Freemius may receive site, plugin, administrator, and diagnostic metadata described in its privacy policy. Skipping opt-in keeps the Free plugin usable.
* Pro distribution: The separately distributed Pro plugin is purchased outside WordPress Admin and installed manually by an administrator. Licensing and Pro updates begin only after Pro has been installed.
* Terms: https://freemius.com/terms/
* Privacy: https://freemius.com/privacy/

= Installation =

1. Upload the `william-research-admin-agent` directory to `/wp-content/plugins/`, or install an official release.
2. Activate **William Research Admin Agent** through the Plugins screen.
3. Open **Settings → William Research Admin Agent**.
4. Select and configure Anthropic, Gemini, or Ollama.
5. Test the provider connection.
6. Review enabled tools before using the assistant.

== Frequently Asked Questions ==

= Does the plugin include AI usage? =

No. The Free edition uses credentials or an Ollama endpoint supplied by the site administrator. Provider billing and policies belong to that provider account.

= Can the Free edition publish content? =

The Free content tool creates drafts only. Publishing and other higher-risk operational workflows are not part of the Free package.

= Where are provider keys stored? =

Keys are encrypted before they are stored in WordPress options. The encryption uses WordPress security key material. Site owners should still secure database backups and administrator accounts.

= What happens to data on uninstall? =

Data is preserved by default. Administrators can enable deletion before uninstalling. When enabled, plugin settings, encrypted credentials, conversations, logs, and plugin tables are deleted during uninstall.

= Does the plugin send telemetry? =

The Free edition does not send product telemetry by default. Calls required to fulfill an administrator's selected provider or tool request are described in the External services section.

== Screenshots ==

1. Assistant launcher in WordPress Admin.
2. Streaming chat and tool activity.
3. Provider and model settings.
4. Tool registry and enable/disable controls.
5. Built-in documentation.

== Changelog ==

= 0.4.2 =

* Disabled Freemius add-on discovery, marketplace, pricing, download, installation, and update paths in the WordPress.org Free package.
* Kept optional opt-in telemetry and account access while requiring administrators to purchase and install the separately distributed Pro plugin manually.

= 0.4.1 =

* Add the WordPress.org-compliant Freemius SDK integration for optional telemetry, account management, and discovery of the separately distributed Pro add-on.
* Keep Free features available when the administrator skips Freemius opt-in.

= 0.4.0 =

* Bound confirmations to expiring, single-use server-side actions and restricted MCP to audited read-only tools.
* Added recursive sensitive-data redaction, authenticated conversation encryption, retention controls, and privacy export/erase support.
* Hardened remote requests, redirects, media imports, browser rendering, and administrator navigation.
* Added per-tool capabilities, multisite lifecycle support, dependency audits, immutable CI actions, and expanded security tests.

= 0.3.1 =

* Adopted the William Research product identity and WordPress.org-ready package slug.
* Resolved Plugin Check findings for escaping, database preparation, file handling, and request sanitization.
* Added the official WordPress Plugin Check action to continuous integration.

= 0.3.0 =

* Introduced the GPL Free edition and extension hook for the Pro add-on.
* Limited Free content creation to drafts.
* Added privacy-policy guidance and optional uninstall cleanup.
* Separated higher-risk write, installation, media, WooCommerce mutation, Wordfence mutation, and Mermaid features into Pro.
* Added WordPress.org packaging and external-service documentation.

= 0.2.0 =

* Added multi-provider chat, tool workflows, conversation history, audit metadata, and MCP support.
