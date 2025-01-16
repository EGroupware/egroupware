/**
 * EGroupware Gruntfile.js
 *
 * @link http://www.egroupware.org
 * @license http://opensource.org/licenses/gpl-license.php GPL - GNU General Public License
 * @author Ralf Becker <rb@egroupware.org>
 * @copyright (c) 2016-21 by Ralf Becker <rb@egroupware.org>
 */

/**
 * To install grunt to build minified javascript files you need to run:
 *
 *		sudo npm install -g grunt-cli
 *		npm install # installs everything from package.json into node_modules dir
 *
 * To generate the now existing package.json:
 *		npm init
 *		npm install grunt --save-dev
 *		npm install grunt-newer --save-dev
 *		npm install grunt-contrib-cssmin --save-dev
 *
 * Building happens by running in your EGroupware directory:
 *
 *		grunt	# runs cssmin for all targets with changed files
 * or
 *		grunt [newer:]cssmin:<target>	# targets: pixelegg, jdots
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
			pixelegg: {
				files: {
					"pixelegg/css/fancy.min.css": [
						"node_modules/flatpickr/dist/themes/light.css",
						"node_modules/diff2html/bundles/css/diff2html.min.css",
						"vendor/bower-asset/cropper/dist/cropper.min.css",
						"api/templates/default/css/flags.css",
						"api/templates/default/css/htmlarea.css",
						"api/templates/default/etemplate2.css",
						"pixelegg/css/fancy.css",
						"api/templates/default/print.css",
						"pixelegg/print.css"
					],
					"pixelegg/css/pixelegg.min.css": [
						"node_modules/flatpickr/dist/themes/light.css",
						"node_modules/diff2html/bundles/css/diff2html.min.css",
						"vendor/bower-asset/cropper/dist/cropper.min.css",
						"api/templates/default/css/flags.css",
						"api/templates/default/css/htmlarea.css",
						"api/templates/default/etemplate2.css",
						"pixelegg/css/pixelegg.css",
						"api/templates/default/print.css",
						"pixelegg/print.css"
					],
					"pixelegg/css/mobile.min.css": [
						"node_modules/flatpickr/dist/themes/light.css",
						"node_modules/diff2html/bundles/css/diff2html.min.css",
						"vendor/bower-asset/cropper/dist/cropper.min.css",
						"api/templates/default/css/flags.css",
						"api/templates/default/css/htmlarea.css",
						"api/templates/default/etemplate2.css",
						"pixelegg/css/mobile.css",
						"api/templates/default/print.css",
						"pixelegg/print.css"
					],
					"pixelegg/mobile/fw_mobile.min.css": [
						"node_modules/flatpickr/dist/themes/light.css",
						"node_modules/diff2html/bundles/css/diff2html.min.css",
						"vendor/bower-asset/cropper/dist/cropper.min.css",
						"api/templates/default/css/flags.css",
						"api/templates/default/css/htmlarea.css",
						"api/templates/default/etemplate2.css",
						"pixelegg/mobile/fw_mobile.css",
						"api/templates/default/print.css",
						"pixelegg/print.css"
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