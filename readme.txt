=== William Research Admin Agent ===
Contributors: williamduongrn
Tags: ai assistant, admin, automation, woocommerce, ollama
Requires at least: 6.5
Tested up to: 7.1
Requires PHP: 8.2
Stable tag: 0.5.0
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
* Guide new administrators through a short setup for bot personality, provider, allowed tools, and safe checks.

The plugin requires a WordPress administrator account with the `manage_options` capability. It does not expose the assistant to public site visitors.

= Source code and build =

The human-readable source for the compiled admin interface and the packaging scripts is maintained at https://github.com/williamduong/wp-admin-agent . To rebuild the JavaScript, run `npm ci` and `npm run build`; the packaging steps are documented in the repository's development guide.

= External services =

The plugin does not contact an AI provider until an administrator selects and configures one and starts a chat or connection test. Sending a chat request can transmit the administrator's prompt, recent conversation context, registered tool schemas, tool results, relevant WordPress site information, and the selected bot name, form of address, and communication style to the selected provider so it can answer the request and select tools.

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
* The plugin identifies the selected feed before it is requested. Built-in presets may contact these publishers:
* Ars Technica (Condé Nast): Terms https://www.condenast.com/user-agreement — Privacy https://www.condenast.com/privacy-policy
* TechCrunch: Terms https://techcrunch.com/terms-of-service/ — Privacy https://techcrunch.com/privacy-policy/
* The Verge (Vox Media): Terms https://www.voxmedia.com/legal/terms-of-use — Privacy https://www.voxmedia.com/legal/privacy-notice
* WIRED (Condé Nast): Terms https://www.condenast.com/user-agreement — Privacy https://www.condenast.com/privacy-policy
* ScienceDaily: Terms https://www.sciencedaily.com/terms.htm — Privacy https://www.sciencedaily.com/privacy.htm
* Phys.org (Science X): Terms https://sciencex.com/help/terms/ — Privacy https://sciencex.com/help/privacy/
* Nature: Terms https://www.nature.com/info/terms-and-conditions — Privacy https://www.nature.com/info/privacy
* NASA: Privacy policy and important notices https://www.nasa.gov/privacy/
* European Space Agency: Terms https://www.esa.int/Services/Terms_and_conditions — Privacy https://www.esa.int/Services/Privacy_notice
* Google News: Terms https://policies.google.com/terms — Privacy https://policies.google.com/privacy
* For a custom RSS URL supplied by the administrator, the terms and privacy policy of that selected publisher apply.

Freemius

* Purpose: Optional opt-in telemetry and account management for the Free plugin. The Free plugin does not discover, download, install, or update Pro.
* Data sent: Only after administrator consent, Freemius may receive site, plugin, administrator, and diagnostic metadata described in its privacy policy. Skipping opt-in keeps the Free plugin usable.
* Pro distribution: The separately distributed Pro plugin is purchased outside WordPress Admin and installed manually by an administrator. Licensing and Pro updates begin only after Pro has been installed.
* Terms: https://freemius.com/terms/
* Privacy: https://freemius.com/privacy/

= Installation =

1. Upload the `william-research-admin-agent` directory to `/wp-content/plugins/`, or install an official release.
2. Activate **William Research Admin Agent** through the Plugins screen.
3. Follow the guided **Settings → Agent Setup** wizard to name the bot and choose its style.
4. Select and configure Anthropic, Gemini, or Ollama; then review enabled tools.
5. Test the provider connection and read-only tools. The wizard can be reopened later.

== Frequently Asked Questions ==

= Does the plugin include AI usage? =

Yes. The plugin provides an AI assistant and sends administrator-initiated chat requests to the selected Anthropic, Google Gemini, or Ollama provider as described in External services. The plugin does not include provider credits: credentials or an Ollama endpoint are supplied by the site administrator, and provider billing and policies belong to that provider account.

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
6. Guided assistant setup with personality, provider, tools, and safe test drive.

== Changelog ==

= 0.5.0 =

* Added a guided first-run setup for assistant identity, AI connection, tool access, and retention.
* Added safe read-only tool checks and an AI connection test before completing setup.
* Made the chosen assistant name and communication style available in the chat and runtime prompt.

= 0.4.4 =

* Replaced short plugin prefixes with distinct names and migrated existing settings, conversations, and logs.
* Removed MIT Technology Review feed presets because the documented privacy URL is unavailable.
* Sites using the separately distributed Pro add-on should update it to 0.1.3 for compatibility with the renamed Free API.

= 0.4.3 =

* Clarified AI usage and documented terms and privacy links for every built-in RSS publisher.
* Moved the Settings screen's static CSS and JavaScript into properly enqueued assets.
* Corrected the WordPress.org contributor username and re-audited global prefixes.

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
