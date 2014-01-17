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
			$export_limit = bo_merge::getExportLimit($appname);
			//error_log(__METHOD__.__LINE__.' app:'.$appname.' limit:'.$export_limit);
			if(bo_merge::is_export_limit_excepted() || $export_limit !== 'no')
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
						'ajax' => 'true'
				),$GLOBALS['egw_info']['user']['apps']['admin'] ? 'admin' : 'preferences');
			}
			display_sidebox($appname,lang($appname),$file);
		}

		if ($GLOBALS['egw_info']['user']['apps']['admin'])
		{
			$file = Array(
				'Site Configuration' => egw::link('/index.php','menuaction=importexport.importexport_definitions_ui.site_config','admin'),
				'Import definitions' => egw::link('/index.php','menuaction=importexport.importexport_definitions_ui.import_definition','admin'),
				'Define imports|exports'  => egw::link('/index.php',array(
					'menuaction' => 'importexport.importexport_definitions_ui.index',
					'ajax' => 'true'
				),'admin'),
				'Schedule' => egw::link('/index.php', array(
					'menuaction' => 'importexport.importexport_schedule_ui.index'
				),'admin'),
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
		$plugins = importexport_helper_functions::get_plugins($appname);
		if($cache[$appname]['import'])
		{
			$file['Import CSV'] = array('link' => "javascript:egw_openWindowCentered2('".
				egw::link('/index.php',array(
					'menuaction' => 'importexport.importexport_import_ui.import_dialog',
					'appname'=>$appname
				),false)."','_blank',850,440,'yes')",
				'icon' => 'import',
				'app' => 'importexport',
				'text' => in_array($appname, array('sitemgr')) || count($plugins[$appname]['import']) > 1 ? 'Import' : 'Import CSV'
			);
			if($GLOBALS['egw_info']['flags']['disable_importexport']['import']) {
				$file['Import CSV']['link'] = '';
			}
		}
		$export_limit = bo_merge::getExportLimit($appname);
		//error_log(__METHOD__.__LINE__.' app:'.$appname.' limit:'.$export_limit);
		if ((bo_merge::is_export_limit_excepted() || bo_merge::hasExportLimit($export_limit,'ISALLOWED')) && $cache[$appname]['export'])
		{
			$file['Export CSV'] = array('link' => "javascript:egw_openWindowCentered2('".
				egw::link('/index.php',array(
					'menuaction' => 'importexport.importexport_export_ui.export_dialog',
					'appname'=>$appname
				),false)."','_blank',850,440,'yes')",
				'icon' => 'export',
				'app' => 'importexport',
				'text' => in_array($appname, array('sitemgr')) || count($plugins[$appname]['export']) > 1 ? 'Export' : 'Export CSV'
			);
			if($GLOBALS['egw_info']['flags']['disable_importexport']['export']) {
				$file['Export CSV']['link'] = '';
			}
		}
		
		$config = config::read('importexport');
		if($appname != 'admin' && ($config['users_create_definitions'] || $GLOBALS['egw_info']['user']['apps']['admin']) &&
			count(importexport_helper_functions::get_plugins($appname)) > 0
		)
		{
			$file['Define imports|exports']	= egw::link('/index.php',array(
				'menuaction' => 'importexport.importexport_definitions_ui.index',
				'application' => $appname,
				'ajax' => 'true'
			),$GLOBALS['egw_info']['user']['apps']['admin'] ? 'admin' : 'preferences');
		}
		if($file) display_sidebox($appname,lang('importexport'),$file);
	}
	
	/**
	 * Returns a list of custom widgets classes for etemplate2
	 */
	public static function widgets()
	{
		return array('importexport_widget_filter');
	}
}
