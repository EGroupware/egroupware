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
	 * 1. $_SERVER['HTTP_X_FORWARDED_HOST'] (X-Forwarded-Host HTTP header)
	 * 2. $_SERVER['HTTP_HOST'] (Host HTTP header)
	 * 3. $GLOBALS['egw_info']['server']['hostname'] (EGroupware Setup) - only reached when there is
	 *    no live request to derive a host from at all (eg. a CLI/cron job building a URL for a
	 *    notification email, which has no $_SERVER['HTTP_HOST'] to begin with)
	 * 4. 'localhost' as a last-resort fallback
	 *
	 * A live request's own host ALWAYS wins over the Setup-configured hostname now, not just when
	 * $use_setup_hostname is false - that Setup value is unreliable enough in practice (almost
	 * always literally "localhost", and otherwise frequently stale on an install reachable via
	 * more than one hostname/reverse-proxy path) that preferring it over the browser's own actual
	 * request broke callers who need an externally-usable URL guaranteed to match the CURRENT
	 * page's own origin (Http::fullUrl(), the only $use_setup_hostname=true caller) - found live:
	 * a real customer's local-shim mail accounts all failed with a browser-level NetworkError, the
	 * browser's own connect-src 'self' CSP silently blocking a same-origin JMAP endpoint built
	 * with this preference, because the page's actual origin didn't match the Setup hostname.
	 *
	 * @param boolean $use_setup_hostname =false true: fall back to the Setup hostname config if
	 *  (and only if) there is no live request to derive a host from at all
	 * @return string
	 */
	static function host($use_setup_hostname=false)
	{
		if (isset($_SERVER['HTTP_X_FORWARDED_HOST']))
		{
			list($host) = explode(',', $_SERVER['HTTP_X_FORWARDED_HOST']);
		}
		elseif (isset($_SERVER['HTTP_HOST']))
		{
			$host = $_SERVER['HTTP_HOST'];
		}
		elseif ($use_setup_hostname && !empty($GLOBALS['egw_info']['server']['hostname']))
		{
			$host = $GLOBALS['egw_info']['server']['hostname'];
		}
		else
		{
			$host = 'localhost';
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
		isset($_SERVER['HTTP_X_FORWARDED_PROTO']) && $_SERVER['HTTP_X_FORWARDED_PROTO'] === 'https' ?
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