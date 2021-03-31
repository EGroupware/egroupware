<?php
/**
 * EGroupware API: Session Type(s)
 *
 * @link http://www.egroupware.org
 * @license http://opensource.org/licenses/gpl-license.php GPL - GNU General Public License
 * @package api
 * @subpackage session
 * @author Ralf Becker <rb-at-egroupware.org>
 */

namespace EGroupware\Api\Session;

/**
 * Determine type of session based on request-uri and currentapp
 *
 *
 */
class Type
{
	const LOGIN    = 'login';
	const SYNCML   = 'syncml';
	const GROUPDAV = 'groupdav';
	const ESYNC    = 'esync';
	const WEBDAV   = 'webdav';
	const WEBGUI   = 'webgui';
	const SETUP    = 'setup';
	const SITEMGR  = 'sitemgr';
	const SHARING  = 'sharing';
	const WOPI     = 'wopi';

	/**
	 * Return the type of session, based on the $request_uri
	 *
	 * @param string $request_uri
	 * @param string $currentapp
	 * @param boolean $nologin =false true: return self::WEBGUI instead of self::LOGIN
	 * @return string see above constants
	 */
	public static function get($request_uri, $currentapp, $nologin=false)
	{
		// sometimes the query makes an url unparseble, eg. /index.php?url=http://something.com/other.html
		if (!($script = @parse_url($request_uri,PHP_URL_PATH)))
		{
			list($script) = explode('?',$request_uri);
			if ($script[0] != '/') $script = parse_url($script,PHP_URL_PATH);
		}
		if (($e = strpos($script,'.php/')) !== false)
		{
			$script = substr($script,0,$e+4);	// cut off WebDAV path
		}
		elseif (substr($script,-1) == '/')
		{
			$script .= 'index.php';
		}
		$script_name = basename($script);

		if (!$nologin && ($script_name == 'login.php' || $script_name == 'logout.php'))
		{
			$type = self::LOGIN;
		}
		elseif($script_name == 'rpc.php')
		{
			$type = self::SYNCML;
		}
		elseif($script_name == 'groupdav.php')
		{
			$type = self::GROUPDAV;
		}
		elseif($script_name == 'webdav.php')
		{
			$type = self::WEBDAV;
		}
		elseif($script_name == 'share.php')
		{
			$type = self::SHARING;
		}
		elseif(basename(dirname($script)) == 'collabora' || $currentapp == 'collabora')
		{
			$type = self::WOPI;
		}
		elseif(basename(dirname($script)) == 'activesync' || $currentapp == 'activesync')
		{
			$type = self::ESYNC;
		}
		elseif(basename(dirname($script)) == 'setup' || $currentapp == 'setup')
		{
			$type = self::SETUP;
		}
		elseif ($currentapp == 'sitemgr-link')
		{
			$type = self::SITEMGR;
		}
		else
		{
			$type = self::WEBGUI;
		}
		//error_log(__METHOD__."('$request_uri', '$currentapp', $nologin) --> '$type'");
		return $type;
	}
}