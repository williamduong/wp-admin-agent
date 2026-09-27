import { readFile } from 'node:fs/promises';
import { resolve } from 'node:path';
import { runCLI } from '@wp-playground/cli';

export default async function globalSetup() {
    const blueprint = JSON.parse(
        await readFile(new URL('./blueprint.json', import.meta.url), 'utf8'),
    );

    // Starting Playground inside Playwright's global setup avoids readiness
    // polling while WordPress and the activation hook hold filesystem locks.
    const cli = await runCLI({
        command: 'server',
        port: 9400,
        // The suite is serial. A single PHP worker avoids Playground's shared
        // auto_prepend_file state occasionally being null in pooled workers.
        workers: 1,
        'mount-before-install': [
            {
                hostPath: resolve('.'),
                vfsPath: '/wordpress/wp-content/plugins/william-research-admin-agent',
            },
        ],
        blueprint,
    });

    return async () => {
        await cli[Symbol.asyncDispose]();
    };
}
