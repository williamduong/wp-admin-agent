/* global wradminData, wradminSettingsData */

const WRADMIN_PRICING = wradminSettingsData.pricing || {};
const WRADMIN_DOCS = wradminSettingsData.docs || [];
let currentProvider = wradminSettingsData.provider || '';
let currentModel = wradminSettingsData.model || '';

// ── Provider tab ─────────────────────────────────────────────────────────────

function wradminOnProviderChange(p) {
    currentProvider = p;
    ['anthropic','gemini','ollama','fake'].forEach(id => {
        const row = document.getElementById('row-' + id);
        if (row) {
            row.style.display = (p === id) ? '' : 'none';
        }
    });
    const refreshButton = document.getElementById('wradmin-refresh-models');
    if (refreshButton) {
        refreshButton.style.display = p === 'ollama' ? '' : 'none';
    }
    const sel    = document.getElementById('wradmin_model');
    const models = WRADMIN_PRICING[p] ?? {};
    if (!sel) {
        return;
    }
    sel.replaceChildren();
    for (const [value, item] of Object.entries(models)) {
        const option = document.createElement('option');
        option.value = value;
        option.textContent = item.label;
        sel.append(option);
    }
    currentModel = sel.value;
    wradminBuildPricingTable();
    wradminUpdateModelInfo();
}

function wradminOnModelChange(m) {
    currentModel = m;
    wradminBuildPricingTable();
    wradminUpdateModelInfo();
}

function wradminUpdateModelInfo() {
    const info = WRADMIN_PRICING[currentProvider]?.[currentModel];
    const el   = document.getElementById('wradmin-model-info');
    if (!el) {
        return;
    }
    if (!info) { el.textContent = ''; return; }
    const ctx  = info.ctx >= 1000000 ? (info.ctx/1000000).toFixed(1)+'M' : (info.ctx/1000)+'K';
    el.innerHTML = info.free
        ? `Context: ${ctx} tokens · <span class="wradmin-free">Free (local)</span>`
        : `Context: ${ctx} tokens · $${info.in}/M in · $${info.out}/M out`;
}

async function wradminRefreshOllamaModels() {
    const btn = document.getElementById('wradmin-refresh-models');
    btn.textContent = '…'; btn.disabled = true;
    try {
        const res  = await fetch(wradminData.restUrl + 'ollama-models', { headers: { 'X-WP-Nonce': wradminData.nonce } });
        const data = await res.json();
        if (data.error) { alert('Ollama error: ' + data.error); return; }
        const fetched = {};
        for (const [id, label] of Object.entries(data.models)) {
            fetched[id] = { label, ctx: 128000, in: 0, out: 0, free: true };
        }
        WRADMIN_PRICING.ollama = fetched;
        const sel = document.getElementById('wradmin_model');
        sel.replaceChildren();
        for (const [value, item] of Object.entries(fetched)) {
            const option = document.createElement('option');
            option.value = value;
            option.textContent = item.label;
            sel.append(option);
        }
        currentModel = sel.value;
        wradminBuildPricingTable(); wradminUpdateModelInfo();
    } catch(e) { alert('Could not reach Ollama: ' + e.message); }
    finally { btn.textContent = '↺ Refresh'; btn.disabled = false; }
}

// Test connection
document.getElementById('wradmin-test-btn')?.addEventListener('click', async function() {
    const el = document.getElementById('wradmin-test-result');
    el.style.color = '#666'; el.textContent = 'Testing…';
    // Send current form values so the test uses live selections, not only saved DB values
    const body = {
        provider:   document.getElementById('wradmin_provider')?.value  ?? '',
        model:      document.getElementById('wradmin_model')?.value     ?? '',
        api_key:    document.getElementById('wradmin_api_key')?.value   ?? '',
        gemini_key: document.getElementById('wradmin_gemini_key')?.value ?? '',
        ollama_url: document.getElementById('wradmin_ollama_url')?.value ?? '',
    };
    try {
        const res  = await fetch(wradminData.restUrl + 'test-connection', {
            method: 'POST',
            headers: { 'X-WP-Nonce': wradminData.nonce, 'Content-Type': 'application/json' },
            body: JSON.stringify(body),
        });
        const data = await res.json();
        if (data.success) {
            el.style.color = 'green';
            el.textContent = `✅ ${data.provider} · ${data.model} · "${data.reply}"`;
        } else {
            el.style.color = '#d63638';
            el.textContent = '❌ ' + (data.error ?? `HTTP ${res.status}`);
        }
    } catch(e) { el.style.color = '#d63638'; el.textContent = '❌ ' + e.message; }
});

// ── Tools tab ─────────────────────────────────────────────────────────────────

function wradminToolToggle(name, enabled) {
    const row = document.getElementById('tool-row-' + name);
    if (!row) return;
    row.classList.toggle('wradmin-tool-disabled', !enabled);
    const descCell = row.cells[2];
    if (descCell) descCell.style.color = enabled ? '#111' : '#999';
}

function wradminToggleAll(enabled) {
    document.querySelectorAll('#wradmin-tools-table input[type=checkbox]').forEach(cb => {
        cb.checked = enabled;
        wradminToolToggle(cb.name.replace('wradmin_tool_', ''), enabled);
    });
}

function wradminShowSchema(name, btn) {
    const row = document.getElementById('schema-row-' + name);
    const pre = document.getElementById('schema-pre-' + name);
    if (!row || !pre) return;
    const isVisible = row.style.display !== 'none';
    row.style.display = isVisible ? 'none' : '';
    btn.textContent   = isVisible ? 'JSON ▾' : 'JSON ▴';
    if (!isVisible && !pre.textContent) {
        try { pre.textContent = JSON.stringify(JSON.parse(btn.dataset.schema), null, 2); }
        catch { pre.textContent = btn.dataset.schema; }
    }
}

// ── Pricing + Stats (always) ──────────────────────────────────────────────────

function wradminBuildPricingTable() {
    const panel = document.getElementById('wradmin-pricing-table');
    if (!panel) {
        return;
    }
    const models = WRADMIN_PRICING[currentProvider] ?? {};
    const table = document.createElement('table');
    table.className = 'wradmin-pricing-table';
    const header = table.createTHead().insertRow();
    for (const label of ['Model', 'Input', 'Output', 'Context']) {
        const th = document.createElement('th');
        th.textContent = label;
        header.append(th);
    }
    const tbody = table.createTBody();
    for (const [id, m] of Object.entries(models)) {
        const row = tbody.insertRow();
        if (id === currentModel) row.className = 'active';
        const ctx    = m.ctx >= 1000000 ? (m.ctx/1000000).toFixed(1)+'M' : (m.ctx/1000)+'K';
        row.insertCell().textContent = m.label;
        if (m.free) {
            const freeCell = row.insertCell();
            freeCell.className = 'wradmin-free';
            freeCell.colSpan = 2;
            freeCell.textContent = 'Free';
        } else {
            row.insertCell().textContent = `$${m.in}/M`;
            row.insertCell().textContent = `$${m.out}/M`;
        }
        row.insertCell().textContent = ctx;
    }
    panel.replaceChildren(table);
}

async function wradminLoadStats() {
    const panel = document.getElementById('wradmin-stats-panel');
    if (!panel) {
        return;
    }
    try {
        const res  = await fetch(wradminData.restUrl + 'stats?period=30', { headers: { 'X-WP-Nonce': wradminData.nonce } });
        const data = await res.json();
        const t    = data.totals ?? {};
        const totalIn  = parseInt(t.total_input  ?? 0);
        const totalOut = parseInt(t.total_output ?? 0);
        const fmtT = n => n >= 1000000 ? (n/1000000).toFixed(2)+'M' : n >= 1000 ? (n/1000).toFixed(1)+'K' : String(n||0);
        const fmtC = c => c === 0 ? 'Free' : c < 0.01 ? `$${c.toFixed(6)}` : `$${c.toFixed(4)}`;
        const fragment = document.createDocumentFragment();
        const grid = document.createElement('div');
        grid.className = 'wradmin-stat-grid';
        for (const [value, label] of [
            [String(t.total_calls || 0), 'Tool calls'],
            [fmtC(Number(data.total_cost || 0)), 'Est. cost'],
            [fmtT(totalIn), 'Input tokens'],
            [fmtT(totalOut), 'Output tokens'],
        ]) {
            const card = document.createElement('div');
            card.className = 'wradmin-stat-card';
            const valueNode = document.createElement('div');
            valueNode.className = 'value';
            valueNode.textContent = value;
            const labelNode = document.createElement('div');
            labelNode.className = 'label';
            labelNode.textContent = label;
            card.append(valueNode, labelNode);
            grid.append(card);
        }
        fragment.append(grid);
        if (data.by_model?.length) {
            const table = document.createElement('table');
            table.className = 'wradmin-pricing-table';
            table.style.marginTop = '8px';
            const head = table.createTHead().insertRow();
            for (const heading of ['Model', 'Calls', 'Tokens', 'Cost']) {
                const cell = document.createElement('th');
                cell.textContent = heading;
                head.append(cell);
            }
            const body = table.createTBody();
            for (const r of data.by_model) {
                const tok = fmtT(parseInt(r.input_tokens)+parseInt(r.output_tokens));
                const row = body.insertRow();
                for (const value of [r.model || r.provider || '', r.calls || 0, tok, fmtC(parseFloat(r.cost_usd || 0))]) {
                    row.insertCell().textContent = String(value);
                }
            }
            fragment.append(table);
        }
        panel.replaceChildren(fragment);
    } catch {
        const error = document.createElement('em');
        error.style.color = '#d63638';
        error.textContent = 'Could not load stats.';
        panel.replaceChildren(error);
    }
}

// ── Docs tab ──────────────────────────────────────────────────────────────────


function wradminShowDoc(index) {
    document.querySelectorAll('.wradmin-doc-link').forEach((b, i) =>
        b.classList.toggle('wradmin-doc-active', i === index)
    );
    const doc = WRADMIN_DOCS[index];
    const panel = document.getElementById('wradmin-docs-content');
    if (!doc || !panel) return;
    panel.innerHTML = wradminMarkdown(doc.content);
}

// Minimal Markdown → HTML renderer
function wradminMarkdown(md) {
    // Escape HTML first
    const esc = s => s.replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;').replace(/'/g,'&#39;');
    const lines = md.split('\n');
    let html = '', inCode = false, codeLines = [], inTable = false, tableRows = [];

    const flushTable = () => {
        if (!tableRows.length) return;
        let t = '<table class="wradmin-doc-table">';
        tableRows.forEach((row, i) => {
            const cells = row.split('|').filter((_, ci, a) => ci > 0 && ci < a.length - 1);
            t += '<tr>' + cells.map(c => i === 0 ? `<th>${inlineFormat(esc(c.trim()))}</th>` : `<td>${inlineFormat(esc(c.trim()))}</td>`).join('') + '</tr>';
        });
        html += t + '</table>';
        tableRows = []; inTable = false;
    };

    const inlineFormat = s => s
        .replace(/`([^`]+)`/g, '<code>$1</code>')
        .replace(/\*\*([^*]+)\*\*/g, '<strong>$1</strong>')
        .replace(/\*([^*]+)\*/g, '<em>$1</em>')
        .replace(/\[([^\]]+)\]\(([^)]+)\)/g, (_match, label, href) => {
            if (!/^https?:\/\/[^\s]+$/i.test(href) && !/^\/[a-z0-9/_#?&=.%+-]*$/i.test(href)) {
                return label;
            }
            return `<a href="${href}" target="_blank" rel="noopener noreferrer">${label}</a>`;
        });

    for (let i = 0; i < lines.length; i++) {
        const line = lines[i];

        // Code fence
        if (line.startsWith('```')) {
            if (!inCode) { inCode = true; codeLines = []; }
            else {
                html += `<pre class="wradmin-doc-code"><code>${esc(codeLines.join('\n'))}</code></pre>`;
                inCode = false; codeLines = [];
            }
            continue;
        }
        if (inCode) { codeLines.push(line); continue; }

        // Table rows
        if (line.startsWith('|')) {
            if (!inTable) inTable = true;
            if (!/^\|[-| ]+\|$/.test(line)) tableRows.push(line);
            continue;
        } else if (inTable) { flushTable(); }

        // Headings
        if (/^#{1,6} /.test(line)) {
            const lvl = line.match(/^(#+)/)[1].length;
            html += `<h${lvl} class="wradmin-doc-h">${inlineFormat(esc(line.slice(lvl + 1)))}</h${lvl}>`;
            continue;
        }
        // HR
        if (/^---+$/.test(line.trim())) { html += '<hr>'; continue; }
        // List
        if (/^[-*] /.test(line)) { html += `<li>${inlineFormat(esc(line.slice(2)))}</li>`; continue; }
        if (/^\d+\. /.test(line)) { html += `<li>${inlineFormat(esc(line.replace(/^\d+\. /, '')))}</li>`; continue; }
        // Blank
        if (line.trim() === '') { html += '<br>'; continue; }
        // Paragraph
        html += `<p class="wradmin-doc-p">${inlineFormat(esc(line))}</p>`;
    }
    if (inTable) flushTable();
    return html;
}

if (WRADMIN_DOCS.length && document.getElementById('wradmin-docs-content')) wradminShowDoc(0);

document.getElementById('wradmin_provider')?.addEventListener('change', (event) => wradminOnProviderChange(event.target.value));
document.getElementById('wradmin_model')?.addEventListener('change', (event) => wradminOnModelChange(event.target.value));
document.getElementById('wradmin-refresh-models')?.addEventListener('click', wradminRefreshOllamaModels);
document.querySelectorAll('[data-wradmin-toggle-all]').forEach((button) => {
    button.addEventListener('click', () => wradminToggleAll(button.dataset.wradminToggleAll === '1'));
});
document.querySelectorAll('#wradmin-tools-table input[data-tool-name]').forEach((checkbox) => {
    checkbox.addEventListener('change', () => wradminToolToggle(checkbox.dataset.toolName, checkbox.checked));
});
document.querySelectorAll('#wradmin-tools-table button[data-schema][data-tool-name]').forEach((button) => {
    button.addEventListener('click', () => wradminShowSchema(button.dataset.toolName, button));
});
document.querySelectorAll('.wradmin-doc-link[data-index]').forEach((button) => {
    button.addEventListener('click', () => wradminShowDoc(Number.parseInt(button.dataset.index, 10)));
});

// Init
wradminBuildPricingTable();
wradminUpdateModelInfo();
wradminLoadStats();
