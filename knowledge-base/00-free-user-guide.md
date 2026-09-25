# WP Admin Agent Free — Quick Guide

## What the Free edition does

WP Admin Agent Free connects an administrator-selected Anthropic, Google Gemini, or Ollama model to a controlled set of WordPress tools. It can inspect site state, navigate WordPress Admin, retrieve selected feeds, and create post drafts for review.

## Safe first requests

- Show active plugins and summarize their purpose.
- List recent draft posts.
- Show installed themes without changing anything.
- Create a draft outline for a product update. Do not publish it.
- Show WooCommerce orders that need attention without changing their status.

## Provider setup

Open **Settings → WP Admin Agent**, select a provider, enter the provider configuration, and use **Test Connection**. Credentials are encrypted before storage using WordPress security keys.

## Tool controls

Open the **Tools** tab to disable any capability that the site does not need. The Free edition exposes 15 inspection, navigation, and draft-oriented tools. It does not install software, change user roles, publish content, modify WooCommerce data, or change global settings.

## Privacy

Prompts, recent conversation context, tool schemas, tool results, and relevant site information may be sent to the selected AI provider. Avoid sending unnecessary personal, confidential, or regulated information. Review the provider's terms and privacy policy before enabling it.

## Data retention

Conversations and audit metadata are stored in the WordPress database. Data is preserved by default when the plugin is removed. Enable **Delete data on uninstall** before uninstalling if the site owner wants the plugin's settings, credentials, conversations, logs, and custom tables removed.

## Pro add-on

WP Admin Agent Pro is a separate plugin. It can add higher-risk operational tools, WooCommerce mutations, media workflows, Mermaid rendering, advanced policies, and commercial update/support services. Free continues to work when Pro is not installed.
