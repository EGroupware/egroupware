<?php
/**
 * eGroupWare - importexport
 *
 * @license http://opensource.org/licenses/gpl-license.php GPL - GNU General Public License
 * @package importexport
 * @link http://www.egroupware.org
 * @copyright Cornelius Weiss <nelius@cwtech.de>
 * @version $Id$
 */

if (!defined('IMPORTEXPORT_APP'))
{
	define('IMPORTEXPORT_APP','importexport');
}

class importexport_admin_prefs_sidebox_hooks
{

	public $public_functions = array(
		'spreadsheet_list' => true
	);

	static function all_hooks($args)
	{
		$appname = IMPORTEXPORT_APP;
		$location = is_array($args) ? $args['location'] : $args;

		if ($GLOBALS['egw_info']['user']['apps']['preferences'] && $location != 'admin')
		{
			$file = array(
				array(
					'text' => 'Import',
					'link' => "javascript:egw_openWindowCentered2('".
						egw::link('/index.php','menuaction=importexport.importexport_import_ui.import_dialog',false).
						"','_blank',850,440,'yes')",
					'icon' => 'import'
				),
			);
			$config = config::read('phpgwapi');
			if($GLOBALS['egw_info']['user']['apps']['admin'] || $config['export_limit'] !== 'no')
			{
				$file[] = array(
					'text' => 'Export',
					'link' => "javascript:egw_openWindowCentered2('".
						egw::link('/index.php','menuaction=importexport.importexport_export_ui.export_dialog',false).
						"','_blank',850,440,'yes')",
					'icon' => 'export'
				);
			}
			$config = config::read($appname);
			if($config['users_create_definitions'])
			{
				$file['Define imports|exports']	= egw::link('/index.php',array(
						'menuaction' => 'importexport.importexport_definitions_ui.index',
				));
			}
			if ($location == 'preferences')
			{
				display_section($appname,$file);
			}
			else
			{
				display_sidebox($appname,lang($appname),$file);
			}
		}

		if ($GLOBALS['egw_info']['user']['apps']['admin'] && $location != 'preferences')
		{
			$file = Array(
				'Site Configuration' => egw::link('/index.php','menuaction=importexport.importexport_definitions_ui.site_config'),
				'Import definitions' => egw::link('/index.php','menuaction=importexport.importexport_definitions_ui.import_definition'),
				'Define imports|exports'  => egw::link('/index.php',array(
					'menuaction' => 'importexport.importexport_definitions_ui.index',
				)),
				'Schedule' => egw::link('/index.php', array(
					'menuaction' => 'importexport.importexport_schedule_ui.index'
				)),
			);
			if ($location == 'admin')
			{
				display_section($appname,$file);
			}
			else
			{
				display_sidebox($appname,lang('Admin'),$file);
			}
		}
	}

	/**
	 * Called from framework so Import / Export can add links into other apps' sidebox.
	 */
	public static function other_apps() {
		if(!$GLOBALS['egw_info']['user']['apps']['importexport']) return array();
		if($GLOBALS['egw_info']['flags']['no_importexport'] === true) return array();

		$appname = $GLOBALS['egw_info']['flags']['currentapp'];
		$cache = egw_cache::getCache(egw_cache::SESSION, 'importexport', 'sidebox_links');

		if(!$cache[$appname] && $GLOBALS['egw_info']['user']['apps']['importexport']) {
			$cache[$appname]['import'] = importexport_helper_functions::has_definitions($appname, 'import');
			$cache[$appname]['export'] = importexport_helper_functions::has_definitions($appname, 'export');
			egw_cache::setCache(egw_cache::SESSION, 'importexport', 'sidebox_links', $cache);
		}

		// Add in import / export, if available
		$file = array();
		if($cache[$appname]['import'])
		{
			$file['Import CSV'] = array('link' => "javascript:egw_openWindowCentered2('".
				egw::link('/index.php',array(
					'menuaction' => 'importexport.importexport_import_ui.import_dialog',
					'appname'=>$appname
				),false)."','_blank',500,220,'yes')",
				'icon' => 'import',
				'app' => 'importexport',
				'text' => in_array($appname, array('calendar', 'sitemgr')) ? 'Import' : 'Import CSV'
			);
			if($GLOBALS['egw_info']['flags']['disable_importexport']['import']) {
				$file['Import CSV']['link'] = '';
			}
		}
		$config = config::read('phpgwapi');
		if (($GLOBALS['egw_info']['user']['apps']['admin'] || !$config['export_limit'] || $config['export_limit'] > 0) && $cache[$appname]['export'])
		{
			$file['Export CSV'] = array('link' => "javascript:egw_openWindowCentered2('".
				egw::link('/index.php',array(
					'menuaction' => 'importexport.importexport_export_ui.export_dialog',
					'appname'=>$appname
				),false)."','_blank',850,440,'yes')",
				'icon' => 'export',
				'app' => 'importexport',
				'text' => in_array($appname, array('calendar', 'sitemgr')) ? 'Export' : 'Export CSV'
			);
			if($GLOBALS['egw_info']['flags']['disable_importexport']['export']) {
				$file['Export CSV']['link'] = '';
			}
		}
		if(($file_list = bo_merge::get_documents($GLOBALS['egw_info']['user']['preferences'][$appname]['document_dir'], '', array(
			'application/vnd.oasis.opendocument.spreadsheet',
			'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet'
		))))
		{
			$prefix = 'document_';

			$options = 'style="max-width:175px;" onchange="var win= egw_appWindow(\''.$appname.'\');
if(win.egw_getActionManager) {
var actionMgrs = win.egw_getActionManager(\''.$appname.'\').getActionsByAttr(\'type\', \'actionManager\');
var actionMgr = null;
for(var i = 0; i < actionMgrs.length; i++) {
	if(typeof actionMgrs[i].etemplate_var_prefix != \'undefined\') {
		actionMgr = actionMgrs[i];
		break;
	}
}
var objectMgr = win.egw_getObjectManager(actionMgr.id);
if(actionMgr && objectMgr && win.egwAction) {
	var action = new win.egwAction(actionMgr,\''.$prefix.'\'+this.value);
	var toggle_select = false;
	if(objectMgr.selectedChildren.length == 0) {
		// Be nice and select all, if they forgot to select any
		if(actionMgr.getActionById(\'select_all\')) {
			var total = parseInt($(\'span#total\',actionMgr.etemplate_form).text());
			if(total > 0) {
				actionMgr.getActionById(\'select_all\').set_checked(true);
				toggle_select = true;
			} else {
				alert(\''.lang('You need to select some entries first!').'\');
				this.value = \'\';
				return false;
			}
		}
	}
	win.nm_action(action, objectMgr.selectedChildren);
	if(toggle_select) {
		// Turn it back off again
		actionMgr.getActionById(\'select_all\').set_checked(false);
	}
}
} else {';
			if($appname == 'calendar')
			{
				$options .= "
var win=egw_appWindow('calendar'); win.location=win.location+(win.location.search.length ? '&' : '?')+'merge='+this.value;this.value='';";
			}
$options .= '
}
this.value = \'\'"';
			if($GLOBALS['egw_info']['flags']['disable_importexport']['merge']) {
				$options = 'disabled="disabled"';
			}
			$file[] = array(
				'text'	=> html::select('merge',false,array('' =>  lang('Export Spreadsheet')) + $file_list, true,$options),
				'noLang'	=> true,
				'link'	=> false,
			);
		}

		$config = config::read('importexport');
		if($appname != 'admin' && ($config['users_create_definitions'] || $GLOBALS['egw_info']['user']['apps']['admin']) &&
			count(importexport_helper_functions::get_plugins($appname)) > 0
		)
		{
			$file['Define imports|exports']	= egw::link('/index.php',array(
				'menuaction' => 'importexport.importexport_definitions_ui.index',
			), 'importexport');
		}
		if($file) display_sidebox($appname,lang('importexport'),$file);
	}
}
