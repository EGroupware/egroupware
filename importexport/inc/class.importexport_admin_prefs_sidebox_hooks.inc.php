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
		if($cache[$appname]['export']) 
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
		
		$file['Export Spreadsheet'] = array('link' => egw::link('/index.php',array(
				'menuaction' => 'importexport.importexport_admin_prefs_sidebox_hooks.spreadsheet_list',
				'app' => $appname
			)),
			//'icon' => 'filemanager/navbar',
			'app' => 'importexport',
			'text' => 'Export Spreadsheet'
		);
	
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

	public function spreadsheet_list() {
		$config = config::read('importexport');
		$config_dirs = $config['export_spreadsheet_folder'] ? explode(',',$config['export_spreadsheet_folder']) : array('user','stylite');
		$appname = $_GET['app'];
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

		$GLOBALS['egw_info']['flags']['app_header'] = lang('Export (merge) spreadsheets');
		$tmpl = new etemplate('importexport.spreadsheet_list');
		$tmpl->exec('importexport.importexport_admin_hooks.spreadsheet_list', array('files' => $file_list));
	}
}
