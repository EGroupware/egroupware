<?php
/**
 * EGroupware: GroupDAV hooks: eg. preferences
 *
 * @link http://www.egroupware.org
 * @license http://opensource.org/licenses/gpl-license.php GPL - GNU General Public License
 * @package api
 * @subpackage groupdav
 * @author Ralf Becker <RalfBecker-AT-outdoor-training.de>
 * @copyright (c) 2010-12 by Ralf Becker <RalfBecker-AT-outdoor-training.de>
 * @version $Id$
 */

/**
 * GroupDAV hooks: eg. preferences
 */
class groupdav_hooks
{
	/**
	 * Show GroupDAV preferences link in preferences
	 *
	 * @param string|array $args
	 */
	public static function menus($args)
	{
		$appname = 'groupdav';
		$location = is_array($args) ? $args['location'] : $args;

		if ($location == 'preferences')
		{
			$file = array(
				'Preferences'     => egw::link('/index.php','menuaction=preferences.uisettings.index&appname='.$appname),
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
	}

	/**
	 * populates $settings for the preferences
	 *
	 * @param array|string $hook_data
	 * @return array
	 */
	static function settings($hook_data)
	{
		$settings = array();

		if ($hook_data['setup'])
		{
			$apps = array('addressbook','calendar','infolog');
		}
		else
		{
			$apps = array_keys($GLOBALS['egw_info']['user']['apps']);
		}
		foreach($apps as $app)
		{
			$class_name = $app.'_groupdav';
			if (class_exists($class_name, true))
			{
				$settings += call_user_func(array($class_name,'get_settings'));
			}
		}

		$settings['debug_level'] = array(
			'type'   => 'select',
			'label'  => 'Debug level for Apache/PHP error-log',
			'name'   => 'debug_level',
			'help'   => 'Enables debug-messages to Apache/PHP error-log, allowing to diagnose problems on a per user basis.',
			'values' => array(
				'0' => 'Off',
				'r' => 'Requests and truncated responses',
				'f' => 'Requests and full responses to files directory',
				'1' => 'Debug 1 - function calls',
				'2' => 'Debug 2 - more info',
				'3' => 'Debug 3 - complete $_SERVER array',
			),
			'xmlrpc' => true,
			'admin'  => false,
			'default' => '0',
		);
		return $settings;
	}
}