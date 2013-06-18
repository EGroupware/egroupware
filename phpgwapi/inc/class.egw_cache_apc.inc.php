<?php
/**
 * EGroupware API: Caching provider storing data in PHP's APC
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
 * Caching provider storing data in PHP's APC
 *
 * The provider concats all $keys with '::' to get a single string.
 *
 * To use this provider set in your header.inc.php:
 * $GLOBALS['egw_info']['server']['cache_provider_instance'] = array('egw_cache_apc');
 * and optional also $GLOBALS['egw_info']['server']['cache_provider_tree'] (defaults to instance)
 */
class egw_cache_apc extends egw_cache_provider_check implements egw_cache_provider
{
	/**
	 * Constructor, eg. opens the connection to the backend
	 *
	 * @throws Exception if connection to backend could not be established
	 * @param array $params eg. array('localhost'[,'localhost:11211',...])
	 */
	function __construct(array $params)
	{
		if (!function_exists('apc_fetch'))	// apc >= 3.0
		{
			throw new Exception (__METHOD__.'('.array2string($params).") No function apc_fetch()!");
		}
		if (PHP_SAPI == 'cli' && !ini_get('apc.enable_cli'))
		{
			throw new Exception (__METHOD__.'('.array2string($params).") APC NOT enabled for cli, check apc.enable_cli!");
		}
	}

	/**
	 * Check if APC is available for caching user data
	 *
	 * Default shared memory size of 32M is just enough for the byte code cache,
	 * but not for caching user data, we only use APC by default if we have at least 64M.
	 *
	 * @return boolean true: apc available, false: not
	 */
	public static function available()
	{
		$available = false;
		if (function_exists('apc_fetch') && (PHP_SAPI != 'cli' || ini_get('apc.enable_cli')))
		{
			$size = ini_get('apc.shm_size');
			// ancent APC (3.1.3) in Debian 6/Squezze has size in MB without a unit
			if (is_numeric($size) && $size <= 1048576) $size .= 'M';

			switch(strtoupper(substr($size, -1)))
			{
				case 'G':
					$size *= 1024;
				case 'M':
					$size *= 1024;
				case 'K':
					$size *= 1024;
			}
			$size *= ini_get('apc.shm_segments');

			// only cache in APC, if we have at least 64M available (default is 32M)
			$available = $size >= 67108864;
		}
		//error_log(__METHOD__."() size=$size returning ".array2string($available));
		return $available;
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
		return apc_store(self::key($keys),$data,$expiration);
	}

	/**
	 * Get some data from the cache
	 *
	 * @param array $keys eg. array($level,$app,$location)
	 * @return mixed data stored or NULL if not found in cache
	 */
	function get(array $keys)
	{
		$data = apc_fetch($key=self::key($keys),$success);

		if (!$success)
		{
			//error_log(__METHOD__."(".array2string($keys).") key='$key' NOT found!");
			return null;
		}
		//error_log(__METHOD__."(".array2string($keys).") key='$key' found ".bytes(serialize($data))." bytes).");
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
		return apc_delete(self::key($keys));
	}

	/**
	 * Delete all data under given keys
	 *
	 * @param array $keys eg. array($level,$app,$location)
	 * @return boolean true on success, false on error (eg. $key not set)
	 */
	function flush(array $keys)
	{
		//error_log(__METHOD__."(".array2string($keys).")");
		foreach(new APCIterator('user', $preg='/^'.preg_quote(self::key($keys).'/')) as $item)
		{
			//error_log(__METHOD__."(".array2string($keys).") preg='$preg': calling apc_delete('$item[key]')");
			apc_delete($item['key']);
		}
		return true;
	}

	/**
	 * Create a single key from $keys
	 *
	 * @param array $keys
	 * @return string
	 */
	private static function key(array $keys)
	{
		return implode('::',$keys);
	}
}
