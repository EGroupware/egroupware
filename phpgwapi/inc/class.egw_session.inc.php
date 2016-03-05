<?php
/**
 * EGroupware API: session handling
 *
 * This class is based on the old phpgwapi/inc/class.sessions(_php4).inc.php:
 * (c) 1998-2000 NetUSE AG Boris Erdmann, Kristian Koehntopp
 * (c) 2003 FreeSoftware Foundation
 * Not sure how much the current code still has to do with it.
 *
 * Former authers were:
 * - NetUSE AG Boris Erdmann, Kristian Koehntopp
 * - Dan Kuykendall <seek3r@phpgroupware.org>
 * - Joseph Engo <jengo@phpgroupware.org>
 *
 * @link http://www.egroupware.org
 * @license http://opensource.org/licenses/gpl-license.php GPL - GNU General Public License
 * @package api
 * @subpackage session
 * @author Ralf Becker <ralfbecker@outdoor-training.de> since 2003 on
 * @version $Id$
 */

use EGroupware\Api;

/**
 * Create, verifies or destroys an EGroupware session
 *
 * @deprecated use Api\Session
 */
class egw_session extends Api\Session
{
	/**
	 * Stores or retrieve applications data in/form the eGW session
	 *
	 * @param string $location free lable to store the data
	 * @param string $appname ='' default current application (egw_info[flags][currentapp])
	 * @param mixed $data ='##NOTHING##' if given, data to store, if not specified
	 * @deprecated use egw_cache::setSession($appname, $location, $data) or egw_cache::getSession($appname, $location)
	 * @return mixed session data or false if no data stored for $appname/$location
	 */
	public static function &appsession($location = 'default', $appname = '', $data = '##NOTHING##')
	{
		if (isset($_SESSION[self::EGW_SESSION_ENCRYPTED]))
		{
			if (self::ERROR_LOG_DEBUG) error_log(__METHOD__.' called after session was encrypted --> ignored!');
			return false;	// can no longer store something in the session, eg. because commit_session() was called
		}
		if (!$appname)
		{
			$appname = $GLOBALS['egw_info']['flags']['currentapp'];
		}

		// allow to store eg. '' as the value.
		if ($data === '##NOTHING##')
		{
			if(isset($_SESSION[self::EGW_APPSESSION_VAR][$appname]) && array_key_exists($location,$_SESSION[self::EGW_APPSESSION_VAR][$appname]))
			{
				$ret =& $_SESSION[self::EGW_APPSESSION_VAR][$appname][$location];
			}
			else
			{
				$ret = false;
			}
		}
		else
		{
			$_SESSION[self::EGW_APPSESSION_VAR][$appname][$location] =& $data;
			$ret =& $_SESSION[self::EGW_APPSESSION_VAR][$appname][$location];
		}
		if (self::ERROR_LOG_DEBUG === 'appsession')
		{
			error_log(__METHOD__."($location,$appname,$data) === ".(is_scalar($ret) && strlen($ret) < 50 ?
				(is_bool($ret) ? ($ret ? '(bool)true' : '(bool)false') : $ret) :
				(strlen($r = array2string($ret)) < 50 ? $r : substr($r,0,50).' ...')));
		}
		return $ret;
	}
}
