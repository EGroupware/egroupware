/**
 * @web/test-runner (dev-server) counterpart to rollup-legacy-widget-shim.mjs.
 *
 * web-test-runner resolves/serves modules through its own esbuild dev-server
 * pipeline, completely separate from rollup.config.js, so the rollup plugin alone
 * doesn't help test files that transitively import one of the "1a-shim"
 * et2_widget_*.ts specifiers (eg. et2_extension_nextmatch.ts -> et2_widget_selectbox).
 * This synthesizes the same virtual module via the dev-server's resolveImport/serve
 * hooks instead of rollup's resolveId/load. Manifest is shared with the rollup plugin.
 */
import path from 'path';
import {SHIM_MANIFEST} from './rollup-legacy-widget-shim.mjs';

const VIRTUAL_ROOT = '/__legacy_widget_shim__/';

// Manifest targetModule paths are relative to api/js/etemplate/ (where the real
// et2_widget_*.ts files used to live) - a fixed, server-root-relative URL path, NOT
// derived from the importer's URL. A consumer's specifier can route through any
// directory (eg. legacy-shims/, for consumers updated to import the .d.ts there)
// without affecting where the target webcomponent actually resolves.
const ETEMPLATE_URL_DIR = '/api/js/etemplate';

export function legacyWidgetShimDevServerPlugin()
{
    return {
        name: 'legacy-widget-shim-dev-server',
        resolveImport({source})
        {
            const base = source.split('/').pop().replace(/\.tsx?$/, '');
            if(!SHIM_MANIFEST[base]) return;

            return `${VIRTUAL_ROOT}${base}.js`;
        },
        serve(context)
        {
            if(!context.path.startsWith(VIRTUAL_ROOT)) return;

            const base = context.path.slice(VIRTUAL_ROOT.length).replace(/\.js$/, '');
            const manifest = SHIM_MANIFEST[base];
            if(!manifest) return;

            const importsByModule = new Map();
            for(const entry of manifest)
            {
                if(!importsByModule.has(entry.targetModule)) importsByModule.set(entry.targetModule, new Set());
                importsByModule.get(entry.targetModule).add(entry.targetExport);
            }

            const lines = [];
            for(const [mod, exportsSet] of importsByModule)
            {
                // .ts extension so the esbuild dev-server plugin picks it up and compiles it
                lines.push(`import {${[...exportsSet].join(', ')}} from ${JSON.stringify(path.posix.resolve(ETEMPLATE_URL_DIR, mod) + '.ts')};`);
            }
            for(const entry of manifest)
            {
                lines.push(`export class ${entry.legacyName} extends ${entry.targetExport} {}`);
            }

            return {body: lines.join('\n') + '\n', type: 'js'};
        }
    };
}
