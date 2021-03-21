<?php
/**
 * EGroupware API: HTTP_REFERER header handling
 *
 * @link http://www.egroupware.org
 * @license http://opensource.org/licenses/gpl-license.php GPL - GNU General Public License
 * @author Ralf Becker <RalfBecker-AT-outdoor-training.de>
 * @copyright 2001-2016 by RalfBecker@outdoor-training.de
 * @package api
 * @subpackage header
 * @version $Id$
 */

namespace EGroupware\Api\Header;

/**
 * Handling of HTTP_REFERER header
 */
class Referer
{
	/**
	 * gets an eGW conformant referer from $_SERVER['HTTP_REFERER'], suitable for direct use in the link function
	 *
	 * @param string $default ='' default to use if referer is not set by webserver or not determinable
	 * @param string $referer ='' referer string to use, default ('') use $_SERVER['HTTP_REFERER']
	 * @return string
	 * @todo get "real" referer for jDots template
	 */
	static function get($default='',$referer='')
	{
		// HTTP_REFERER seems NOT to get urldecoded
		if (!$referer) $referer = urldecode($_SERVER['HTTP_REFERER']);

		$webserver_url = $GLOBALS['egw_info']['server']['webserver_url'];
		if (empty($webserver_url) || $webserver_url[0] === '/')	// url is just a path
		{
			$referer = preg_replace('/^https?:\/\/[^\/]+/','',$referer);	// removing the domain part
		}
		if (strlen($webserver_url) > 1)
		{
			list(,$referer) = explode($webserver_url,$referer,2);
		}
		$ret = str_replace('/etemplate/process_exec.php', '/index.php', $referer);

		if (empty($ret) || strpos($ret, 'cd=yes') !== false) $ret = $default;

		return $ret;
	}
}
UserAgent::_init_static();
