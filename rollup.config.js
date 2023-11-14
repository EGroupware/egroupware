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
import { readFileSync, readdirSync, statSync, unlinkSync  } from "fs";
//import rimraf from 'rimraf';
import { minify } from 'terser';
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
        "pixelegg/js/fw_pixelegg.min": "pixelegg/js/fw_pixelegg.js",
        "pixelegg/js/fw_mobile.min": "pixelegg/js/fw_mobile.js",
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
            if (!parentId || parentId.indexOf(path.sep + 'node_modules' + path.sep) !== -1)
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
                }
                catch (e) {
                    return jsPath;
                }
                return tsPath;
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
							['@babel/plugin-proposal-decorators', {legacy: true}],
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
            return minify(code, {
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