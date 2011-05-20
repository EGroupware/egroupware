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
				'text' => 'Import CSV'
			);
		}
		$config = config::read('phpgwapi');
		if(($GLOBALS['egw_info']['user']['apps']['admin'] || $config['export_limit'] !== 'no') && $cache[$appname]['export']) 
		{
			$file['Export CSV'] = array('link' => "javascript:egw_openWindowCentered2('".
				egw::link('/index.php',array(
					'menuaction' => 'importexport.importexport_export_ui.export_dialog',
					'appname'=>$appname
				),false)."','_blank',850,440,'yes')",
				'icon' => 'export',
				'app' => 'importexport',
				'text' => 'Export CSV'
			);
		}
		if($list = self::get_spreadsheet_list($appname)) {
			$file_list = array();
			foreach($list as $_file) {
				$file_list[$_file['path']] = egw_vfs::decodePath($_file['name']);
			}
			$prefix = 'document_';
			
			$options = 'style="max-width:175px;" onchange="var window = egw_appWindow(\''.$appname.'\'); 
var action = new window.egwAction(null,\''.$prefix.'\'+this.value);
if(window.egw_objectManager.selectedChildren.length == 0) {
	// Be nice and select all, if they forgot to select any
	window.egw_actionManager.getActionById(\'select_all\').set_checked(true);
}
window.nm_action(action, window.egw_objectManager.selectedChildren); 
this.value = \'\'"';
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

	/**
	 * Get a list of spreadsheets, in the user's preference folder, and / or a system provided template folder
	 */
	public static function get_spreadsheet_list($app) {
		$config = config::read('importexport');
		$config_dirs = $config['export_spreadsheet_folder'] ? explode(',',$config['export_spreadsheet_folder']) : array('user','stylite');
		$appname = $_GET['app'] ? $_GET['app'] : $app;
		$stylite_dir = '/stylite/templates/merge';
		$dir_list = array();
		$file_list = array();
		$mimes = array(
			'application/vnd.oasis.opendocument.spreadsheet',
			'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet'
		);

		if(in_array('user', $config_dirs)) {
			$dir_list[] = $GLOBALS['egw_info']['user']['preferences'][$appname]['document_dir'];
		}
		if(in_array('stylite', $config_dirs) && is_dir($stylite_dir)) {
			$dir_list[] = $stylite_dir;
		}
		
		foreach($dir_list as $dir) {
			if ($dir && ($files = egw_vfs::find($dir,array(
				'need_mime' => true,
				'order' => 'fs_name',
				'sort' => 'ASC',
			),true)))
			{
				foreach($files as $file) {
					if($dir != $GLOBALS['egw_info']['user']['preferences'][$appname]['document_dir']) {
						// Stylite files have a naming convention that must be adhered to
						list($export, $application, $name) = explode('-', $file['name'], 3);
					}
					if(($dir == $GLOBALS['egw_info']['user']['preferences'][$appname]['document_dir'] || ($export == 'export' && $application == $appname)) && in_array($file['mime'], $mimes)) {
						$file_list[] = array(
							'name'	=>	$name ? $name : $file['name'],
							'path'	=>	$file['path'],
							'mime'	=>	$file['mime']
						);
					}
				}
			}
		}
		return $file_list;
	}

}
