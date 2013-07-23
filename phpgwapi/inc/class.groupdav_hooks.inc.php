<?php
/**
 * EGroupware: GroupDAV hooks: eg. preferences
 *
 * @link http://www.egroupware.org
 * @license http://opensource.org/licenses/gpl-license.php GPL - GNU General Public License
 * @package api
 * @subpackage groupdav
 * @author Ralf Becker <RalfBecker-AT-outdoor-training.de>
 * @copyright (c) 2010-13 by Ralf Becker <RalfBecker-AT-outdoor-training.de>
 * @version $Id$
 */

/**
 * GroupDAV hooks: eg. preferences
 */
class groupdav_hooks
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
				$settings[] = array(
					'type'  => 'section',
					'title' => $app,
				);
				$settings += call_user_func(array($class_name,'get_settings'), $hook_data);
			}
		}

		$settings[] = array(
			'type'  => 'section',
			'title' => 'Logging / debuging',
		);
		$settings['debug_level'] = array(
			'type'   => 'select',
			'label'  => 'Enable logging',
			'name'   => 'debug_level',
			'help'   => 'Enables logging of CalDAV/CardDAV traffic to diagnose problems with devices.',
			'values' => array(
				'0' => lang('Off'),
				'r' => lang('Requests and truncated responses to Apache error-log'),
				'f' => lang('Requests and full responses to files directory'),
			),
			'xmlrpc' => true,
			'admin'  => false,
			'default' => '0',
		);
		if ($GLOBALS['type'] === 'forced' || $GLOBALS['type'] === 'user' &&
			$GLOBALS['egw_info']['user']['preferences']['groupdav']['debug-log'] !== 'never')
		{
			if ($GLOBALS['type'] === 'user')
			{
				$logs = array();
				if (file_exists($log_dir=$GLOBALS['egw_info']['server']['files_dir'].'/groupdav') && ($files = scandir($log_dir)))
				{
					$account_lid_len = strlen($GLOBALS['egw_info']['user']['account_lid']);
					foreach($files as $log)
					{
						if (substr($log,0,$account_lid_len+1) == $GLOBALS['egw_info']['user']['account_lid'].'-' &&
							substr($log,-4) == '.log')
						{
							$logs['groupdav/'.$log] = egw_time::to(filemtime($log_dir.'/'.$log)).': '.
								str_replace('!','/',substr($log,$account_lid_len+1,-4));
						}
					}
				}
				$link = egw::link('/index.php',array(
					'menuaction' => 'groupdav.groupdav_hooks.log',
					'filename' => '',
				));
				$onchange = "egw_openWindowCentered('$link'+encodeURIComponent(this.value), '_blank', 1000, 500); this.value=''";
			}
			else	// allow to force users to NOT be able to delete their profiles
			{
				$logs = array('never' => lang('Never'));
			}
			$settings['show-log'] = array(
				'type'   => 'select',
				'label'  => 'Show log of following device',
				'name'   => 'show-log',
				'help'   => lang('You need to set enable logging to "%1" to create/update a log.',
					lang('Requests and full responses to files directory')),
				'values' => $logs,
				'xmlrpc' => True,
				'admin'  => False,
				'onchange' => $onchange,
			);
		}
		return $settings;
	}

	/**
	 * Open log window for log-file specified in GET parameter filename (relative to files_dir)
	 *
	 * $_GET['filename'] has to be in groupdav sub-dir of files_dir and start with account_lid of current user
	 *
	 * @throws egw_exception_wrong_parameter
	 */
	public function log()
	{
		$filename = $_GET['filename'];
		if (!preg_match('|^groupdav/'.preg_quote($GLOBALS['egw_info']['user']['account_lid'],'|').'-[^/]+\.log$|',$filename))
		{
			throw new egw_exception_wrong_parameter("Access denied to file '$filename'!");
		}
		$GLOBALS['egw_info']['flags']['css'] = '
body { background-color: #e0e0e0; overflow: hidden; }
pre.tail { background-color: white; padding-left: 5px; margin-left: 5px; }
';
		$header = str_replace('!','/',substr($filename,10+strlen($GLOBALS['egw_info']['user']['account_lid']),-4));
		$tail = new egw_tail($filename);
		$GLOBALS['egw']->framework->render($tail->show($header),false,false);
	}
}