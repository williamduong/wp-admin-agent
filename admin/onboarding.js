/* global wradminSetupData */

const root = document.getElementById('wradmin-setup');

if (root) {
    const form = document.getElementById('wradmin-setup-form');
    const provider = document.getElementById('wradmin-setup-provider');
    const model = document.getElementById('wradmin-setup-model');

    if (form) {
        const steps = [...form.querySelectorAll('[data-wradmin-step]')];
        const progress = [...root.querySelectorAll('.wradmin-setup-progress > span')];
        const back = document.getElementById('wradmin-setup-back');
        const next = document.getElementById('wradmin-setup-next');
        const save = document.getElementById('wradmin-setup-save');
        let current = 0;

        const showStep = (index) => {
            current = Math.max(0, Math.min(steps.length - 1, index));
            steps.forEach((step, i) => step.classList.toggle('is-current', i === current));
            progress.forEach((item, i) => {
                item.classList.toggle('is-current', i === current);
                item.classList.toggle('is-done', i < current);
            });
            back.hidden = current === 0;
            next.hidden = current === steps.length - 1;
            save.hidden = current !== steps.length - 1;
            next.textContent = current === 0 ? 'Let’s begin' : 'Continue';
            steps[current].querySelector('h1')?.focus({ preventScroll: true });
            root.scrollIntoView({ behavior: 'smooth', block: 'start' });
        };

        next.addEventListener('click', () => {
            const required = [...steps[current].querySelectorAll('[required]')];
            if (required.some(input => !input.reportValidity())) return;
            showStep(current + 1);
        });
        back.addEventListener('click', () => showStep(current - 1));
        form.addEventListener('submit', (event) => {
            const name = document.getElementById('wradmin-bot-name');
            name.value = name.value.trim();
            if (!name.reportValidity() || !model.value) {
                event.preventDefault();
                showStep(name.validity.valid ? 2 : 1);
            }
        });

        const refreshProvider = (keepCurrent = false) => {
            const selected = provider.value;
            root.querySelectorAll('[data-provider-field]').forEach(field => {
                const active = field.dataset.providerField === selected;
                field.hidden = !active;
                field.querySelectorAll('input').forEach(input => { input.disabled = !active; });
            });
            const previous = keepCurrent ? model.dataset.current : '';
            model.replaceChildren();
            const models = wradminSetupData.pricing[selected] || {};
            Object.entries(models).forEach(([id, info]) => {
                const option = new Option(info.label, id);
                model.add(option);
            });
            if (previous && Object.hasOwn(models, previous)) model.value = previous;
        };
        provider.addEventListener('change', () => refreshProvider());
        refreshProvider(true);

        const name = document.getElementById('wradmin-bot-name');
        const title = document.getElementById('wradmin-user-title');
        const updatePreview = () => {
            const botName = name.value.trim() || 'William Research Admin Agent';
            const userTitle = title.value.trim();
            const style = form.querySelector('input[name="wradmin_bot_style"]:checked')?.value || 'friendly';
            const sample = {
                friendly: `Hi${userTitle ? `, ${userTitle}` : ''}! What shall we work on today? 😊`,
                concise: `Ready${userTitle ? `, ${userTitle}` : ''}. What is the task?`,
                professional: `Hello${userTitle ? `, ${userTitle}` : ''}. How may I assist you?`,
                coach: `Hi${userTitle ? `, ${userTitle}` : ''}! Tell me your goal and we’ll take it step by step.`,
            };
            document.getElementById('wradmin-preview-name').textContent = botName;
            document.getElementById('wradmin-preview-message').textContent = sample[style];
        };
        form.addEventListener('input', updatePreview);
        updatePreview();
        showStep(0);
    }

    root.querySelectorAll('[data-wradmin-test]').forEach(button => {
        button.addEventListener('click', async () => {
            const name = button.dataset.wradminTest;
            const result = root.querySelector(`[data-wradmin-result="${name}"]`);
            result.textContent = 'Testing…';
            result.classList.remove('is-success', 'is-error');
            button.disabled = true;
            const isConnection = name === 'connection';
            try {
                const response = await fetch(wradminSetupData.restUrl + (isConnection ? 'test-connection' : 'setup/test-tool'), {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json', 'X-WP-Nonce': wradminSetupData.nonce },
                    body: JSON.stringify(isConnection ? {} : { name }),
                });
                const data = await response.json();
                if (!response.ok || !data.success) throw new Error(data.error || data.message || `HTTP ${response.status}`);
                result.textContent = '✓ ' + (isConnection ? `${data.provider}: ${data.reply || 'Connected.'}` : data.message);
                result.classList.add('is-success');
            } catch (error) {
                result.textContent = 'Could not complete test: ' + error.message;
                result.classList.add('is-error');
            } finally {
                button.disabled = false;
            }
        });
    });

    document.getElementById('wradmin-open-bot')?.addEventListener('click', () => {
        const toggle = document.querySelector('button[aria-label="Open AI assistant"]');
        toggle?.click();
        if (!toggle) window.location.href = wradminSetupData.settingsUrl || 'options-general.php?page=wp-admin-agent';
    });
}
