<?php
/**
 * EGroupware API - Check for updates
 *
 * @link http://www.egroupware.org
 * @author Ralf Becker <RalfBecker-AT-outdoor-training.de>
 * @license http://opensource.org/licenses/gpl-license.php GPL - GNU General Public License
 * @package api
 * @subpackage framework
 * @access public
 */

namespace EGroupware\Api\Framework;

use EGroupware\Api\Html;
use EGroupware\Api\Cache;
use EGroupware\Api;

/**
 * Check for updates
 *
 * https://www.egroupware.org/currentversion
 *
 * Contains multiple lines with version numbers:
 * 1. current stable version      eg. 17.1.20180118
 * 2. last stable security update eg. 17.1.20180118
 * 3. last old-stable security up.eg. 16.1.20171106 (only if that is still secure!)
 * 4. further old secure versions, if available
 */
class Updates
{
	/**
	 * URL to check for security or maintenance updates
	 */
	const CURRENT_VERSION_URL = 'https://www.egroupware.org/currentversion';
	/**
	 * How long to cache (in secs) / often to check for updates
	 */
	const VERSIONS_CACHE_TIMEOUT = 7200;
	/**
	 * After how many days of not applied security updates, start warning non-admins too
	 */
	const WARN_USERS_DAYS = 5;

	/**
	 * Get versions of available updates
	 *
	 * @param string $api =null major api version to return security for, default latest
	 * @return array verions for keys "current" and "security"
	 */
	public static function available($api=null)
	{
		$versions = Cache::getTree(__CLASS__, 'versions', function() use ($api)
		{
			$versions = array();
			$security = null;
			if (($remote = file_get_contents(self::CURRENT_VERSION_URL, false, Api\Framework::proxy_context())))
			{
				$all_versions = explode("\n", $remote);
				$current = array_shift($all_versions);
				if (empty($all_versions)) $all_versions = array($current);
				// find latest security release for optional API version
				foreach(array_reverse($all_versions) as $security)
				{
					if (isset($api) && $api === substr($security, 0, strlen($api))) break;
				}
				$versions = array(
					'current'  => $current,		// last maintenance update
					'security' => $security,	// last security update
				);
			}
			return $versions;
		}, array(), self::VERSIONS_CACHE_TIMEOUT);

		//error_log(__METHOD__."($api) returning ".array2string($versions));
		return $versions;
	}

	/**
	 * Check update status
	 *
	 * @return string
	 * @todo Check from client-side, if server-side check fails
	 */
	public static function notification()
	{
		$api = preg_replace('/ ?EPL$/', '', self::api_version());
		$api_major = $matches = null;
		if (preg_match('/^(\d+\.\d+)\./', $api, $matches))
		{
			$api_major = $matches[1];
		}

		$versions = self::available($api_major);

		if ($versions)
		{
			if (version_compare($api, $versions['security'], '<'))
			{
				if (!$GLOBALS['egw_info']['user']['apps']['admin'] && !self::update_older($versions['security'], self::WARN_USERS_DAYS))
				{
					return null;
				}
				return Html::a_href(Html::image('api', 'security-update', lang('EGroupware security update %1 needs to be installed!', $versions['security'])),
					'http://www.egroupware.org/changelog', null, ' target="_blank"');
			}
			if ($GLOBALS['egw_info']['user']['apps']['admin'] && version_compare($api, $versions['current'], '<'))
			{
				$msg = substr($versions['current'], 0, strlen($api_major)) == $api_major ?
					lang('EGroupware maintenance update %1 available', $versions['current']) :
					lang('New EGroupware release %1 available', $versions['current']);
				return Html::a_href(Html::image('api', 'update', $msg),
					'http://www.egroupware.org/changelog', null, ' target="_blank"');
			}
		}
		elseif ($GLOBALS['egw_info']['user']['apps']['admin'])
		{
			$error = lang('Automatic update check failed, you need to check manually!');
			if (!ini_get('allow_url_fopen'))
			{
				$error .= "\n".lang('%1 setting "%2" = %3 disallows access via http!',
					'php.ini', 'allow_url_fopen', array2string(ini_get('allow_url_fopen')));
			}
			return Html::a_href(Html::image('api', 'update', $error),
				'http://www.egroupware.org/changelog', null, ' target="_blank" data-api-version="'.$api.'"');
		}
		return null;
	}

	/**
	 * Check if version is older then $days days
	 *
	 * @param string $version eg. "14.1.20140715" last part is checked (only if > 20140000!)
	 * @param int $days
	 * @return boolean
	 */
	protected static function update_older($version, $days)
	{
		list(,,$date) = explode('.', $version);
		if ($date < 20140000) return false;
		$version_timestamp = mktime(0, 0, 0, (int)substr($date, 4, 2), (int)substr($date, -2), (int)substr($date, 0, 4));

		return (time() - $version_timestamp) / 86400 > $days;
	}

	/**
	 * Get current API version from api/setup/setup.inc.php "maintenance_release" or database, whichever is bigger
	 *
	 * @param string &$changelog on return path to changelog
	 * @return string
	 */
	public static function api_version(&$changelog=null)
	{
		$changelog = EGW_SERVER_ROOT.'/doc/rpm-build/debian.changes';

		return Cache::getTree(__CLASS__, 'api_version', function()
		{
			$version = preg_replace('/[^0-9.]/', '', $GLOBALS['egw_info']['server']['versions']['api']);

			if (empty($GLOBALS['egw_info']['server']['versions']['maintenance_release']))
			{
				$setup_info = null;
				include (EGW_SERVER_ROOT.'/api/setup/setup.inc.php');
				$GLOBALS['egw_info']['server']['versions'] += $setup_info['api']['versions'];
				unset($setup_info);
			}
			if (version_compare($version, $GLOBALS['egw_info']['server']['versions']['maintenance_release'], '<'))
			{
				$version = $GLOBALS['egw_info']['server']['versions']['maintenance_release'];
			}
			return $version;
		}, array(), 300);
	}
}
