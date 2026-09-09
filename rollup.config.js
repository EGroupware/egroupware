/**
 * EGroupware - Rollup config file
 *
 * @link https://www.egroupware.org
 * @copyright (c) 2021 by Nathan Gray
 * @license http://opensource.org/licenses/gpl-license.php GPL - GNU General Public License
 *
 * @see http://rollupjs.org/guide/en
 * @type {import('rollup').RollupOptions}
 */

import path from 'path';
import babel from '@babel/core';
import { readFileSync, readdirSync, statSync, unlinkSync, writeFileSync, renameSync  } from "fs";
//import rimraf from 'rimraf';
// Default import: terser 4.x ships a minified CJS bundle with no exports map, so Node
// cannot detect its named exports and "import { minify }" fails to load this config.
import terser from 'terser';
import resolve from '@rollup/plugin-node-resolve';
import commonjs from '@rollup/plugin-commonjs';
import { legacyWidgetShimPlugin } from './api/js/etemplate/rollup-legacy-widget-shim.mjs';

// Best practice: use this
//rimraf.sync('./dist/');
//rimraf.sync('./chunks/');

// remove only chunks older than 2 days, to allow UI to still load them and not require a reload / F5
const rm_older = Date.now() - 48*3600000;
readdirSync('./chunks').forEach(name => {
    const stat = statSync('./chunks/'+name);
    if (stat.atimeMs < rm_older) unlinkSync('./chunks/'+name);
});

// Timestamp identifying this build, written to build-epoch.json below so a running session
// can cheaply poll for "is a newer build available" without re-fetching any JS bundle.
//
// Reassigned in buildStart below (not just set once here) because rollup's watch mode keeps
// this same config module instance alive across every incremental rebuild - a plain top-level
// const would freeze both this and entryManifest's filename to whatever they were when the
// watcher started, so a later rebuild would silently overwrite that same
// chunks/build-manifest-<epoch>.json with fresh hashes instead of writing a new one. That would
// rot the pin for any document already open: it always asks for its own epoch's manifest, but
// the file behind that epoch would have quietly become a different build.
let buildEpoch = Date.now();

// Populated in generateBundle below: logical entry path relative to EGW_SERVER_ROOT (eg.
// "/infolog/js/app.min.js") -> this build's hashed physical path (eg.
// "/chunks/infolog-js-app.min-<hash>.js"). Written to chunks/build-manifest-<epoch>.json so the
// server can resolve an entry against the exact build a document was pinned to, even after a
// later build moves that entry to a new hash. Reset in buildStart below, same reason as buildEpoch.
let entryManifest = {};

// Turn on minification
const do_minify = false;

function isBareSpecifier (id) {
    if (id.startsWith("./") || id.startsWith("../") || id.startsWith("/"))
        return false;
    try {
        new URL(id);
        return false;
    }
    catch {
        return true;
    }
}

const config = {
    treeshake: false,
    input: {
        // Output : Input
        // Note the .ts extension on the input - we build directly from the TypeScript when available
        // "pixelegg/js/fw_pixelegg.min": "pixelegg/js/fw_pixelegg.js",
        // "pixelegg/js/fw_mobile.min": "pixelegg/js/fw_mobile.js",
        "api/js/jsapi/egw.min": "api/js/jsapi/egw_modules.js",
        "api/js/etemplate/etemplate2": "api/js/etemplate/etemplate2.ts",

        // app.ts/js are added automatic by addAppsConfig() below
    },
    external: function(id,parentId,isResolved) {
        // core-js used require and needs to be run through RollupJS and NOT treated as external
        if (id.includes("/node_modules/core-js/"))
        {
            return false;
        }
        if(!isResolved)
        {
            return;
        }

        if(id.includes("/vendor/"))
        {
            return true;
        }
    },
    output: {
        // Hashed entries, addressable like chunks already are - entries land in chunks/ too, so
        // an old build stays servable as long as its files aren't swept by the atime GC above.
        // Flattens the input key's slashes into a collision-free name across all apps, eg.
        // "infolog/js/app.min" -> "chunks/infolog-js-app.min-<hash>.js"
        entryFileNames: (chunkInfo) => 'chunks/' + chunkInfo.name.replace(/\//g, '-') + '-[hash].js',
        chunkFileNames: 'chunks/[name]-[hash].js',
        // Best practice: use this:
        //dir: './dist',
        dir: '.',
        sourcemap: true
    },
    plugins: [
    // must run before the extensionless .ts/.js resolver below, so it can
    // synthesize the legacy et2_widget_*.ts shims that no longer exist on disk
    legacyWidgetShimPlugin(),
    {
        resolveId (id, parentId) {
            // Delegate bare specifiers to node_modules resolver
            if (isBareSpecifier(id))
            {
                return;
            }
            // Leave node_modules alone, whether we got here from one or resolved into one.
            // Another plugin can re-resolve an already-absolute dependency path through this
            // hook, and the extension rewriting below only makes sense for first-party source -
            // without this a dependency resolving to index.mjs becomes index.mjs.js.
            const nodeModules = path.sep + 'node_modules' + path.sep;
            if (!parentId || parentId.indexOf(nodeModules) !== -1 || id.indexOf(nodeModules) !== -1)
            {
                return;
            }
            if (id.endsWith(".js"))
            {
                const tsPath = path.resolve(path.dirname(parentId), id.slice(0,-3) + '.ts');
                try {
                    readFileSync(tsPath);
                    console.warn(id + " is a TS file loaded with wrong extension.  Remove the extension on the import in " + parentId);
                }
                catch (e) {}
            }
            else if (!id.endsWith('.ts')) {

                const tsPath =path.resolve(path.dirname(parentId), id + '.ts');
                const jsPath =path.resolve(path.dirname(parentId), id + '.js');
                try {
                    readFileSync(tsPath);
                    return tsPath;
                }
                catch (e) {}
                try {
                    readFileSync(jsPath);
                    return jsPath;
                }
                catch (e) {}
                // Neither exists, so this is not an extensionless module import - it is a file
                // that already names its own extension (eg. a .css imported for its text).
                // Leave it to the other plugins rather than inventing a ".js" path
                // that isn't there.
                return;
            }
        }
    },
    {
        // Let a component import a stylesheet as text, for lit's unsafeCSS().
        // Keeps a .less/.css file the single source of truth for styles that are needed
        // both in a shadow root and in the document.
        load (id) {
            if (id.endsWith('.css') && id.indexOf(path.sep + 'node_modules' + path.sep) === -1)
            {
                return 'export default ' + JSON.stringify(readFileSync(id, 'utf-8')) + ';';
            }
        }
    },
    // resolve (external) node modules from node_modules directory
    resolve({
        browser: true
    }),
    // core-js uses require, which needs to be transformed to es-modules
    commonjs(),
    {
        transform (code, id) {
            if (id.endsWith('.ts'))
                return new Promise((resolve, reject) => {
                    return babel.transform(code, {
                        filename: id,
                        sourceMaps: true,
                        ast: false,
                        compact: false,
                        sourceType: 'module',
                        parserOpts: {
                            // plugins: stage3Syntax,
                            errorRecovery: true
                        },
						plugins: [
							['@babel/plugin-proposal-decorators', {legacy: false, decoratorsBeforeExport: false}],
							['@babel/plugin-transform-class-properties', {loose: false}]
						],
                        presets: [
                            ['@babel/preset-typescript', {
                                //onlyRemoveTypeImports: true   // seems not necessary and generates a lot of warnings about not exported symbols
                            }],
                            ['@babel/preset-env', {
                                corejs: {
                                    version: "3"
                                },
                                useBuiltIns: "usage",
                                modules: false,
                                targets : {
                                    esmodules: true,
                                    safari: "14"
                                }
                            }],
                        ]
                    }, function (err, result) {
                        if (err)
                            return reject(err);
                        resolve(result);
                    });
                });
        }
    },
    {
        transform (code,id) {
            if(!do_minify || id.includes(".min"))
            {
                return;
            }
            return terser.minify(code, {
                mangle: false,
                sourceMap: true,
                output: {
                    preamble: `/*!
 * EGroupware (https://www.egroupware.org/) minified Javascript
 *
 * full sources are available under https://github.com/EGroupware/egroupware/
 *
 * build ${Date.now()}
 */
`
                }
            });
        }
    },
    {
        // Fresh identity for this build, before anything else in the pipeline runs - see the
        // comments on buildEpoch/entryManifest above for why this can't just be top-level state.
        buildStart () {
            buildEpoch = Date.now();
            entryManifest = {};
        }
    },
    {
        // Record this build's logical-entry -> hashed-physical-path mapping (see entryManifest
        // above). generateBundle sees final hashed fileNames, before they're written to disk.
        generateBundle (options, bundle) {
            for (const file of Object.values(bundle)) {
                if (file.type === 'chunk' && file.isEntry) {
                    entryManifest['/' + file.name + '.js'] = '/' + file.fileName;
                }
            }
        }
    },
    {
        // Write out this build's epoch, so a running session can cheaply poll for
        // "is a newer build available" (see api/js/jsapi/egw.js) without touching any JS bundle.
        // The manifest is unversioned, same as this - the server only ever resolves an entry
        // against the current build (a fresh page render) or not at all (ajax_exec, where the
        // client resolves against its own already-resident copy instead), so there is nothing to
        // keep a history of and nothing for the chunks/ GC to sweep here.
        //
        // Guards against a stale rebuild overwriting a fresher one (eg. two concurrent
        // "rollup -cw" processes on the same tree): skip the write (both files, together)
        // if this build's epoch is older than what's already on disk, and write each file
        // via a same-directory temp file + rename so a concurrent reader never sees a
        // half-written one.
        writeBundle () {
            const epochPath = './api/js/build-epoch.json';
            let existingEpoch = 0;
            try {
                existingEpoch = JSON.parse(readFileSync(epochPath, 'utf-8')).epoch || 0;
            }
            catch (e) {}
            if (buildEpoch < existingEpoch)
            {
                return;
            }
            const atomicWrite = (path, content) => {
                const tmpPath = path + '.' + process.pid + '.tmp';
                writeFileSync(tmpPath, content);
                renameSync(tmpPath, path);
            };
            atomicWrite(epochPath, JSON.stringify({epoch: buildEpoch}));
            atomicWrite('./api/js/build-manifest.json', JSON.stringify(entryManifest));
        }
    }],

    // Custom warning handler to give more information about circular dependencies
    onwarn: function(warning,warn) {
        console.warn(warning.toString());
    }
};

/**
 * Add existing app.ts/js endpoints to config.input and return it
 *
 * @return Promise<object>
 */
export default function addAppsConfig()
{
    const conf = config;
    const files = readdirSync('.', { withFileTypes: true});
    for (const file of files)
    {
        if (file.isDirectory())
        {
            try {
                statSync(file.name + '/js/app.ts');
                config.input[file.name + '/js/app.min'] = file.name + '/js/app.ts';
            }
            catch (e) {
                try {
                    statSync(file.name + '/js/app.js');
                    config.input[file.name + '/js/app.min'] = file.name + '/js/app.js';
                }
                catch (e) {
                }
            }
        }
    }
    return conf;
}