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
import { readFileSync } from "fs";
import rimraf from 'rimraf';
import { minify } from 'terser';

// Best practice: use this
//rimraf.sync('./dist/');
rimraf.sync('./chunks/');

// Turn on minification
const do_minify = false;

export default {
    treeshake: false,
    input: {
        // Output : Input
        // Note the .ts extension on the input - we build directly from the TypeScript when available
        "pixelegg/js/fw_pixelegg.min": "pixelegg/js/fw_pixelegg.js",
        "pixelegg/js/fw_mobile.min": "pixelegg/js/fw_mobile.js",
        "api/js/etemplate/etemplate2.min":"api/js/etemplate/etemplate2.ts",
        "api/js/egw_action/egw_dragdrop_dhtmlx_tree.min":"api/js/egw_action/egw_dragdrop_dhtmlx_tree.js",
        "api/js/jsapi/egw.min": "api/js/jsapi/egw_modules.js",
        "api/js/jsapi.min": 'api/js/jsapi/jsapi.js',

        // Should be just built-in apps, but until rollup supports multi-level we need them all
        "addressbook/js/app": "addressbook/js/app.ts",
        "admin/js/app": "admin/js/app.ts",
        "bookmarks/js/app": "bookmarks/js/app.ts",
        "calendar/js/app" : "calendar/js/app.ts",
        "collabora/js/app": "collabora/js/app.ts",
        "filemanager/js/app": "filemanager/js/app.ts",
        //"home/js/app": "home/js/app.js",
        //"importexport/js/app": "importexport/js/app.ts",
        "infolog/js/app": "infolog/js/app.ts",
        "mail/js/app.min": "mail/js/app.js",
        //"news_admin/js/app.min": "news_admin/js/app.js",
        "notifications/js/notificationajaxpopup.min": "notifications/js/notificationajaxpopup.js",
        "preferences/js/app": "preferences/js/app.ts",
        "projectmanager/js/app.min": "projectmanager/js/app.ts",
        "resources/js/app": "resources/js/app.ts",
        "rocketchat/js/app.min": "rocketchat/js/app.js",
        //"smallpart/js/app.min": "smallpart/js/app.ts",
        "status/js/app": "status/js/app.ts",
        "timesheet/js/app": "timesheet/js/app.ts",
        "tracker/js/app": "tracker/js/app.ts",
        // EPL
        "esyncpro/js/app": "esyncpro/js/app.ts",
        "kanban/js/app": "kanban/js/app.ts",
        "policy/js/app": "policy/js/app.ts",
        "stylite/js/app": "stylite/js/app.ts",
        "webauthn/js/app": "webauthn/js/app.ts",
    },
    external: function(id,parentId,isResolved) {
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
            if(id.endsWith(".js") && parentId)
            {
                const tsPath =path.resolve(path.dirname(parentId), id.slice(0,-3) + '.ts');
                try {
                    readFileSync(tsPath);
                    console.warn(id + " is a TS file loaded with wrong extension.  Remove the extension on the import in " + parentId);
                }
                catch (e) {}
            }
            else if (!id.endsWith('.js') && !id.endsWith('.ts')) {

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
    }, {
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
                        presets: ['@babel/preset-typescript']
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
                output: {
                    preamble: `/*!
 * EGroupware (http://www.egroupware.org/) minified Javascript
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
        console.warn(warning);
    }


};