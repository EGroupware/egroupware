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
 * @version $Id$
 */

namespace EGroupware\Api\Framework;

use EGroupware\Api\Html;
use EGroupware\Api\Cache;
use EGroupware\Api;

/**
 * Check for updates
 */
class Updates
{
	/**
	 * URL to check for security or maintenance updates
	 */
	const CURRENT_VERSION_URL = 'http://www.egroupware.org/currentversion';
	/**
	 * How long to cache (in secs) / often to check for updates
	 */
	const VERSIONS_CACHE_TIMEOUT = 7200;
	/**
	 * After how many days of not applied security updates, start warning non-admins too
	 */
	const WARN_USERS_DAYS = 3;

	/**
	 * Get versions of available updates
	 *
	 * @return array verions for keys "current" and "security"
	 */
	public static function available()
	{
		$versions = Cache::getTree(__CLASS__, 'versions', function()
		{
			$versions = array();
			$security = null;
			if (($remote = file_get_contents(self::CURRENT_VERSION_URL, false, Api\Framework::proxy_context())))
			{
				list($current, $security) = explode("\n", $remote);
				if (empty($security)) $security = $current;
				$versions = array(
					'current'  => $current,		// last maintenance update
					'security' => $security,	// last security update
				);
			}
			return $versions;
		}, array(), self::VERSIONS_CACHE_TIMEOUT);

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
		$versions = self::available();

		$api = self::api_version();

		if ($versions)
		{
			if (version_compare($api, $versions['security'], '<'))
			{
				if (!$GLOBALS['egw_info']['user']['apps']['admin'] && !self::update_older($versions['security'], self::WARN_USERS_DAYS))
				{
					return null;
				}
				return Html::a_href(Html::image('phpgwapi', 'security-update', lang('EGroupware security update %1 needs to be installed!', $versions['security'])),
					'http://www.egroupware.org/changelog', null, ' target="_blank"');
			}
			if ($GLOBALS['egw_info']['user']['apps']['admin'] && version_compare($api, $versions['current'], '<'))
			{
				return Html::a_href(Html::image('phpgwapi', 'update', lang('EGroupware maintenance update %1 available', $versions['current'])),
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
			return Html::a_href(Html::image('phpgwapi', 'update', $error),
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
	 * Get current API version from changelog or database, whichever is bigger
	 *
	 * @param string &$changelog on return path to changelog
	 * @return string
	 */
	public static function api_version(&$changelog=null)
	{
		$changelog = EGW_SERVER_ROOT.'/doc/rpm-build/debian.changes';

		return Cache::getTree(__CLASS__, 'api_version', function() use ($changelog)
		{
			$version = preg_replace('/[^0-9.]/', '', $GLOBALS['egw_info']['server']['versions']['phpgwapi']);
			// parse version from changelog
			$matches = null;
			if (($f = fopen($changelog, 'r')) && preg_match('/egroupware-epl \(([0-9.]+)/', fread($f, 80), $matches) &&
				version_compare($version, $matches[1], '<'))
			{
				$version = $matches[1];
				fclose($f);
			}
			return $version;
		}, array(), 300);
	}
}
