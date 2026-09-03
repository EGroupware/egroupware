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

export function legacyWidgetShimDevServerPlugin()
{
    return {
        name: 'legacy-widget-shim-dev-server',
        resolveImport({source, context})
        {
            const base = source.split('/').pop().replace(/\.tsx?$/, '');
            if(!SHIM_MANIFEST[base]) return;

            const importerDir = path.posix.dirname(context.path);
            const dir = source.startsWith('.')
                ? path.posix.resolve(importerDir, path.posix.dirname(source))
                : path.posix.dirname(source.startsWith('/') ? source : '/' + source);

            return `${VIRTUAL_ROOT}${base}.js?dir=${encodeURIComponent(dir)}`;
        },
        serve(context)
        {
            if(!context.path.startsWith(VIRTUAL_ROOT)) return;

            const base = context.path.slice(VIRTUAL_ROOT.length).replace(/\.js$/, '');
            const dir = context.query.dir;
            const manifest = SHIM_MANIFEST[base];
            if(!manifest || !dir) return;

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
                lines.push(`import {${[...exportsSet].join(', ')}} from ${JSON.stringify(path.posix.resolve(dir, mod) + '.ts')};`);
            }
            for(const entry of manifest)
            {
                lines.push(`export class ${entry.legacyName} extends ${entry.targetExport} {}`);
            }

            return {body: lines.join('\n') + '\n', type: 'js'};
        }
    };
}
