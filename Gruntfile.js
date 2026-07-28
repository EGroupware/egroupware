/**
 * EGroupware Gruntfile.js
 *
 * @link https://www.egroupware.org
 * @license https://opensource.org/licenses/gpl-license.php GPL - GNU General Public License
 * @author Ralf Becker <rb@egroupware.org>
 * @copyright (c) 2016-25 by Ralf Becker <rb@egroupware.org>
 */

/**
 * This is no longer run via the grunt CLI/npm package (dropped, as both grunt and
 * grunt-contrib-cssmin vendor outdated, vulnerable glob/minimatch/js-yaml versions
 * with no upstream fix - see GHSA-mh99-v99m-4gvg and related advisories).
 *
 * This file is kept for two reasons:
 * - build-css.mjs (run via "npm run css") reads the cssmin config below through a
 *   minimal grunt.initConfig() shim, so the file lists stay a single source of truth
 * - Api\Framework\Bundle::getImportMap() (api/src/Framework/Bundle.php) parses this
 *   file's grunt.initConfig({...}) call directly by regex for (unrelated, legacy)
 *   JS bundle info
 *
 * Please use only double quotes, as we parse this file as json to update it!
 *
 * @param {object} grunt
 */
module.exports = function (grunt) {
	grunt.initConfig({
		cssmin: {
			options: {
				shorthandCompacting: false,
				sourceMap: true,
				rebase: true
			},
			kdots: {
				files: {
					"kdots/css/themes/glassy.min.css": [
						"node_modules/flatpickr/dist/themes/light.css",
						"node_modules/diff2html/bundles/css/diff2html.min.css",
						"vendor/bower-asset/cropper/dist/cropper.min.css",
						"api/templates/default/css/flags.css",
						"api/templates/default/css/htmlarea.css",
						"api/templates/default/etemplate2.css",
						"kdots/css/themes/glassy.css",
						"api/templates/default/print.css"
					],
					"kdots/css/themes/classic.min.css": [
						"node_modules/flatpickr/dist/themes/light.css",
						"node_modules/diff2html/bundles/css/diff2html.min.css",
						"vendor/bower-asset/cropper/dist/cropper.min.css",
						"api/templates/default/css/flags.css",
						"api/templates/default/css/htmlarea.css",
						"api/templates/default/etemplate2.css",
						"kdots/css/themes/classic.css",
						"api/templates/default/print.css"
					]
				}
			}
		},
		hub: {
			all: {
				src: [
					"*/Gruntfile.js"
				]
			}
		}
	});
	// Load plugin for css minification
	grunt.loadNpmTasks("grunt-contrib-cssmin");

	// Load the plugin that runs tasks only on modified files
	//grunt.loadNpmTasks("grunt-newer");

	// uncomment to run Gruntfile.js in apps / sub-directories
	//grunt.loadNpmTasks('grunt-hub');

	// Default task(s).
	grunt.registerTask("default", ["cssmin"]);//, 'hub']);
};