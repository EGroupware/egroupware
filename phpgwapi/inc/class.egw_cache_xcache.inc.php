<?php
/**
 * EGroupware API: Caching provider storing data in PHP's xcache
 *
 * @link http://www.egroupware.org
 * @license http://opensource.org/licenses/gpl-license.php GPL - GNU General Public License
 * @package api
 * @subpackage cache
 * @author Ralf Becker <RalfBecker-AT-outdoor-training.de>
 * @copyright (c) 2010-12 by Ralf Becker <RalfBecker-AT-outdoor-training.de>
 * @version $Id$
 */

/**
 * Caching provider storing data in PHP's xcache
 *
 * The provider concats all $keys with '::' to get a single string.
 *
 * To use this provider set in your header.inc.php:
 * $GLOBALS['egw_info']['server']['cache_provider_instance'] = array('egw_cache_apc');
 * and optional also $GLOBALS['egw_info']['server']['cache_provider_tree'] (defaults to instance)
 */
class egw_cache_xcache extends egw_cache_provider_check implements egw_cache_provider
{
	/**
	 * Constructor, eg. opens the connection to the backend
	 *
	 * @throws Exception if connection to backend could not be established
	 * @param array $params eg. array('localhost'[,'localhost:11211',...])
	 */
	function __construct(array $params)
	{
		if (!function_exists('xcache_get'))
		{
			throw new Exception (__METHOD__.'('.array2string($params).") No function xcache_get()!");
		}
		if (PHP_SAPI == 'cli')
		{
			throw new Exception (__METHOD__.'('.array2string($params).") xcache does NOT support cli sapi!");
		}
	}

	/**
	 * Stores some data in the cache
	 *
	 * @param array $keys eg. array($level,$app,$location)
	 * @param mixed $data
	 * @param int $expiration=0
	 * @return boolean true on success, false on error
	 */
	function set(array $keys,$data,$expiration=0)
	{
		return xcache_set(self::key($keys),$data,$expiration);
	}

	/**
	 * Get some data from the cache
	 *
	 * @param array $keys eg. array($level,$app,$location)
	 * @return mixed data stored or NULL if not found in cache
	 */
	function get(array $keys)
	{
		$data = xcache_get($key=self::key($keys));

		return $data;
	}

	/**
	 * Delete some data from the cache
	 *
	 * @param array $keys eg. array($level,$app,$location)
	 * @return boolean true on success, false on error (eg. $key not set)
	 */
	function delete(array $keys)
	{
		return xcache_unset(self::key($keys));
	}

	/**
	 * Create a single key from $keys
	 *
	 * @param array $keys
	 * @return string
	 */
	private function key(array $keys)
	{
		return implode('::',$keys);
	}
}
