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
				/*
				array(
					'text' => 'Export',
					'link' => "javascript:egw_openWindowCentered2('".
						egw::link('/index.php','menuaction=importexport.importexport_export_ui.export_dialog',false).
						"','_blank',850,440,'yes')",
					'icon' => 'export'
				),
				*/
			);
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
}
