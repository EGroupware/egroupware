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
			if($config['export_limit'] !== 'no')
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
			$file['Import'] = array('link' => "javascript:egw_openWindowCentered2('".
				egw::link('/index.php',array(
					'menuaction' => 'importexport.importexport_import_ui.import_dialog',
					'appname'=>$appname
				),false)."','_blank',500,220,'yes')",
				'icon' => 'import',
				'app' => 'importexport',
				'text' => 'import'
			);
		}
		if($cache[$appname]['export']) 
		{
			$file['Export'] = array('link' => "javascript:egw_openWindowCentered2('".
				egw::link('/index.php',array(
					'menuaction' => 'importexport.importexport_export_ui.export_dialog',
					'appname'=>$appname
				),false)."','_blank',850,440,'yes')",
				'icon' => 'export',
				'app' => 'importexport',
				'text' => 'export'
			);
		}
		if($file) display_sidebox($appname,lang('importexport'),$file);
	}
}
