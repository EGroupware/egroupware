<?php
/**
 * EGroupware API: server-side browser and mobile device detection
 *
 * @link http://www.egroupware.org
 * @author Ralf Becker <RalfBecker-AT-outdoor-training.de> complete rewrite in 6/2006 and earlier modifications
 * @license http://opensource.org/licenses/gpl-license.php GPL - GNU General Public License
 * @author RalfBecker-AT-outdoor-training.de
 * @copyright 2001-2016 by RalfBecker@outdoor-training.de
 * @package api
 * @subpackage header
 * @version $Id$
 */

namespace EGroupware\Api\Header;

/**
 * Server-side browser and mobile device detection based on User-Agent header
 */
class UserAgent
{
	/**
	 * Normalized type of user-agent
	 *
	 * @return string 'firefox', 'msie', 'safari' (incl. iPhone), 'chrome', 'opera', 'konqueror', 'mozilla'
	 */
	public static function type()
	{
		return self::$user_agent;
	}

	/**
	 * Version of user-agent as specified by browser
	 *
	 * @return integer
	 */
	public static function version()
	{
		return (int)self::$ua_version;
	}

	/**
	 * Mobile device type
	 *
	 * @return string "iphone", "ipod", "ipad", "android", "symbianos", "blackberry", "kindle", "opera mobi", "windows phone"
	 */
	public static function mobile()
	{
		return self::$ua_mobile;
	}

	/**
	 * Convert user-agent string to OS and Browser
	 *
	 * @param string $user_agent =null
	 */
	public static function osBrowser($user_agent=null)
	{
		$matches = $os_matches = null;
		if (preg_match_all('#([^/]+)/([0-9.]+)( \([^)]+\))? ?#i', $user_agent, $matches) && count($matches) >= 4)
		{
			if (preg_match('/((Windows|Linux|Mac OS X|iOS|Android)( NT)?) ([0-9._]+)?/', $os=$matches[3][0], $os_matches) ||
				preg_match('/((Windows|Linux|Mac OS X|iOS|Android)( NT)?)\/([0-9._]+)?/', $os=$matches[0][0], $os_matches))
			{
				$os = $os_matches[1].' '.str_replace('_', '.', $os_matches[4]);
			}
			$browser = $matches[1][2] === 'Version' ? $matches[1][3] : (!empty($matches[1][2]) ? $matches[1][2] : $matches[1][1]);
			$browser_version = !empty($matches[2][2]) ? $matches[2][2] : $matches[2][1];
			$user_agent = "$os\n$browser $browser_version";
		}
		return str_replace('/', ' ', $user_agent);
	}

	/**
	 * user-agent: 'firefox', 'msie', 'edge', 'safari' (incl. iPhone), 'chrome', 'opera', 'konqueror', 'mozilla'
	 *
	 * @var string
	 */
	protected static $user_agent;
	/**
	 * User agent is mobile browser: "iphone", "ipod", "ipad", "android", "symbianos", "blackberry", "kindle", "opera mobi", "windows phone"
	 *
	 * @var string with name of mobile browser or null, if not mobile browser
	 */
	protected static $ua_mobile;

	/**
	 * version of user-agent as specified by browser
	 *
	 * @var string
	 */
	protected static $ua_version;

	/**
	 * initialise our static vars
	 */
	static function _init_static()
	{
		// should be Ok for all HTML 4 compatible browsers
		$parts = $all_parts = null;
		if(!preg_match('/compatible; ([a-z]+)[\/ ]+([0-9.]+)/i',$_SERVER['HTTP_USER_AGENT'] ?? '',$parts))
		{
			preg_match_all('/([a-z]+)\/([0-9.]+)/i',$_SERVER['HTTP_USER_AGENT'] ?? '',$all_parts,PREG_SET_ORDER);
			$parts = array_pop($all_parts);
			foreach($all_parts as $p)
			{
				if ($p[1] == 'Chrome' && $parts[1] != 'Edge')
				{
					$parts = $p;
					break;
				}
			}
		}
		list(,self::$user_agent,self::$ua_version) = $parts;
		if ((self::$user_agent = strtolower(self::$user_agent ?? '')) == 'version') self::$user_agent = 'opera';
		// IE no longer reports MSIE, but "Trident/7.0; rv:11.0"
		if (self::$user_agent=='trident')
		{
			self::$user_agent='msie';
			$matches = null;
			self::$ua_version = preg_match('|Trident/[0-9.]+; rv:([0-9.]+)|i', $_SERVER['HTTP_USER_AGENT'], $matches) ?
				$matches[1] : 11.0;
		}
		// iceweasel is based on mozilla and we treat it like as firefox
		if (self::$user_agent == 'iceweasel')
		{
			self::$user_agent = 'firefox';
		}
		// MS Edge sometimes reports just "Mozilla/5.0 (Windows NT 10.0; Win64; x64)"
		if (self::$user_agent == 'mozilla' && self::$ua_version == '5.0')
		{
			self::$user_agent = 'edge';
			self::$ua_version = '12.0';
		}
		self::$ua_mobile = preg_match('/(iPhone|iPod|iPad|Android|SymbianOS|Blackberry|Kindle|Opera Mobi|Windows Phone)/i',
			$_SERVER['HTTP_USER_AGENT'] ?? '', $matches) ? strtolower($matches[1]) : null;

		//error_log("HTTP_USER_AGENT='$_SERVER[HTTP_USER_AGENT]', UserAgent: '".self::$user_agent."', Version: '".self::$ua_version."', isMobile=".array2string(self::$ua_mobile));
	}
}
UserAgent::_init_static();