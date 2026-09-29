import { readFileSync, writeFileSync, mkdirSync } from 'node:fs';
import { resolve } from 'node:path';
import { runInNewContext } from 'node:vm';

export function extractBundleCss() {
    const bundlePath = resolve('assets/js/admin-agent.js');
    const cssPath = resolve('assets/css/admin-agent.css');
    const bundle = readFileSync(bundlePath, 'utf8');
    const injectedStyle = /^(\(function\([^)]*\)\{)var ([\w$]+)=document\.createElement\(`style`\);\2\.textContent=(`(?:\\.|[^`])*`)[,;]document\.head\.appendChild\(\2\);/;
    const match = bundle.match(injectedStyle);

    if (!match) {
        throw new Error('Expected Vite CSS injection was not found; inspect the bundle before packaging.');
    }

    const css = runInNewContext(match[3]);
    if (typeof css !== 'string' || css.length === 0) {
        throw new Error('Vite produced an empty stylesheet.');
    }

    mkdirSync(resolve('assets/css'), { recursive: true });
    writeFileSync(cssPath, css);
    writeFileSync(bundlePath, bundle.replace(injectedStyle, match[1]));
    console.log(`Extracted ${css.length} CSS characters to ${cssPath}`);
}
