/**
 * EGroupware Gruntfile.js
 *
 * @link http://www.egroupware.org
 * @license http://opensource.org/licenses/gpl-license.php GPL - GNU General Public License
 * @author Ralf Becker <rb@egroupware.org>
 * @copyright (c) 2016 by Ralf Becker <rb@egroupware.org>
 * @version $Id$
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
 *		npm install grunt-contrib-uglify --save-dev
 *		npm install grunt-newer --save-dev
 *		npm install grunt-contrib-cssmin --save-dev
 *		npm install grunt-hub --save-dev
 *
 * Building happens by running in your EGroupware directory:
 *
 *		grunt	# runs uglify and cssmin for all targets with changed files
 * or
 *		grunt [newer:]uglify:<target>	# targets: api, et2, pixelegg, mobile, mail, calendar, ...
 * or
 *		grunt [newer:]cssmin:<target>	# targets: pixelegg, jdots
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
				banner: "/*!\n * EGroupware (http://www.egroupware.org/) minified Javascript\n *\n * full sources are available under https://github.com/EGroupware/egroupware/\n *\n * build <%= grunt.template.today() %>\n */\n",
				mangle: false,
				sourceMap: true,
				screwIE8: true
			},
			api: {
				files: {
					"api/js/jsapi.min.js": [
						"vendor/bower-asset/jquery/dist/jquery.js",
						"api/js/jquery/jquery.noconflict.js",
						"vendor/bower-asset/jquery-ui/jquery-ui.js",
						"api/js/jsapi/jsapi.js",
						"api/js/egw_json.js",
						"api/js/jsapi/egw_core.js",
						"api/js/jsapi/egw_debug.js",
						"api/js/jsapi/egw_preferences.js",
						"api/js/jsapi/egw_utils.js",
						"api/js/jsapi/egw_ready.js",
						"api/js/jsapi/egw_files.js",
						"api/js/jsapi/egw_lang.js",
						"api/js/jsapi/egw_links.js",
						"api/js/jsapi/egw_open.js",
						"api/js/jsapi/egw_user.js",
						"api/js/jsapi/egw_config.js",
						"api/js/jsapi/egw_images.js",
						"api/js/jsapi/egw_jsonq.js",
						"api/js/jsapi/egw_json.js",
						"api/js/jsapi/egw_store.js",
						"api/js/jsapi/egw_tooltip.js",
						"api/js/jsapi/egw_css.js",
						"api/js/jquery/jquery-ui-timepicker-addon.js",
						"api/js/jsapi/egw_calendar.js",
						"api/js/jsapi/egw_data.js",
						"api/js/jsapi/egw_tail.js",
						"api/js/jsapi/egw_inheritance.js",
						"api/js/jsapi/egw_message.js",
						"api/js/jsapi/egw_notification.js",
						"api/js/es6-promise.min.js",
						"api/js/jsapi/app_base.js",
						"api/js/dhtmlxtree/codebase/dhtmlxcommon.js",
						"api/js/dhtmlxtree/sources/dhtmlxtree.js",
						"api/js/dhtmlxtree/sources/ext/dhtmlxtree_json.js",
						"api/js/egw_action/egw_action_common.js",
						"api/js/egw_action/egw_action.js",
						"api/js/egw_action/egw_keymanager.js",
						"api/js/egw_action/egw_menu.js",
						"api/js/jquery/jquery-tap-and-hold/jquery.tapandhold.js",
						"api/js/egw_action/egw_action_popup.js",
						"api/js/egw_action/egw_action_dragdrop.js",
						"api/js/egw_action/egw_dragdrop_dhtmlx_tree.js",
						"api/js/dhtmlxMenu/sources/dhtmlxmenu.js",
						"api/js/dhtmlxMenu/sources/ext/dhtmlxmenu_ext.js",
						"api/js/egw_action/egw_menu_dhtmlx.js",
						"api/js/jquery/chosen/chosen.jquery.js",
						"vendor/egroupware/ckeditor/config.js"
					]
				}
			},
			et2: {
				files: {
					"api/js/etemplate/etemplate2.min.js": [
						"api/js/etemplate/et2_core_xml.js",
						"api/js/etemplate/et2_core_common.js",
						"api/js/etemplate/et2_core_inheritance.js",
						"api/js/etemplate/et2_core_interfaces.js",
						"api/js/etemplate/et2_core_phpExpressionCompiler.js",
						"api/js/etemplate/et2_core_arrayMgr.js",
						"api/js/etemplate/et2_core_widget.js",
						"api/js/etemplate/et2_core_DOMWidget.js",
						"api/js/etemplate/et2_widget_template.js",
						"api/js/etemplate/et2_widget_grid.js",
						"api/js/etemplate/et2_core_baseWidget.js",
						"api/js/etemplate/et2_widget_box.js",
						"api/js/etemplate/et2_widget_hbox.js",
						"api/js/etemplate/et2_widget_groupbox.js",
						"api/js/jquery/splitter.js",
						"api/js/etemplate/et2_widget_split.js",
						"api/js/etemplate/et2_widget_button.js",
						"api/js/etemplate/et2_core_valueWidget.js",
						"api/js/etemplate/et2_core_inputWidget.js",
						"api/js/jquery/jpicker/jpicker-1.1.6.js",
						"api/js/etemplate/et2_widget_color.js",
						"api/js/jquery/blueimp/js/blueimp-gallery.min.js",
						"api/js/etemplate/expose.js",
						"api/js/etemplate/et2_widget_description.js",
						"api/js/etemplate/et2_widget_entry.js",
						"api/js/etemplate/et2_widget_textbox.js",
						"api/js/etemplate/et2_widget_number.js",
						"api/js/jquery/jquery.base64.js",
						"api/js/etemplate/et2_widget_url.js",
						"api/js/etemplate/et2_widget_selectbox.js",
						"api/js/etemplate/et2_widget_checkbox.js",
						"api/js/etemplate/et2_widget_radiobox.js",
						"api/js/etemplate/lib/date.js",
						"api/js/etemplate/et2_widget_date.js",
						"api/js/etemplate/et2_widget_dialog.js",
						"api/js/etemplate/lib/jsdifflib/difflib.js",
						"api/js/etemplate/lib/jsdifflib/diffview.js",
						"api/js/etemplate/et2_widget_diff.js",
						"api/js/etemplate/et2_widget_dropdown_button.js",
						"api/js/etemplate/et2_widget_styles.js",
						"api/js/etemplate/et2_widget_link.js",
						"api/js/etemplate/et2_widget_selectAccount.js",
						"api/js/jquery/magicsuggest/magicsuggest.js",
						"api/js/etemplate/et2_widget_taglist.js",
						"api/js/etemplate/et2_extension_customfields.js",
						"api/js/etemplate/et2_dataview_interfaces.js",
						"api/js/etemplate/et2_dataview_view_container.js",
						"api/js/etemplate/et2_dataview_view_row.js",
						"vendor/bower-asset/jquery-touchswipe/jquery.touchSwipe.js",
						"api/js/etemplate/et2_dataview_view_aoi.js",
						"api/js/etemplate/et2_dataview_controller_selection.js",
						"api/js/etemplate/et2_dataview_controller.js",
						"api/js/etemplate/et2_dataview_view_tile.js",
						"api/js/etemplate/et2_extension_nextmatch_actions.js",
						"api/js/etemplate/et2_extension_nextmatch_controller.js",
						"api/js/etemplate/et2_extension_nextmatch_rowProvider.js",
						"api/js/etemplate/et2_extension_nextmatch_dynheight.js",
						"api/js/etemplate/et2_dataview_model_columns.js",
						"api/js/etemplate/et2_dataview_view_rowProvider.js",
						"api/js/etemplate/et2_dataview_view_spacer.js",
						"api/js/etemplate/et2_dataview_view_grid.js",
						"api/js/etemplate/et2_dataview_view_resizeable.js",
						"api/js/etemplate/et2_dataview.js",
						"api/js/etemplate/et2_extension_nextmatch.js",
						"api/js/etemplate/et2_widget_favorites.js",
						"api/js/etemplate/et2_widget_html.js",
						"api/js/etemplate/et2_widget_htmlarea.js",
						"api/js/etemplate/et2_widget_tabs.js",
						"api/js/etemplate/et2_widget_toolbar.js",
						"api/js/etemplate/et2_widget_timestamper.js",
						"api/js/etemplate/et2_widget_tree.js",
						"api/js/etemplate/et2_widget_historylog.js",
						"api/js/etemplate/et2_widget_hrule.js",
						"vendor/bower-asset/cropper/dist/cropper.min.js",
						"api/js/etemplate/et2_widget_image.js",
						"api/js/etemplate/et2_widget_iframe.js",
						"api/js/Resumable/resumable.js",
						"api/js/etemplate/et2_widget_file.js",
						"api/js/etemplate/et2_widget_progress.js",
						"api/js/etemplate/et2_widget_portlet.js",
						"api/js/etemplate/et2_widget_ajaxSelect.js",
						"api/js/etemplate/et2_widget_vfs.js",
						"api/js/etemplate/et2_widget_video.js",
						"api/js/jquery/barcode/jquery-barcode.min.js",
						"api/js/etemplate/et2_widget_barcode.js",
						"api/js/etemplate/et2_extension_itempicker_actions.js",
						"api/js/etemplate/et2_widget_itempicker.js",
						"api/js/etemplate/et2_widget_script.js",
						"api/js/etemplate/et2_core_legacyJSFunctions.js",
						"api/js/etemplate/etemplate2.js",
						"api/js/etemplate/vfsSelectUI.js"
					]
				}
			},
			mail: {
				files: {
					"mail/js/app.min.js": [
						"mail/js/app.js"
					]
				}
			},
			calendar: {
				files: {
					"calendar/js/app.min.js": [
						"calendar/js/et2_widget_owner.js",
						"calendar/js/et2_widget_view.js",
						"calendar/js/et2_widget_timegrid.js",
						"calendar/js/et2_widget_event.js",
						"calendar/js/et2_widget_daycol.js",
						"calendar/js/et2_widget_planner_row.js",
						"calendar/js/et2_widget_planner.js",
						"calendar/js/app.js"
					]
				}
			},
			jdots: {
				files: {
					"jdots/js/fw_jdots.min.js": [
						"api/js/framework/fw_base.js",
						"api/js/framework/fw_browser.js",
						"api/js/jquery/mousewheel/mousewheel.js",
						"api/js/framework/fw_ui.js",
						"api/js/framework/fw_classes.js",
						"api/js/framework/fw_desktop.js",
						"jdots/js/fw_jdots.js"
					]
				}
			},
			mobile: {
				files: {
					"pixelegg/js/fw_mobile.min.js": [
						"vendor/bower-asset/fastclick/lib/fastclick.js",
						"api/js/framework/fw_base.js",
						"api/js/framework/fw_browser.js",
						"api/js/jquery/mousewheel/mousewheel.js",
						"api/js/framework/fw_ui.js",
						"api/js/framework/fw_classes.js",
						"pixelegg/js/fw_mobile.js"
					]
				}
			},
			pixelegg: {
				files: {
					"pixelegg/js/fw_pixelegg.min.js": [
						"api/js/framework/fw_base.js",
						"api/js/framework/fw_browser.js",
						"api/js/jquery/mousewheel/mousewheel.js",
						"api/js/framework/fw_ui.js",
						"api/js/framework/fw_classes.js",
						"api/js/framework/fw_desktop.js",
						"pixelegg/js/slider.js",
						"pixelegg/js/fw_pixelegg.js"
					]
				}
			},
			projectmanager: {
				files: {
					"projectmanager/js/app.min.js": [
						"projectmanager/js/dhtmlxGantt/codebase/dhtmlxgantt.js",
						"projectmanager/js/dhtmlxGantt/codebase/ext/dhtmlxgantt_marker.js",
						"projectmanager/js/et2_widget_gantt.js",
						"projectmanager/js/app.js"
					]
				}
			}
		},
		cssmin: {
			options: {
				shorthandCompacting: false,
				sourceMap: true,
				rebase: true
			},
			pixelegg: {
				files: {
					"pixelegg/css/pixelegg.min.css": [
						"api/js/jquery/chosen/chosen.css",
						"vendor/bower-asset/jquery-ui/themes/redmond/jquery-ui.css",
						"api/js/jquery/magicsuggest/magicsuggest.css",
						"api/js/jquery/jpicker/css/jPicker-1.1.6.min.css",
						"api/js/jquery/jquery-ui-timepicker-addon.css",
						"api/js/jquery/blueimp/css/blueimp-gallery.min.css",
						"api/js/dhtmlxtree/codebase/dhtmlxtree.css",
						"api/js/egw_action/test/skins/dhtmlxmenu_egw.css",
						"api/js/etemplate/lib/jsdifflib/diffview.css",
						"vendor/bower-asset/cropper/dist/cropper.min.css",
						"api/templates/default/etemplate2.css",
						"pixelegg/css/pixelegg.css",
						"api/templates/default/print.css",
						"pixelegg/print.css"
					],
					"pixelegg/css/mobile.min.css": [
						"api/js/jquery/chosen/chosen.css",
						"vendor/bower-asset/jquery-ui/themes/redmond/jquery-ui.css",
						"api/js/jquery/magicsuggest/magicsuggest.css",
						"api/js/jquery/jpicker/css/jPicker-1.1.6.min.css",
						"api/js/jquery/jquery-ui-timepicker-addon.css",
						"api/js/jquery/blueimp/css/blueimp-gallery.min.css",
						"api/js/dhtmlxtree/codebase/dhtmlxtree.css",
						"api/js/egw_action/test/skins/dhtmlxmenu_egw.css",
						"api/js/etemplate/lib/jsdifflib/diffview.css",
						"vendor/bower-asset/cropper/dist/cropper.min.css",
						"api/templates/default/etemplate2.css",
						"pixelegg/css/mobile.css",
						"api/templates/default/print.css",
						"pixelegg/print.css"
					],
					"pixelegg/mobile/fw_mobile.min.css": [
						"api/js/jquery/chosen/chosen.css",
						"vendor/bower-asset/jquery-ui/themes/redmond/jquery-ui.css",
						"api/js/jquery/magicsuggest/magicsuggest.css",
						"api/js/jquery/jpicker/css/jPicker-1.1.6.min.css",
						"api/js/jquery/jquery-ui-timepicker-addon.css",
						"api/js/jquery/blueimp/css/blueimp-gallery.min.css",
						"api/js/dhtmlxtree/codebase/dhtmlxtree.css",
						"api/js/egw_action/test/skins/dhtmlxmenu_egw.css",
						"api/js/etemplate/lib/jsdifflib/diffview.css",
						"vendor/bower-asset/cropper/dist/cropper.min.css",
						"api/templates/default/etemplate2.css",
						"pixelegg/mobile/fw_mobile.css",
						"api/templates/default/print.css",
						"pixelegg/print.css"
					],
					"pixelegg/css/Standard.min.css": [
						"api/js/jquery/chosen/chosen.css",
						"vendor/bower-asset/jquery-ui/themes/redmond/jquery-ui.css",
						"api/js/jquery/magicsuggest/magicsuggest.css",
						"api/js/jquery/jpicker/css/jPicker-1.1.6.min.css",
						"api/js/jquery/jquery-ui-timepicker-addon.css",
						"api/js/jquery/blueimp/css/blueimp-gallery.min.css",
						"api/js/dhtmlxtree/codebase/dhtmlxtree.css",
						"api/js/egw_action/test/skins/dhtmlxmenu_egw.css",
						"api/js/etemplate/lib/jsdifflib/diffview.css",
						"vendor/bower-asset/cropper/dist/cropper.min.css",
						"api/templates/default/etemplate2.css",
						"pixelegg/css/pixelegg.css",
						"api/templates/default/print.css",
						"pixelegg/print.css"
					],
					"pixelegg/css/Compact.min.css": [
						"api/js/jquery/chosen/chosen.css",
						"vendor/bower-asset/jquery-ui/themes/redmond/jquery-ui.css",
						"api/js/jquery/magicsuggest/magicsuggest.css",
						"api/js/jquery/jpicker/css/jPicker-1.1.6.min.css",
						"api/js/jquery/jquery-ui-timepicker-addon.css",
						"api/js/jquery/blueimp/css/blueimp-gallery.min.css",
						"api/js/dhtmlxtree/codebase/dhtmlxtree.css",
						"api/js/egw_action/test/skins/dhtmlxmenu_egw.css",
						"api/js/etemplate/lib/jsdifflib/diffview.css",
						"vendor/bower-asset/cropper/dist/cropper.min.css",
						"api/templates/default/etemplate2.css",
						"pixelegg/css/pixelegg.css",
						"api/templates/default/print.css",
						"pixelegg/print.css"
					],
					"pixelegg/css/Traditional.min.css": [
						"api/js/jquery/chosen/chosen.css",
						"vendor/bower-asset/jquery-ui/themes/redmond/jquery-ui.css",
						"api/js/jquery/magicsuggest/magicsuggest.css",
						"api/js/jquery/jpicker/css/jPicker-1.1.6.min.css",
						"api/js/jquery/jquery-ui-timepicker-addon.css",
						"api/js/jquery/blueimp/css/blueimp-gallery.min.css",
						"api/js/dhtmlxtree/codebase/dhtmlxtree.css",
						"api/js/egw_action/test/skins/dhtmlxmenu_egw.css",
						"api/js/etemplate/lib/jsdifflib/diffview.css",
						"vendor/bower-asset/cropper/dist/cropper.min.css",
						"api/templates/default/etemplate2.css",
						"pixelegg/css/pixelegg.css",
						"api/templates/default/print.css",
						"pixelegg/print.css"
					]
				}
			},
			jdots: {
				files: {
					"jdots/css/high-contrast.min.css": [
						"api/js/jquery/chosen/chosen.css",
						"vendor/bower-asset/jquery-ui/themes/redmond/jquery-ui.css",
						"api/js/jquery/magicsuggest/magicsuggest.css",
						"api/js/jquery/jpicker/css/jPicker-1.1.6.min.css",
						"api/js/jquery/jquery-ui-timepicker-addon.css",
						"api/js/jquery/blueimp/css/blueimp-gallery.min.css",
						"api/js/dhtmlxtree/codebase/dhtmlxtree.css",
						"api/js/egw_action/test/skins/dhtmlxmenu_egw.css",
						"api/js/etemplate/lib/jsdifflib/diffview.css",
						"vendor/bower-asset/cropper/dist/cropper.min.css",
						"api/templates/default/etemplate2.css",
						"api/templates/default/def_tutorials.css",
						"api/templates/default/default.css",
						"jdots/egw_fw.css",
						"jdots/css/jdots.css",
						"jdots/css/high-contrast.css",
						"api/templates/default/print.css",
						"jdots/print.css"
					],
					"jdots/css/jdots.min.css": [
						"api/js/jquery/chosen/chosen.css",
						"vendor/bower-asset/jquery-ui/themes/redmond/jquery-ui.css",
						"api/js/jquery/magicsuggest/magicsuggest.css",
						"api/js/jquery/jpicker/css/jPicker-1.1.6.min.css",
						"api/js/jquery/jquery-ui-timepicker-addon.css",
						"api/js/jquery/blueimp/css/blueimp-gallery.min.css",
						"api/js/dhtmlxtree/codebase/dhtmlxtree.css",
						"api/js/egw_action/test/skins/dhtmlxmenu_egw.css",
						"api/js/etemplate/lib/jsdifflib/diffview.css",
						"vendor/bower-asset/cropper/dist/cropper.min.css",
						"api/templates/default/etemplate2.css",
						"api/templates/default/def_tutorials.css",
						"api/templates/default/default.css",
						"jdots/egw_fw.css",
						"jdots/css/jdots.css",
						"api/templates/default/print.css",
						"jdots/print.css"
					],
					"jdots/css/orange-green.min.css": [
						"api/js/jquery/chosen/chosen.css",
						"vendor/bower-asset/jquery-ui/themes/redmond/jquery-ui.css",
						"api/js/jquery/magicsuggest/magicsuggest.css",
						"api/js/jquery/jpicker/css/jPicker-1.1.6.min.css",
						"api/js/jquery/jquery-ui-timepicker-addon.css",
						"api/js/jquery/blueimp/css/blueimp-gallery.min.css",
						"api/js/dhtmlxtree/codebase/dhtmlxtree.css",
						"api/js/egw_action/test/skins/dhtmlxmenu_egw.css",
						"api/js/etemplate/lib/jsdifflib/diffview.css",
						"vendor/bower-asset/cropper/dist/cropper.min.css",
						"api/templates/default/etemplate2.css",
						"api/templates/default/def_tutorials.css",
						"api/templates/default/default.css",
						"jdots/egw_fw.css",
						"jdots/css/jdots.css",
						"jdots/css/orange-green.css",
						"api/templates/default/print.css",
						"jdots/print.css"
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
	// Load the plugin that provides the "uglify" task.
	grunt.loadNpmTasks('grunt-contrib-uglify');

	// Load plugin for css minificaton
	grunt.loadNpmTasks('grunt-contrib-cssmin');

	// Load the plugin that runs tasks only on modified files
	grunt.loadNpmTasks('grunt-newer');

	// uncomment to run Gruntfile.js in apps / sub-directories
	//grunt.loadNpmTasks('grunt-hub');

	// Default task(s).
	grunt.registerTask('default', ['newer:uglify', 'newer:cssmin']);//, 'hub']);
};
