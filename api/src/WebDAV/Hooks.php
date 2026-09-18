<?php
/**
 * EGroupware API: shared WebDAV request/response logging preferences UI + log-viewer
 *
 * Used by both CalDAV\Hooks (app=groupdav, CalDAV/CardDAV) and filemanager_hooks (app=filemanager,
 * plain WebDAV) - the actual request/response logging engine lives in HTTP_WebDAV_Server itself,
 * @see HTTP_WebDAV_Server::logApp()/log_request().
 *
 * @link https://www.egroupware.org
 * @license https://opensource.org/licenses/gpl-license.php GPL - GNU General Public License
 * @package api
 * @subpackage webdav
 * @author Ralf Becker <rb@egroupware.org>
 * @copyright (c) 2026 by Ralf Becker <rb@egroupware.org>
 */

namespace EGroupware\Api\WebDAV;

use EGroupware\Api;
use HTTP_WebDAV_Server;

require_once __DIR__.'/Server.php';

class Hooks
{
	/**
	 * Build a request/response-log preference section (own tab) for $app
	 *
	 * @param string $app app-name AND files_dir subdirectory name, must match the relevant
	 *  HTTP_WebDAV_Server subclass's logApp()
	 * @param string $help help text for the "Enable logging" select, naming the protocol/devices
	 * @param string $log_menuaction menuaction opening the log-viewer popup, e.g.
	 *  "filemanager.filemanager_hooks.log" - the target method must call self::logViewer($app)
	 * @param int|string $account_id account whose existing logs to list (ignored unless
	 *  $GLOBALS['type'] === 'user')
	 * @param string $tab_title ='Logging / debuging' title of the tab this settings section creates
	 * @return array settings, to be merged (+=) into the calling app's own settings() hook return value
	 */
	public static function logSettings($app, $help, $log_menuaction, $account_id, $tab_title='Logging / debuging')
	{
		$settings = array();
		$settings[$app.'_log_section'] = array(
			'type'  => 'section',
			'title' => $tab_title,
		);
		$settings['debug_level'] = array(
			'type'   => 'select',
			'label'  => 'Enable logging',
			'name'   => 'debug_level',
			'help'   => $help,
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
			$GLOBALS['egw_info']['user']['preferences'][$app]['debug-log'] !== 'never')
		{
			if ($GLOBALS['type'] === 'user')
			{
				$logs = array();
				$relativ_log_dir = $app.'/'.HTTP_WebDAV_Server::sanitize_filename(Api\Accounts::id2name($account_id));
				$log_dir = $GLOBALS['egw_info']['server']['files_dir'].'/'.$relativ_log_dir;
				if (file_exists($log_dir) && ($files = scandir($log_dir)))
				{
					foreach($files as $log)
					{
						if (substr($log, -4) == '.log')
						{
							$logs[$relativ_log_dir.'/'.$log] = Api\DateTime::to(filemtime($log_dir.'/'.$log)).': '.
								str_replace('!', '/', $log);
						}
					}
				}
				$link = Api\Framework::link('/index.php', array(
					'menuaction' => $log_menuaction,
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
	 * $_GET['filename'] has to be in $app's sub-dir of files_dir and start with the current user's
	 * account_lid, unless they're an admin.
	 *
	 * @param string $app app-name / files_dir subdirectory, matching what built the "show-log" list
	 * @throws Api\Exception\WrongParameter
	 */
	public static function logViewer($app)
	{
		$filename = $_GET['filename'];
		$matches = null;
		if (!preg_match('|^'.preg_quote($app, '|').'/'.($GLOBALS['egw_info']['user']['apps']['admin'] ? '[^/]+/' :
			preg_quote(HTTP_WebDAV_Server::sanitize_filename($GLOBALS['egw_info']['user']['account_lid']), '|')).'(.*)\.log$|', $filename, $matches))
		{
			throw new Api\Exception\WrongParameter("Access denied to file '$filename'!");
		}
		$GLOBALS['egw_info']['flags']['css'] = '
body { background-color: #e0e0e0; overflow: hidden; }
pre.tail { background-color: white; padding-left: 5px; margin-left: 5px; }
';
		$tail = new Api\Json\Tail($filename);
		$GLOBALS['egw']->framework->render($tail->show(str_replace('!', '/', $matches[1])), false, false);
	}
}
