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


export default [{
    // Main bundle
    input: "./api/js/jsapi/egw.js",
    output: {
        file: "./api/js/jsapi.min.js",
        format: "iife"
    }
}];