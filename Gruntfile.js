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
 * Please use only double quotes, as we parse this file as json, to update it!
 *
 * To install grunt to build minified javascript files you need to run:
 *		sudo npm install -g grunt-cli
 *		npm install grunt --save-dev
 *		npm install grunt-contrib-uglify
 *
 * Building happens by running in your EGroupware directory:
 *		grunt
 * or
 *		grunt uglify:<target>	# targets: api, et2, mail, calendar, ...
 *
 * app.js files can be added under apps target, api and et2 bundels are already there.
 * To update files in Gruntfile after adding new js files you need to run:
 *		updateGruntfile.php
 *
 * @param {object} grunt
 */
module.exports = function (grunt) {
	grunt.initConfig({
		uglify: {
			options: {
				banner: "\/*! build <%= grunt.template.today() %> *\/\n",
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
						"phpgwapi\/js\/jsapi\/egw.js",
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
						"phpgwapi\/js\/ckeditor\/ckeditor.js",
						"phpgwapi\/js\/ckeditor\/config.js"
					]
				}
			},
			et2: {
				files: {
					"etemplate\/js\/etemplate2.min.js": [
						"etemplate\/js\/et2_core_xml.js",
						"etemplate\/js\/et2_core_common.js",
						"etemplate\/js\/et2_core_inheritance.js",
						"etemplate\/js\/et2_core_interfaces.js",
						"etemplate\/js\/et2_core_phpExpressionCompiler.js",
						"etemplate\/js\/et2_core_arrayMgr.js",
						"etemplate\/js\/et2_core_widget.js",
						"etemplate\/js\/et2_core_DOMWidget.js",
						"etemplate\/js\/et2_widget_template.js",
						"etemplate\/js\/et2_widget_grid.js",
						"etemplate\/js\/et2_core_baseWidget.js",
						"etemplate\/js\/et2_widget_box.js",
						"etemplate\/js\/et2_widget_hbox.js",
						"etemplate\/js\/et2_widget_groupbox.js",
						"phpgwapi\/js\/jquery\/splitter.js",
						"etemplate\/js\/et2_widget_split.js",
						"etemplate\/js\/et2_widget_button.js",
						"etemplate\/js\/et2_core_valueWidget.js",
						"etemplate\/js\/et2_core_inputWidget.js",
						"phpgwapi\/js\/jquery\/jpicker\/jpicker-1.1.6.js",
						"etemplate\/js\/et2_widget_color.js",
						"phpgwapi\/js\/jquery\/blueimp\/js\/blueimp-gallery.min.js",
						"etemplate\/js\/expose.js",
						"etemplate\/js\/et2_widget_description.js",
						"etemplate\/js\/et2_widget_entry.js",
						"etemplate\/js\/et2_widget_textbox.js",
						"etemplate\/js\/et2_widget_number.js",
						"phpgwapi\/js\/jquery\/jquery.base64.js",
						"etemplate\/js\/et2_widget_url.js",
						"etemplate\/js\/et2_widget_selectbox.js",
						"etemplate\/js\/et2_widget_checkbox.js",
						"etemplate\/js\/et2_widget_radiobox.js",
						"etemplate\/js\/lib\/date.js",
						"etemplate\/js\/et2_widget_date.js",
						"etemplate\/js\/et2_widget_dialog.js",
						"etemplate\/js\/lib\/jsdifflib\/difflib.js",
						"etemplate\/js\/lib\/jsdifflib\/diffview.js",
						"etemplate\/js\/et2_widget_diff.js",
						"etemplate\/js\/et2_widget_dropdown_button.js",
						"etemplate\/js\/et2_widget_styles.js",
						"etemplate\/js\/et2_widget_link.js",
						"etemplate\/js\/et2_widget_selectAccount.js",
						"etemplate\/js\/et2_extension_customfields.js",
						"etemplate\/js\/et2_dataview_interfaces.js",
						"etemplate\/js\/et2_dataview_view_container.js",
						"etemplate\/js\/et2_dataview_view_row.js",
						"phpgwapi\/js\/jquery\/TouchSwipe\/jquery.touchSwipe.js",
						"etemplate\/js\/et2_dataview_view_aoi.js",
						"etemplate\/js\/et2_dataview_controller_selection.js",
						"etemplate\/js\/et2_dataview_controller.js",
						"etemplate\/js\/et2_dataview_view_tile.js",
						"etemplate\/js\/et2_extension_nextmatch_actions.js",
						"etemplate\/js\/et2_extension_nextmatch_controller.js",
						"etemplate\/js\/et2_extension_nextmatch_rowProvider.js",
						"etemplate\/js\/et2_extension_nextmatch_dynheight.js",
						"etemplate\/js\/et2_dataview_model_columns.js",
						"etemplate\/js\/et2_dataview_view_rowProvider.js",
						"etemplate\/js\/et2_dataview_view_spacer.js",
						"etemplate\/js\/et2_dataview_view_grid.js",
						"etemplate\/js\/et2_dataview_view_resizeable.js",
						"etemplate\/js\/et2_dataview.js",
						"etemplate\/js\/et2_extension_nextmatch.js",
						"etemplate\/js\/et2_widget_favorites.js",
						"phpgwapi\/js\/dhtmlxGantt\/codebase\/dhtmlxgantt.js",
						"etemplate\/js\/et2_widget_gantt.js",
						"etemplate\/js\/et2_widget_html.js",
						"phpgwapi\/js\/ckeditor\/adapters\/jquery.js",
						"etemplate\/js\/et2_widget_htmlarea.js",
						"etemplate\/js\/et2_widget_tabs.js",
						"phpgwapi\/js\/jquery\/magicsuggest\/magicsuggest.js",
						"etemplate\/js\/et2_widget_taglist.js",
						"etemplate\/js\/et2_widget_toolbar.js",
						"etemplate\/js\/et2_widget_tree.js",
						"etemplate\/js\/et2_widget_historylog.js",
						"etemplate\/js\/et2_widget_hrule.js",
						"etemplate\/js\/et2_widget_image.js",
						"etemplate\/js\/et2_widget_iframe.js",
						"phpgwapi\/js\/Resumable\/resumable.js",
						"etemplate\/js\/et2_widget_file.js",
						"etemplate\/js\/et2_widget_progress.js",
						"etemplate\/js\/et2_widget_portlet.js",
						"etemplate\/js\/et2_widget_ajaxSelect.js",
						"etemplate\/js\/et2_widget_vfs.js",
						"etemplate\/js\/et2_widget_video.js",
						"phpgwapi\/js\/jquery\/barcode\/jquery-barcode.min.js",
						"etemplate\/js\/et2_widget_barcode.js",
						"etemplate\/js\/et2_extension_itempicker_actions.js",
						"etemplate\/js\/et2_widget_itempicker.js",
						"etemplate\/js\/et2_widget_script.js",
						"etemplate\/js\/et2_core_legacyJSFunctions.js",
						"etemplate\/js\/etemplate2.js"
					]
				}
			},
			mail: {
				files: {
					"mail\/js\/app.min.js": "mail\/js\/app.js"
				}
			},
			calendar: {
				files: {
					"calendar\/js\/app.min.js": "calendar\/js\/app.js"
				}
			}
		}
	});
	// Load the plugin that provides the "uglify" task.
	grunt.loadNpmTasks('grunt-contrib-uglify');

	// Default task(s).
	grunt.registerTask('default', ['uglify']);
};
