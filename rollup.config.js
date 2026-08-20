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
import { readFileSync, readdirSync, statSync, unlinkSync, writeFileSync  } from "fs";
//import rimraf from 'rimraf';
// Default import: terser 4.x ships a minified CJS bundle with no exports map, so Node
// cannot detect its named exports and "import { minify }" fails to load this config.
import terser from 'terser';
import resolve from '@rollup/plugin-node-resolve';
import commonjs from '@rollup/plugin-commonjs';

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
const buildEpoch = Date.now();

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
        // TODO: Hashed entries, when server supports
        //entryFileNames: '[name]-[hash].js',
        entryFileNames: '[name].js',
        chunkFileNames: 'chunks/[name]-[hash].js',
        // Best practice: use this:
        //dir: './dist',
        dir: '.',
        sourcemap: true
    },
    plugins: [{
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
        // Write out this build's epoch, so a running session can cheaply poll for
        // "is a newer build available" (see api/js/jsapi/egw.js) without touching any JS bundle.
        writeBundle () {
            writeFileSync('./api/js/build-epoch.json', JSON.stringify({epoch: buildEpoch}));
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