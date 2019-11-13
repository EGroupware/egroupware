<?php
/**
 * EGroupware API: HTTP header handling for host and schema
 *
 * @link http://www.egroupware.org
 * @license http://opensource.org/licenses/gpl-license.php GPL - GNU General Public License
 * @author Ralf Becker <RalfBecker-AT-outdoor-training.de>
 * @copyright 2019 by RalfBecker@outdoor-training.de
 * @package api
 * @subpackage header
 */

namespace EGroupware\Api\Header;

/**
 * HTTP header handling for host and schema
 */
class Http
{
	/**
	 * Get host considering X-Forwarded-Host and Host header
	 *
	 * Host is determined in the following order / priority:
	 * 1. $GLOBALS['egw_info']['server']['hostname'] !== 'localhost' (EGroupware Setup)
	 * 2. $_SERVER['HTTP_X_FORWARDED_HOST'] (X-Forwarded-Host HTTP header)
	 * 3. $_SERVER['HTTP_HOST'] (Host HTTP header)
	 *
	 * @param boolean $use_setup_hostname =false true: hostame config from setup has highest precedence, default not
	 * @return string
	 */
	static function host($use_setup_hostname=false)
	{
		if ($use_setup_hostname && !empty($GLOBALS['egw_info']['server']['hostname']) &&
			$GLOBALS['egw_info']['server']['hostname'] !== 'localhost')
		{
			$host = $GLOBALS['egw_info']['server']['hostname'];
		}
		elseif (isset($_SERVER['HTTP_X_FORWARDED_HOST']))
		{
			list($host) = explode(',', $_SERVER['HTTP_X_FORWARDED_HOST']);
		}
		else
		{
			$host = $_SERVER['HTTP_HOST'];
		}
		return $host;
	}

	/**
	 * Get schema considering X-Forwarded-Schema and used schema
	 *
	 * The following HTTP Headers / $_SERVER variables and EGroupware configuration
	 * is taken into account to determine if URL should use https schema:
	 * - $_SERVER['HTTPS'] !== off
	 * - $GLOBALS['egw_info']['server']['enforce_ssl'] (EGroupware Setup)
	 * - $_SERVER['HTTP_X_FORWARDED_PROTO'] === 'https' (X-Forwarded-Proto HTTP header)
	 *
	 * @return string
	 */
	static function schema()
	{
		return !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off' ||
			$_SERVER['SERVER_PORT'] == 443 ||
			!empty($GLOBALS['egw_info']['server']['enforce_ssl']) ||
			$_SERVER['HTTP_X_FORWARDED_PROTO'] === 'https' ?
				'https' : 'http';
	}

	/**
	 * Get a full / externally usable URL from an EGroupware link
	 *
	 * Code is only used, if $link is only a path (starts with slash)
	 *
	 * @param string $link
	 */
	static function fullUrl($link)
	{
		if ($link[0] === '/')
		{
			$link = self::schema().'://'.self::host(true).$link;
		}
		return $link;
	}
}