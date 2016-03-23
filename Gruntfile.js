/**
 * EGroupware Gruntfile.js
 *
 * @link http://www.egroupware.org
 * @license http://opensource.org/licenses/gpl-license.php GPL - GNU General Public License
 * @author Ralf Becker <rb@stylite.de>
 * @copyright (c) 2016 by Ralf Becker <rb@stylite.de>
 * @version $Id$
 */

/**
 * To install grunt to build minified javascript files you need to run:
 *
 *		sudo npm install -g grunt-cli
 *		npm install grunt --save-dev
 *		npm install grunt-contrib-uglify --save-dev
 *		npm install grunt-newer --save-dev
 *
 * Building happens by running in your EGroupware directory:
 *
 *		grunt	# runs uglify for all targets with changed files
 * or
 *		grunt [newer:]uglify:<target>	# targets: api, et2, pixelegg, mobile, mail, calendar, ...
 *
 * app.js files can be added like mail target or, if you want automatic dependencies,
 * you need to add them in egw_framework::$bundle2minurl and egw_framework::get_bundles().
 *
 * To update files in Gruntfile after adding new js files you need to run:
 *
 *		updateGruntfile.php
 *
 * Please use only double quotes, as we parse this file as json to update it!
 *
 * @param {object} grunt
 */
module.exports = function (grunt) {
	grunt.initConfig({
		uglify: {
			options: {
				banner: "\/*!\n * EGroupware (http:\/\/www.egroupware.org\/) minified Javascript\n *\n * full sources are available under https:\/\/svn.stylite.de\/viewvc\/egroupware\/\n *\n * build <%= grunt.template.today() %>\n *\/\n",
				mangle: false,
				sourceMap: true,
				screwIE8: true
			},
			api: {
				files: {
					"phpgwapi\/js\/jsapi.min.js": [
						"phpgwapi\/js\/jquery\/jquery.js",
						"phpgwapi\/js\/jquery\/jquery-ui.js",
						"phpgwapi\/js\/jsapi\/jsapi.js",
						"phpgwapi\/js\/egw_json.js",
						"phpgwapi\/js\/jsapi\/egw_core.js",
						"phpgwapi\/js\/jsapi\/egw_debug.js",
						"phpgwapi\/js\/jsapi\/egw_preferences.js",
						"phpgwapi\/js\/jsapi\/egw_utils.js",
						"phpgwapi\/js\/jsapi\/egw_ready.js",
						"phpgwapi\/js\/jsapi\/egw_files.js",
						"phpgwapi\/js\/jsapi\/egw_lang.js",
						"phpgwapi\/js\/jsapi\/egw_links.js",
						"phpgwapi\/js\/jsapi\/egw_open.js",
						"phpgwapi\/js\/jsapi\/egw_user.js",
						"phpgwapi\/js\/jsapi\/egw_config.js",
						"phpgwapi\/js\/jsapi\/egw_images.js",
						"phpgwapi\/js\/jsapi\/egw_jsonq.js",
						"phpgwapi\/js\/jsapi\/egw_json.js",
						"phpgwapi\/js\/jsapi\/egw_store.js",
						"phpgwapi\/js\/jsapi\/egw_tooltip.js",
						"phpgwapi\/js\/jsapi\/egw_css.js",
						"phpgwapi\/js\/jquery\/jquery-ui-timepicker-addon.js",
						"phpgwapi\/js\/jsapi\/egw_calendar.js",
						"phpgwapi\/js\/jsapi\/egw_data.js",
						"phpgwapi\/js\/jsapi\/egw_tail.js",
						"phpgwapi\/js\/jsapi\/egw_inheritance.js",
						"phpgwapi\/js\/jsapi\/egw_message.js",
						"phpgwapi\/js\/es6-promise.min.js",
						"phpgwapi\/js\/jsapi\/app_base.js",
						"phpgwapi\/js\/dhtmlxtree\/codebase\/dhtmlxcommon.js",
						"phpgwapi\/js\/dhtmlxtree\/sources\/dhtmlxtree.js",
						"phpgwapi\/js\/dhtmlxtree\/sources\/ext\/dhtmlxtree_json.js",
						"phpgwapi\/js\/egw_action\/egw_action_common.js",
						"phpgwapi\/js\/egw_action\/egw_action.js",
						"phpgwapi\/js\/egw_action\/egw_keymanager.js",
						"phpgwapi\/js\/egw_action\/egw_menu.js",
						"phpgwapi\/js\/jquery\/jquery-tap-and-hold\/jquery.tapandhold.js",
						"phpgwapi\/js\/egw_action\/egw_action_popup.js",
						"phpgwapi\/js\/egw_action\/egw_action_dragdrop.js",
						"phpgwapi\/js\/egw_action\/egw_dragdrop_dhtmlx_tree.js",
						"phpgwapi\/js\/dhtmlxMenu\/sources\/dhtmlxmenu.js",
						"phpgwapi\/js\/dhtmlxMenu\/sources\/ext\/dhtmlxmenu_ext.js",
						"phpgwapi\/js\/egw_action\/egw_menu_dhtmlx.js",
						"phpgwapi\/js\/jquery\/chosen\/chosen.jquery.js",
						"phpgwapi\/js\/ckeditor\/config.js"
					]
				}
			},
			et2: {
				files: {
					"api\/js\/etemplate\/etemplate2.min.js": [
						"api\/js\/etemplate\/et2_core_xml.js",
						"api\/js\/etemplate\/et2_core_common.js",
						"api\/js\/etemplate\/et2_core_inheritance.js",
						"api\/js\/etemplate\/et2_core_interfaces.js",
						"api\/js\/etemplate\/et2_core_phpExpressionCompiler.js",
						"api\/js\/etemplate\/et2_core_arrayMgr.js",
						"api\/js\/etemplate\/et2_core_widget.js",
						"api\/js\/etemplate\/et2_core_DOMWidget.js",
						"api\/js\/etemplate\/et2_widget_template.js",
						"api\/js\/etemplate\/et2_widget_grid.js",
						"api\/js\/etemplate\/et2_core_baseWidget.js",
						"api\/js\/etemplate\/et2_widget_box.js",
						"api\/js\/etemplate\/et2_widget_hbox.js",
						"api\/js\/etemplate\/et2_widget_groupbox.js",
						"phpgwapi\/js\/jquery\/splitter.js",
						"api\/js\/etemplate\/et2_widget_split.js",
						"api\/js\/etemplate\/et2_widget_button.js",
						"api\/js\/etemplate\/et2_core_valueWidget.js",
						"api\/js\/etemplate\/et2_core_inputWidget.js",
						"phpgwapi\/js\/jquery\/jpicker\/jpicker-1.1.6.js",
						"api\/js\/etemplate\/et2_widget_color.js",
						"phpgwapi\/js\/jquery\/blueimp\/js\/blueimp-gallery.min.js",
						"api\/js\/etemplate\/expose.js",
						"api\/js\/etemplate\/et2_widget_description.js",
						"api\/js\/etemplate\/et2_widget_entry.js",
						"api\/js\/etemplate\/et2_widget_textbox.js",
						"api\/js\/etemplate\/et2_widget_number.js",
						"phpgwapi\/js\/jquery\/jquery.base64.js",
						"api\/js\/etemplate\/et2_widget_url.js",
						"api\/js\/etemplate\/et2_widget_selectbox.js",
						"api\/js\/etemplate\/et2_widget_checkbox.js",
						"api\/js\/etemplate\/et2_widget_radiobox.js",
						"api\/js\/etemplate\/lib\/date.js",
						"api\/js\/etemplate\/et2_widget_date.js",
						"api\/js\/etemplate\/et2_widget_dialog.js",
						"api\/js\/etemplate\/lib\/jsdifflib\/difflib.js",
						"api\/js\/etemplate\/lib\/jsdifflib\/diffview.js",
						"api\/js\/etemplate\/et2_widget_diff.js",
						"api\/js\/etemplate\/et2_widget_dropdown_button.js",
						"api\/js\/etemplate\/et2_widget_styles.js",
						"api\/js\/etemplate\/et2_widget_link.js",
						"api\/js\/etemplate\/et2_widget_selectAccount.js",
						"api\/js\/etemplate\/et2_extension_customfields.js",
						"api\/js\/etemplate\/et2_dataview_interfaces.js",
						"api\/js\/etemplate\/et2_dataview_view_container.js",
						"api\/js\/etemplate\/et2_dataview_view_row.js",
						"phpgwapi\/js\/jquery\/TouchSwipe\/jquery.touchSwipe.js",
						"api\/js\/etemplate\/et2_dataview_view_aoi.js",
						"api\/js\/etemplate\/et2_dataview_controller_selection.js",
						"api\/js\/etemplate\/et2_dataview_controller.js",
						"api\/js\/etemplate\/et2_dataview_view_tile.js",
						"api\/js\/etemplate\/et2_extension_nextmatch_actions.js",
						"api\/js\/etemplate\/et2_extension_nextmatch_controller.js",
						"api\/js\/etemplate\/et2_extension_nextmatch_rowProvider.js",
						"api\/js\/etemplate\/et2_extension_nextmatch_dynheight.js",
						"api\/js\/etemplate\/et2_dataview_model_columns.js",
						"api\/js\/etemplate\/et2_dataview_view_rowProvider.js",
						"api\/js\/etemplate\/et2_dataview_view_spacer.js",
						"api\/js\/etemplate\/et2_dataview_view_grid.js",
						"api\/js\/etemplate\/et2_dataview_view_resizeable.js",
						"api\/js\/etemplate\/et2_dataview.js",
						"api\/js\/etemplate\/et2_extension_nextmatch.js",
						"api\/js\/etemplate\/et2_widget_favorites.js",
						"api\/js\/etemplate\/et2_widget_html.js",
						"phpgwapi\/js\/ckeditor\/adapters\/jquery.js",
						"api\/js\/etemplate\/et2_widget_htmlarea.js",
						"api\/js\/etemplate\/et2_widget_tabs.js",
						"phpgwapi\/js\/jquery\/magicsuggest\/magicsuggest.js",
						"api\/js\/etemplate\/et2_widget_taglist.js",
						"api\/js\/etemplate\/et2_widget_toolbar.js",
						"api\/js\/etemplate\/et2_widget_tree.js",
						"api\/js\/etemplate\/et2_widget_historylog.js",
						"api\/js\/etemplate\/et2_widget_hrule.js",
						"api\/js\/etemplate\/et2_widget_image.js",
						"api\/js\/etemplate\/et2_widget_iframe.js",
						"phpgwapi\/js\/Resumable\/resumable.js",
						"api\/js\/etemplate\/et2_widget_file.js",
						"api\/js\/etemplate\/et2_widget_progress.js",
						"api\/js\/etemplate\/et2_widget_portlet.js",
						"api\/js\/etemplate\/et2_widget_ajaxSelect.js",
						"api\/js\/etemplate\/et2_widget_vfs.js",
						"api\/js\/etemplate\/et2_widget_video.js",
						"phpgwapi\/js\/jquery\/barcode\/jquery-barcode.min.js",
						"api\/js\/etemplate\/et2_widget_barcode.js",
						"api\/js\/etemplate\/et2_extension_itempicker_actions.js",
						"api\/js\/etemplate\/et2_widget_itempicker.js",
						"api\/js\/etemplate\/et2_widget_script.js",
						"api\/js\/etemplate\/et2_core_legacyJSFunctions.js",
						"api\/js\/etemplate\/etemplate2.js"
					]
				}
			},
			mail: {
				files: {
					"mail\/js\/app.min.js": [
						"mail\/js\/app.js"
					]
				}
			},
			calendar: {
				files: {
					"calendar\/js\/app.min.js": [
						"calendar\/js\/et2_widget_owner.js",
						"calendar\/js\/et2_widget_view.js",
						"calendar\/js\/et2_widget_timegrid.js",
						"calendar\/js\/et2_widget_event.js",
						"calendar\/js\/et2_widget_daycol.js",
						"calendar\/js\/et2_widget_planner_row.js",
						"calendar\/js\/et2_widget_planner.js",
						"calendar\/js\/app.js"
					]
				}
			},
			jdots: {
				files: {
					"jdots\/js\/fw_jdots.min.js": [
						"phpgwapi\/js\/framework\/fw_base.js",
						"phpgwapi\/js\/framework\/fw_browser.js",
						"phpgwapi\/js\/jquery\/mousewheel\/mousewheel.js",
						"phpgwapi\/js\/framework\/fw_ui.js",
						"phpgwapi\/js\/framework\/fw_classes.js",
						"phpgwapi\/js\/framework\/fw_desktop.js",
						"jdots\/js\/fw_jdots.js"
					]
				}
			},
			mobile: {
				files: {
					"jdots\/js\/fw_mobile.min.js": [
						"phpgwapi\/js\/jquery\/fastclick\/lib\/fastclick.js",
						"phpgwapi\/js\/framework\/fw_base.js",
						"phpgwapi\/js\/framework\/fw_browser.js",
						"phpgwapi\/js\/jquery\/mousewheel\/mousewheel.js",
						"phpgwapi\/js\/framework\/fw_ui.js",
						"phpgwapi\/js\/framework\/fw_classes.js",
						"jdots\/js\/fw_mobile.js"
					]
				}
			},
			pixelegg: {
				files: {
					"pixelegg\/js\/fw_pixelegg.min.js": [
						"phpgwapi\/js\/framework\/fw_base.js",
						"phpgwapi\/js\/framework\/fw_browser.js",
						"phpgwapi\/js\/jquery\/mousewheel\/mousewheel.js",
						"phpgwapi\/js\/framework\/fw_ui.js",
						"phpgwapi\/js\/framework\/fw_classes.js",
						"phpgwapi\/js\/framework\/fw_desktop.js",
						"pixelegg\/js\/slider.js",
						"pixelegg\/js\/fw_pixelegg.js"
					]
				}
			},
			projectmanager: {
				files: {
					"projectmanager\/js\/app.min.js": [
						"projectmanager\/js\/dhtmlxGantt\/codebase\/dhtmlxgantt.js",
						"projectmanager\/js\/et2_widget_gantt.js",
						"projectmanager\/js\/app.js"
					]
				}
			}
		}
	});
	// Load the plugin that provides the "uglify" task.
	grunt.loadNpmTasks('grunt-contrib-uglify');

	// Load the plugin that runs tasks only on modified files
	grunt.loadNpmTasks('grunt-newer');

	// Default task(s).
	grunt.registerTask('default', ['newer:uglify']);
};
