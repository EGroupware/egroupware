<?php
/**
 * EGroupware: CalDAV/CardDAV/GroupDAV access: hooks eg. preferences
 *
 * @link http://www.egroupware.org
 * @license http://opensource.org/licenses/gpl-license.php GPL - GNU General Public License
 * @package api
 * @subpackage groupdav
 * @author Ralf Becker <RalfBecker-AT-outdoor-training.de>
 * @copyright (c) 2010-16 by Ralf Becker <RalfBecker-AT-outdoor-training.de>
 * @version $Id$
 */

namespace EGroupware\Api\CalDAV;

use EGroupware\Api;

/**
 * GroupDAV hooks: eg. preferences
 */
class Hooks
{
	public $public_functions = array(
		'log' => true,
	);

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
				'Preferences'     => Api\Framework::link('/index.php','menuaction=preferences.preference_settings.index&appname='.$appname),
			);
			if ($location == 'preferences')
			{
				display_section($appname,$file);
			}
			else
			{
				$GLOBALS['egw']->framework->sidebox($appname,lang('Preferences'),$file);
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
				$settings[] = array(
					'type'  => 'section',
					'title' => $app,
				);
				$settings += call_user_func(array($class_name,'get_settings'), $hook_data);
			}
		}

		$settings += Api\WebDAV\Hooks::logSettings('groupdav',
			'Enables logging of CalDAV/CardDAV traffic to diagnose problems with devices.',
			'api.'.__CLASS__.'.log', $hook_data['account_id']);

		return $settings;
	}

	/**
	 * Open log window for log-file specified in GET parameter filename (relative to files_dir)
	 *
	 * $_GET['filename'] has to be in groupdav sub-dir of files_dir and start with account_lid of current user
	 *
	 * @throws Api\Exception\WrongParameter
	 */
	public static function log()
	{
		Api\WebDAV\Hooks::logViewer('groupdav');
	}

	/**
	 * Hooks to show CalDAV/CardDAV/REST-API App configuration
	 *
	 * @param string|array $args hook args
	 */
	public static function adminHook($args)
	{
		if ($GLOBALS['egw_info']['user']['apps']['admin'])
		{
			display_section($appname='groupdav', [
				'OpenAPI configuration' => Api\Egw::link('/index.php','menuaction=api.EGroupware\\Api\\CalDAV\\OpenAPI.configuration&ajax=true'),
			]);
		}
	}
}