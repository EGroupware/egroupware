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
	var $config = array();

	function importexport_admin_prefs_sidebox_hooks()
	{
		$config =& CreateObject('phpgwapi.config',IMPORTEXPORT_APP);
		$config->read_repository();
		$this->config =& $config->config_data;
		unset($config);
	}

	function all_hooks($args)
	{
		$appname = IMPORTEXPORT_APP;
		$location = is_array($args) ? $args['location'] : $args;

		if ($location == 'sidebox_menu')
		{
			$file = array(
				'Import definitions' => $GLOBALS['egw']->link('/index.php','menuaction=importexport.uidefinitions.import_definition'),
			);
			display_sidebox($appname,$GLOBALS['egw_info']['apps'][$appname]['title'].' '.lang('Menu'),$file);
		}

		if ($GLOBALS['egw_info']['user']['apps']['preferences'] && $location != 'admin')
		{
			$file = array(
//				'Preferences'     => $GLOBALS['egw']->link('/index.php','menuaction=preferences.uisettings.index&appname='.$appname),
//				'Grant Access'    => $GLOBALS['egw']->link('/index.php','menuaction=preferences.uiaclprefs.index&acl_app='.$appname),
//				'Edit Categories' => $GLOBALS['egw']->link('/index.php','menuaction=preferences.uicategories.index&cats_app=' . $appname . '&cats_level=True&global_cats=True')
			);
			if ($location == 'preferences')
			{
				display_section($appname,$file);
			}
			else
			{
				display_sidebox($appname,lang('Preferences'),$file);
			}
		}

		if ($GLOBALS['egw_info']['user']['apps']['admin'] && $location != 'preferences')
		{
			$file = Array(
				'Define {im|ex}ports'  => $GLOBALS['egw']->link('/index.php',array(
					'menuaction' => 'importexport.uidefinitions.index',
				)),
				'Schedule' => $GLOBALS['egw']->link('/index.php', array(
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
	 * populates $GLOBALS['settings'] for the preferences
	 */
	function settings()
	{
		$this->check_set_default_prefs();

		return true;	// otherwise prefs say it cant find the file ;-)
	}
	
	/**
	 * Check if reasonable default preferences are set and set them if not
	 *
	 * It sets a flag in the app-session-data to be called only once per session
	 */
	function check_set_default_prefs()
	{
		if ($GLOBALS['egw']->session->appsession('default_prefs_set',IMPORTEXPORT_APP))
		{
			return;
		}
		$GLOBALS['egw']->session->appsession('default_prefs_set',IMPORTEXPORT_APP,'set');

		$default_prefs =& $GLOBALS['egw']->preferences->default[IMPORTEXPORT_APP];

		$defaults = array(
		);
		foreach($defaults as $var => $default)
		{
			if (!isset($default_prefs[$var]) || $default_prefs[$var] === '')
			{
				$GLOBALS['egw']->preferences->add(IMPORTEXPORT_APP,$var,$default,'default');
				$need_save = True;
			}
		}
		if ($need_save)
		{
			$GLOBALS['egw']->preferences->save_repository(False,'default');
		}
	}
}
