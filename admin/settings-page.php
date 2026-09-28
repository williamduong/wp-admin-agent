<?php defined('ABSPATH') || exit;

$settings     = new WAA_Settings();
$provider     = $settings->get_provider();
$model        = $settings->get_model();
$custom_rules = $settings->get_custom_rules();
$disabled     = $settings->get_disabled_tools();
$pricing      = WAA_Pricing::all_for_js();

// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only tab selection; no data is changed.
$active_tab = isset($_GET['tab']) ? sanitize_key(wp_unslash($_GET['tab'])) : 'provider';

// Build tool list for Tools tab
$all_schemas  = WAA_REST_API::build_registry()->get_schemas(); // no disabled filter here — show all
$settings_tabs = apply_filters('waa_admin_agent_settings_tabs', [
    'provider' => 'Provider & Keys',
    'prompt' => 'System Prompt',
    'tools' => 'Tools (' . count($all_schemas) . ')',
    'docs' => '📖 Docs',
]);

// Load knowledge-base docs for Docs tab
$kb_dir  = WAA_PLUGIN_DIR . 'knowledge-base/';
$kb_docs = [];
foreach (glob($kb_dir . '*.md') as $path) {
    $filename = basename($path);
    if (str_starts_with($filename, '.')) continue;
    $raw_name  = preg_replace('/^\d+-/', '', pathinfo($filename, PATHINFO_FILENAME));
    $label     = ucwords(str_replace('-', ' ', $raw_name));
    $kb_docs[] = ['file' => $filename, 'label' => $label, 'content' => file_get_contents($path)];
}
usort($kb_docs, fn($a, $b) => strcmp($a['file'], $b['file']));

// Build navigate map for Prompt tab
$navigate_map = [
    'update_site_settings' => 'options-general.php',
    'set_site_icon'        => 'options-general.php',
    'install_plugin'       => 'plugins.php',
    'activate_plugin'      => 'plugins.php',
    'deactivate_plugin'    => 'plugins.php',
    'install_theme'        => 'themes.php',
    'switch_theme'         => 'themes.php',
    'update_user_role'     => 'users.php',
    'create_post'          => 'edit.php',
    'update_post'          => 'edit.php',
    'set_post_image'       => 'edit.php',
];
?>
<div class="wrap">
    <h1>William Research Admin Agent — Settings</h1>

    <?php // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only success notice after a nonce-protected save. ?>
    <?php if (isset($_GET['saved'])): ?>
        <div class="notice notice-success is-dismissible"><p>Settings saved.</p></div>
    <?php endif; ?>

    <nav class="nav-tab-wrapper" style="margin-bottom:0">
        <?php foreach ($settings_tabs as $slug => $label): ?>
            <a href="<?php echo esc_url(add_query_arg(['page' => 'wp-admin-agent', 'tab' => $slug], admin_url('admin.php'))); ?>"
               class="nav-tab <?php echo $active_tab === $slug ? 'nav-tab-active' : ''; ?>">
                <?php echo esc_html($label); ?>
            </a>
        <?php endforeach; ?>
    </nav>

    <div style="display:flex;gap:24px;align-items:flex-start;flex-wrap:wrap;margin-top:0">

        <!-- LEFT: Tab content inside form -->
        <div style="flex:1;min-width:380px">
            <form method="post" id="waa-settings-form" style="background:#fff;border:1px solid #c3c4c7;border-top:none;padding:20px">
                <?php wp_nonce_field('waa_settings'); ?>

                <!-- ══════════ TAB: PROVIDER ══════════ -->
                <?php if ($active_tab === 'provider'): ?>
                <input type="hidden" name="tab" value="provider">
                <table class="form-table" role="presentation">

                    <tr>
                        <th><label for="waa_provider">AI Provider</label></th>
                        <td>
                            <select id="waa_provider" name="waa_provider">
                                <option value="anthropic" <?php selected($provider,'anthropic'); ?>>Anthropic (Claude)</option>
                                <option value="gemini"    <?php selected($provider,'gemini'); ?>>Google Gemini</option>
                                <option value="ollama"    <?php selected($provider,'ollama'); ?>>Ollama (Local)</option>
                                <?php if (wp_get_environment_type() !== 'production'): ?>
                                    <option value="fake" <?php selected($provider,'fake'); ?>>Deterministic Showcase (no external API)</option>
                                <?php endif; ?>
                            </select>
                        </td>
                    </tr>

                    <tr>
                        <th><label for="waa_model">Model</label></th>
                        <td>
                            <div style="display:flex;gap:8px;align-items:center">
                                <select id="waa_model" name="waa_model">
                                    <?php foreach ($pricing[$provider] ?? [] as $id => $info): ?>
                                        <option value="<?php echo esc_attr($id); ?>" <?php selected($model,$id); ?>>
                                            <?php echo esc_html($info['label']); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                                <button type="button" id="waa-refresh-models" class="button button-small"
                                        <?php echo $provider !== 'ollama' ? 'style="display:none"' : ''; ?>>
                                    ↺ Refresh
                                </button>
                            </div>
                            <div id="waa-model-info" style="margin-top:6px;font-size:12px;color:#666"></div>
                        </td>
                    </tr>

                    <tr id="row-anthropic" <?php echo $provider !== 'anthropic' ? 'style="display:none"' : ''; ?>>
                        <th><label for="waa_api_key">Anthropic API Key</label></th>
                        <td>
                            <input type="password" id="waa_api_key" name="waa_api_key" class="regular-text"
                                   value="<?php echo $settings->get_api_key() ? '••••••••' : ''; ?>"
                                   placeholder="Enter your Anthropic API key" autocomplete="off">
                            <p class="description">
                                <?php if ($settings->get_api_key()): ?>
                                    <span style="color:green">✓ Key set.</span> Leave blank to keep.
                                <?php else: ?>
                                    <a href="https://console.anthropic.com" target="_blank">console.anthropic.com</a>
                                <?php endif; ?>
                            </p>
                        </td>
                    </tr>

                    <tr id="row-gemini" <?php echo $provider !== 'gemini' ? 'style="display:none"' : ''; ?>>
                        <th><label for="waa_gemini_key">Gemini API Key</label></th>
                        <td>
                            <input type="password" id="waa_gemini_key" name="waa_gemini_key" class="regular-text"
                                   value="<?php echo $settings->get_gemini_api_key() ? '••••••••' : ''; ?>"
                                   placeholder="Enter your Gemini API key" autocomplete="off">
                            <p class="description">
                                <?php if ($settings->get_gemini_api_key()): ?>
                                    <span style="color:green">✓ Key set.</span> Leave blank to keep.
                                <?php else: ?>
                                    <a href="https://aistudio.google.com/app/apikey" target="_blank">aistudio.google.com</a> — free tier available
                                <?php endif; ?>
                            </p>
                        </td>
                    </tr>

                    <tr id="row-ollama" <?php echo $provider !== 'ollama' ? 'style="display:none"' : ''; ?>>
                        <th><label for="waa_ollama_url">Ollama URL</label></th>
                        <td>
                            <input type="text" id="waa_ollama_url" name="waa_ollama_url" class="regular-text"
                                   value="<?php echo esc_attr($settings->get_ollama_url()); ?>">
                            <p class="description">
                                Same machine: <code>http://localhost:11434</code><br>
                                Docker/wp-env local: <code>http://host.docker.internal:11434</code><br>
                                Private network / another host: <code>http://your-ollama-host:11434</code>
                            </p>
                        </td>
                    </tr>
                    <tr id="row-fake" <?php echo $provider !== 'fake' ? 'style="display:none"' : ''; ?>>
                        <th>Showcase mode</th>
                        <td>
                            <p class="description">Uses bundled deterministic fixtures. No prompt, credential, or site data is sent to an external AI provider.</p>
                        </td>
                    </tr>
                    <tr>
                        <th><?php esc_html_e('Data retention', 'william-research-admin-agent'); ?></th>
                        <td>
                            <label for="waa_data_retention_days">
                                <?php esc_html_e('Automatically delete conversations and audit records older than', 'william-research-admin-agent'); ?>
                            </label>
                            <input type="number" id="waa_data_retention_days" name="waa_data_retention_days"
                                   min="7" max="365" step="1"
                                   value="<?php echo esc_attr((string) $settings->get_data_retention_days()); ?>"
                                   style="width:80px">
                            <?php esc_html_e('days.', 'william-research-admin-agent'); ?>
                            <p class="description">
                                <?php esc_html_e('Cleanup runs daily. The default is 30 days; allowed range is 7–365 days.', 'william-research-admin-agent'); ?>
                            </p>
                            <label>
                                <input type="checkbox" name="waa_delete_data_on_uninstall" value="1"
                                    <?php checked($settings->should_delete_data_on_uninstall()); ?>>
                                <?php esc_html_e('Delete settings, encrypted credentials, conversations, and audit logs when the plugin is uninstalled.', 'william-research-admin-agent'); ?>
                            </label>
                            <p class="description">
                                <?php esc_html_e('Disabled by default so operational history is not removed unexpectedly.', 'william-research-admin-agent'); ?>
                            </p>
                        </td>
                    </tr>
                </table>

                <p>
                    <button type="button" id="waa-test-btn" class="button button-secondary">Test Connection</button>
                    <span id="waa-test-result" style="margin-left:10px;font-weight:500"></span>
                </p>
                <?php submit_button('Save Settings'); ?>

                <!-- ══════════ TAB: PROMPT ══════════ -->
                <?php elseif ($active_tab === 'prompt'): ?>
                <input type="hidden" name="tab" value="prompt">

                <h3 style="margin-top:0">System Prompt</h3>
                <p class="description" style="margin-bottom:16px">
                    The runtime prompt is rebuilt on every request. It includes live WordPress context, registered tools, provider-specific guidance, and your custom rules.
                    Confirmation policy and async safety are enforced by runtime code even if the model responds imperfectly.
                </p>

                <!-- Base prompt preview (read-only) -->
                <details style="margin-bottom:20px">
                    <summary style="cursor:pointer;font-weight:600;padding:8px 0;color:#2271b1">
                        View full runtime prompt preview ▾
                    </summary>
                    <pre style="background:#f6f7f7;border:1px solid #e2e4e7;padding:12px;font-size:12px;white-space:pre-wrap;max-height:260px;overflow-y:auto;margin-top:8px"><?php
$provider_obj = WAA_Provider_Factory::make($settings);
$prompt_preview = WAA_Agent::build_system_prompt(
    $provider_obj,
    WAA_REST_API::build_registry($settings->get_disabled_tools()),
    $settings
);
echo esc_html($prompt_preview);
?></pre>
                </details>

                <!-- Model-specific instructions (per-provider, read-only) -->
                <h4 style="margin-bottom:6px">Prompt layers <span style="font-weight:400;color:#666;font-size:12px">(how the final prompt is assembled)</span></h4>
                <ol style="margin:0 0 20px 18px;line-height:1.7">
                    <li><strong>Base runtime prompt</strong> — site context, safety rules, content/media guidance, and registered tool names.</li>
                    <li><strong>Model-specific guidance</strong> — provider rules from <code><?php echo esc_html(get_class($provider_obj)); ?></code>.</li>
                    <li><strong>Custom rules</strong> — your editable instructions, appended last.</li>
                </ol>

                <!-- Custom rules (editable) -->
                <h4 style="margin-bottom:6px">Custom rules <span style="font-weight:400;color:#666;font-size:12px">(appended last, editable)</span></h4>
                <textarea name="waa_custom_rules" rows="8" style="width:100%;font-family:monospace;font-size:13px"
                          placeholder="Add extra instructions here, e.g.:
- Always respond in formal Vietnamese.
- When creating a post, prefer create_draft_post.
- When writing current-event posts, use fetch_rss before drafting.
- When replacing content images, keep the existing post title unchanged unless I explicitly ask."
                ><?php echo esc_textarea($custom_rules); ?></textarea>
                <p class="description">Plain text. One rule per line recommended. Best for tone, defaults, editorial policy, and tool preferences. Runtime confirmation rules still apply even if you ask the model to be more aggressive.</p>

                <!-- Auto-navigate map -->
                <h4 style="margin-top:20px;margin-bottom:8px">Auto-navigate map <span style="font-weight:400;color:#666;font-size:12px">(best-effort redirect after successful write actions)</span></h4>
                <table class="widefat striped" style="font-size:12px">
                    <thead><tr><th>Tool</th><th>Navigates to</th></tr></thead>
                    <tbody>
                    <?php foreach ($navigate_map as $tool => $path): ?>
                        <tr>
                            <td><code><?php echo esc_html($tool); ?></code></td>
                            <td><a href="<?php echo esc_url(admin_url($path)); ?>" target="_blank"><?php echo esc_html($path); ?></a></td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>

                <?php submit_button('Save Prompt Settings'); ?>

                <!-- ══════════ TAB: TOOLS ══════════ -->
                <?php elseif ($active_tab === 'tools'): ?>
                <input type="hidden" name="tab" value="tools">

                <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:12px">
                    <div>
                        <h3 style="margin:0">Available Tools</h3>
                        <p class="description" style="margin:4px 0 0">
                            <?php echo esc_html((string) count($all_schemas)); ?> tools registered.
                            Disabled tools are hidden from the AI — it cannot call them.
                        </p>
                    </div>
                    <div style="display:flex;gap:8px">
                        <button type="button" class="button button-small" data-waa-toggle-all="1">Enable all</button>
                        <button type="button" class="button button-small" data-waa-toggle-all="0">Disable all</button>
                    </div>
                </div>

                <table class="widefat" id="waa-tools-table">
                    <thead>
                        <tr>
                            <th style="width:36px">On</th>
                            <th style="width:200px">Tool name</th>
                            <th>Description</th>
                            <th style="width:80px">Schema</th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php foreach ($all_schemas as $schema):
                        $name      = $schema['name'];
                        $is_on     = !in_array($name, $disabled, true);
                        $schema_js = wp_json_encode($schema['input_schema'], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
                    ?>
                        <tr id="tool-row-<?php echo esc_attr($name); ?>" class="<?php echo $is_on ? '' : 'waa-tool-disabled'; ?>">
                            <td>
                                <input type="checkbox" name="waa_tool_<?php echo esc_attr($name); ?>"
                                       value="1" <?php checked($is_on); ?>
                                       data-tool-name="<?php echo esc_attr($name); ?>">
                            </td>
                            <td><code style="font-size:12px"><?php echo esc_html($name); ?></code></td>
                            <td style="font-size:13px;color:<?php echo $is_on ? '#111' : '#999'; ?>">
                                <?php echo esc_html($schema['description']); ?>
                            </td>
                            <td>
                                <button type="button" class="button button-small"
                                        data-tool-name="<?php echo esc_attr($name); ?>"
                                         data-schema="<?php echo esc_attr($schema_js); ?>">
                                    JSON ▾
                                </button>
                            </td>
                        </tr>
                        <tr id="schema-row-<?php echo esc_attr($name); ?>" style="display:none">
                            <td colspan="4" style="padding:0">
                                <pre id="schema-pre-<?php echo esc_attr($name); ?>"
                                     style="background:#f6f7f7;margin:0;padding:10px 16px;font-size:11px;overflow-x:auto"></pre>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>

                <h4 style="margin-top:24px;margin-bottom:6px">Debug mode <span style="font-weight:400;color:#666;font-size:12px">(tool call log in chat)</span></h4>
                <select name="waa_debug_mode">
                    <?php foreach (['off' => 'Off', 'compact' => 'Compact — errors only', 'full' => 'Full — show inputs & outputs'] as $val => $label): ?>
                        <option value="<?php echo esc_attr($val); ?>" <?php selected($settings->get_debug_mode(), $val); ?>><?php echo esc_html($label); ?></option>
                    <?php endforeach; ?>
                </select>
                <p class="description" style="margin-top:4px">Controls tool execution detail shown in the chat widget. "Full" is useful for debugging.</p>

                <?php submit_button('Save Tool Settings'); ?>

                <!-- ══════════ TAB: DOCS ══════════ -->
                <?php elseif ($active_tab === 'docs'): ?>
                <div id="waa-docs-wrap" style="display:flex;gap:0;min-height:500px">

                    <!-- Sidebar: file tree -->
                    <div id="waa-docs-tree" style="width:200px;flex-shrink:0;border-right:1px solid #e2e4e7;padding:12px 0">
                        <?php foreach ($kb_docs as $i => $doc): ?>
                        <button type="button"
                                class="waa-doc-link <?php echo $i === 0 ? 'waa-doc-active' : ''; ?>"
                                data-index="<?php echo esc_attr((string) $i); ?>"
                                >
                            📄 <?php echo esc_html($doc['label']); ?>
                        </button>
                        <?php endforeach; ?>
                        <?php if (empty($kb_docs)): ?>
                            <p style="padding:12px;color:#999;font-size:12px">No docs found in <code>knowledge-base/</code></p>
                        <?php endif; ?>
                    </div>

                    <!-- Content pane -->
                    <div id="waa-docs-content" style="flex:1;padding:20px 24px;overflow-y:auto;max-height:75vh"></div>

                </div>

                <?php else: ?>
                    <?php do_action('waa_admin_agent_render_settings_tab', $active_tab, $settings); ?>
                <?php endif; ?>

            </form>
        </div>

        <!-- RIGHT: Pricing + Stats (always visible) -->
        <div style="min-width:280px;margin-top:0">
            <div style="background:#fff;border:1px solid #c3c4c7;padding:16px">
                <h3 style="margin-top:0">Pricing <span style="font-size:12px;font-weight:400;color:#666">(USD / 1M tokens)</span></h3>
                <div id="waa-pricing-table"></div>

                <h3 style="margin-top:20px">Usage (last 30 days)</h3>
                <div id="waa-stats-panel"><em style="color:#999;font-size:13px">Loading…</em></div>

                <h3 style="margin-top:20px">MCP Endpoint</h3>
                <p style="font-size:12px;color:#555;margin:0">
                    Connect any MCP client (Claude Desktop, Claude Code) to:<br>
                    <code style="word-break:break-all"><?php echo esc_html(rest_url('wp-admin-agent/v1/mcp')); ?></code>
                </p>
            </div>
        </div>

    </div>
</div>
